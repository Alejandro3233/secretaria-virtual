<?php

namespace App\Http\Controllers;

use App\Models\Stylist;
use App\Models\StylistVacation;
use App\Services\ClinicResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                ->with(['service', 'vacations' => fn ($query) => $query->where('ends_on', '>=', now($clinic->localTimezone())->toDateString())->orderBy('starts_on')])
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
        $data = $this->validatedData($request, $clinic->id);

        $clinic->stylists()->create($data);

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

        $stylist->update($this->validatedData($request, $clinic->id));

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
            $stylist->appointments()->update(['stylist_id' => null]);
            $stylist->delete();
        });

        return redirect('/personal')->with('staff_status', 'Empleado eliminado correctamente. La agenda se actualizo y sus citas quedaron sin asignar.');
    }

    private function validatedData(Request $request, int $clinicId): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'work_days' => ['nullable', 'array'],
            'work_days.*' => ['string', 'in:'.implode(',', array_keys(self::WORK_DAYS))],
            'work_starts_at' => ['nullable', 'date_format:H:i'],
            'work_ends_at' => ['nullable', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if (! empty($data['service_id'])) {
            abort_unless(
                \App\Models\Service::query()
                    ->where('clinic_id', $clinicId)
                    ->whereKey($data['service_id'])
                    ->exists(),
                404
            );
        }

        $data['work_days'] = $data['work_days'] ?? [];
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
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
