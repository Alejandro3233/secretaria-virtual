<?php

namespace App\Http\Controllers;

use App\Models\Stylist;
use App\Services\ClinicResolver;
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
                ->with('service')
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

    public function update(Request $request, Stylist $stylist): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic && $stylist->clinic_id === $clinic->id, 404);

        $stylist->update($this->validatedData($request, $clinic->id));

        return redirect('/personal')->with('staff_status', 'Personal actualizado correctamente.');
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
}
