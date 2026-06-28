<?php

namespace App\Http\Controllers;

use App\Models\Stylist;
use App\Models\StylistVacation;
use App\Services\ClinicResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StaffController extends Controller
{
    private const WORK_DAYS = [
        'monday' => 'Lunes',
        'tuesday' => 'Martes',
        'wednesday' => 'Miercoles',
        'thursday' => 'Jueves',
        'friday' => 'Viernes',
        'saturday' => 'Sabado',
        'sunday' => 'Domingo',
    ];

    public function index(Request $request, ClinicResolver $clinics): View
    {
        $clinic = $clinics->currentOrCreate($request->user());

        return view('staff.index', [
            'clinic' => $clinic,
            'stylists' => $clinic->stylists()
                ->with(['service', 'services', 'vacations' => fn ($query) => $query->where('ends_on', '>=', now($clinic->localTimezone())->toDateString())->orderBy('starts_on')])
                ->when(! $clinic->google_ever_synced_at, fn ($query) => $query->where('is_internal', false))
                ->orderBy('name')
                ->get(),
            'services' => $clinic->services()->where('is_active', true)->orderBy('name')->get(),
            'workDays' => self::WORK_DAYS,
        ]);
    }

    public function store(Request $request, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        [$data, $serviceIds] = $this->validatedData($request, $clinic->id);
        $stylist = $clinic->stylists()->create($data);
        $stylist->services()->sync($serviceIds);

        return redirect('/personal')->with('staff_status', 'Personal agregado correctamente.');
    }

    public function vacations(Request $request, ClinicResolver $clinics): JsonResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim((string) $data['name']);
        $stylist = $this->matchingStylists($clinic->id, $name)->first();

        if (! $stylist) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'No encontre un especialista llamado '.$name.'.',
            ], 404);
        }

        $vacations = $stylist->vacations()
            ->where('ends_on', '>=', now($clinic->localTimezone())->toDateString())
            ->orderBy('starts_on')
            ->get();

        if ($vacations->isEmpty()) {
            return response()->json([
                'status' => 'empty',
                'message' => $stylist->name.' no tiene vacaciones proximas registradas.',
                'stylist' => $stylist->name,
                'vacations' => [],
            ]);
        }

        $details = $vacations
            ->map(function (StylistVacation $vacation): string {
                $range = $vacation->starts_on->format('d/m/Y').' al '.$vacation->ends_on->format('d/m/Y');

                return $vacation->reason ? $range.', '.$vacation->reason : $range;
            })
            ->join('; ');

        return response()->json([
            'status' => 'found',
            'message' => 'Las proximas vacaciones de '.$stylist->name.' son: '.$details.'.',
            'stylist' => $stylist->name,
            'vacations' => $vacations->map(fn (StylistVacation $vacation): array => [
                'starts_on' => $vacation->starts_on->toDateString(),
                'ends_on' => $vacation->ends_on->toDateString(),
                'reason' => $vacation->reason,
            ])->values(),
        ]);
    }

    public function update(Request $request, Stylist $stylist): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic && $stylist->clinic_id === $clinic->id, 404);

        [$data, $serviceIds] = $this->validatedData($request, $clinic->id, true);
        $stylist->update($data);
        $stylist->services()->sync($serviceIds);
        $this->storeAvatar($request, $stylist);

        return redirect('/personal')->with('staff_status', 'Personal actualizado correctamente.');
    }

    public function storeVacation(Request $request, Stylist $stylist): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic && $stylist->clinic_id === $clinic->id, 404);

        $data = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $stylist->vacations()->create([
            'starts_on' => $data['starts_on'],
            'ends_on' => $data['ends_on'],
            'reason' => $data['reason'] ?? null,
        ]);

        return redirect('/personal')->with('staff_status', 'Vacaciones asignadas a '.$stylist->name.'.');
    }

    public function destroyVacation(Request $request, Stylist $stylist, StylistVacation $vacation): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic && $stylist->clinic_id === $clinic->id && $vacation->stylist_id === $stylist->id, 404);

        $vacation->delete();

        return redirect('/personal')->with('staff_status', 'Vacaciones eliminadas correctamente.');
    }

    public function destroy(Request $request, Stylist $stylist): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic && $stylist->clinic_id === $clinic->id, 404);

        DB::transaction(function () use ($stylist): void {
            if ($stylist->avatar_path) Storage::disk('public')->delete($stylist->avatar_path);
            $stylist->appointments()->update(['stylist_id' => null]);
            $stylist->delete();
        });

        return redirect('/personal')->with('staff_status', 'Empleado eliminado correctamente. La agenda se actualizo y sus citas quedaron sin asignar.');
    }

    private function validatedData(Request $request, int $clinicId, bool $allowAvatar = false): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'distinct', 'exists:services,id'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'work_days' => ['nullable', 'array'],
            'work_days.*' => ['string', 'in:'.implode(',', array_keys(self::WORK_DAYS))],
            'work_starts_at' => ['nullable', 'date_format:H:i'],
            'work_ends_at' => ['nullable', 'date_format:H:i'],
            'break_starts_at' => ['nullable', 'date_format:H:i', 'required_with:break_ends_at'],
            'break_ends_at' => ['nullable', 'date_format:H:i', 'required_with:break_starts_at', 'after:break_starts_at'],
            'weekly_schedule' => ['nullable', 'array'],
            'weekly_schedule.*.enabled' => ['nullable', 'boolean'],
            'weekly_schedule.*.start' => ['nullable', 'date_format:H:i'],
            'weekly_schedule.*.end' => ['nullable', 'date_format:H:i'],
            'weekly_schedule.*.break_start' => ['nullable', 'date_format:H:i'],
            'weekly_schedule.*.break_end' => ['nullable', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
            'avatar' => $allowAvatar
                ? ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072']
                : ['prohibited'],
            'preset_avatar' => $allowAvatar ? ['nullable', 'string', 'in:avatar-man-1.jpg,avatar-man-2.jpg,avatar-man-3.jpg,avatar-woman-1.jpg,avatar-woman-2.jpg,avatar-woman-3.jpg'] : ['prohibited'],
        ]);

        $serviceIds = collect($data['service_ids'] ?? [])->map(fn ($id) => (int) $id)->values();
        abort_unless($serviceIds->isEmpty() || \App\Models\Service::query()
            ->where('clinic_id', $clinicId)->whereIn('id', $serviceIds)->count() === $serviceIds->count(), 404);

        unset($data['service_ids']);
        $data['service_id'] = $serviceIds->first();
        $data['work_days'] = $data['work_days'] ?? [];
        $data['is_active'] = $request->boolean('is_active', true);

        if (! empty($data['weekly_schedule'])) {
            $weekly = [];
            foreach (self::WORK_DAYS as $day => $label) {
                $entry = $data['weekly_schedule'][$day] ?? [];
                $enabled = filter_var($entry['enabled'] ?? false, FILTER_VALIDATE_BOOL);
                $start = $entry['start'] ?? null;
                $end = $entry['end'] ?? null;
                $breakStart = $entry['break_start'] ?? null;
                $breakEnd = $entry['break_end'] ?? null;
                if ($enabled && (! $start || ! $end || $end <= $start)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(["weekly_schedule.{$day}.start" => "Revisa el horario del {$label}: la salida debe ser posterior a la entrada."]);
                }
                if (($breakStart && ! $breakEnd) || ($breakEnd && ! $breakStart) || ($breakStart && ($breakEnd <= $breakStart || $breakStart < $start || $breakEnd > $end))) {
                    throw \Illuminate\Validation\ValidationException::withMessages(["weekly_schedule.{$day}.break_start" => "El descanso del {$label} debe estar completo y dentro de la jornada."]);
                }
                $weekly[$day] = ['enabled' => $enabled, 'start' => $start, 'end' => $end, 'break_start' => $breakStart, 'break_end' => $breakEnd];
            }
            $data['weekly_schedule'] = $weekly;
            $data['work_days'] = collect($weekly)->filter(fn ($day) => $day['enabled'])->keys()->all();
            $firstOpen = collect($weekly)->first(fn ($day) => $day['enabled']);
            if ($firstOpen) {
                $data['work_starts_at'] = $firstOpen['start']; $data['work_ends_at'] = $firstOpen['end'];
                $data['break_starts_at'] = $firstOpen['break_start']; $data['break_ends_at'] = $firstOpen['break_end'];
            }
        }

        if (! empty($data['break_starts_at']) && (! empty($data['work_starts_at']) && $data['break_starts_at'] < $data['work_starts_at']
            || ! empty($data['work_ends_at']) && $data['break_ends_at'] > $data['work_ends_at'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'break_starts_at' => 'El descanso debe estar dentro del horario de trabajo.',
            ]);
        }

        unset($data['avatar']);
        unset($data['preset_avatar']);

        return [$data, $serviceIds->all()];
    }

    private function storeAvatar(Request $request, Stylist $stylist): void
    {
        if (! $request->hasFile('avatar') && ! $request->filled('preset_avatar')) return;
        $oldPath = $stylist->avatar_path;
        $path = $request->hasFile('avatar')
            ? $request->file('avatar')->store('staff-avatars/'.$stylist->clinic_id, 'public')
            : 'preset:'.$request->string('preset_avatar');
        $stylist->update(['avatar_path' => $path]);
        if ($oldPath && ! str_starts_with($oldPath, 'preset:')) Storage::disk('public')->delete($oldPath);
    }

    private function matchingStylists(int $clinicId, string $name)
    {
        $needle = $this->normalizedText($name);
        $tokens = collect(explode(' ', $needle))->filter()->values();

        if ($tokens->isEmpty()) {
            return collect();
        }

        $stylists = Stylist::query()
            ->where('clinic_id', $clinicId)
            ->where('is_internal', false)
            ->get();

        $scored = $stylists->map(function (Stylist $stylist) use ($needle, $tokens): array {
            $haystack = $this->normalizedText($stylist->name);
            $allTokensMatch = $tokens->every(fn (string $token): bool => str_contains($haystack, $token));
            similar_text($needle, $haystack, $similarity);

            return [
                'stylist' => $stylist,
                'score' => $allTokensMatch ? 100 : (int) round($similarity),
            ];
        })
            ->filter(fn (array $item): bool => $item['score'] >= 72)
            ->sortByDesc('score')
            ->values();

        $bestScore = (int) ($scored->first()['score'] ?? 0);

        return $scored
            ->filter(fn (array $item): bool => $item['score'] >= max(72, $bestScore - 8))
            ->take(3)
            ->map(fn (array $item): Stylist => $item['stylist'])
            ->values();
    }

    private function normalizedText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($value))) ?: '');
    }
}
