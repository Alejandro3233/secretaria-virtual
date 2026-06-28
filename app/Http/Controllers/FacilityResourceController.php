<?php

namespace App\Http\Controllers;

use App\Models\FacilityResource;
use App\Services\ClinicResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FacilityResourceController extends Controller
{
    public function store(Request $request, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $data = $request->validate(['resource_name' => ['required', 'string', 'max:255'], 'capacity' => ['required', 'integer', 'min:1', 'max:999']]);
        $name = trim($data['resource_name']);
        if ($this->duplicateExists($clinic->id, $name)) {
            return redirect('/ajustes#recursos')
                ->withErrors(['resource_name' => 'Este recurso ya existe. Aumenta la cantidad en el recurso existente.'])
                ->withInput();
        }
        $clinic->facilityResources()->create(['name' => $name, 'capacity' => $data['capacity'], 'is_active' => true]);
        return redirect('/ajustes#recursos')->with('settings_status', 'Puesto o equipamiento agregado correctamente.');
    }

    public function update(Request $request, FacilityResource $resource): RedirectResponse
    {
        $this->authorizeResource($request, $resource);
        $data = $request->validate(['resource_name' => ['required', 'string', 'max:255'], 'capacity' => ['required', 'integer', 'min:1', 'max:999'], 'is_active' => ['nullable', 'boolean']]);
        $name = trim($data['resource_name']);
        if ($this->duplicateExists($resource->clinic_id, $name, $resource->id)) {
            return redirect('/ajustes#recursos')
                ->withErrors(['resource_name' => 'Ya existe otro recurso con este nombre. Aumenta su cantidad en lugar de duplicarlo.'])
                ->withInput();
        }
        $resource->update(['name' => $name, 'capacity' => $data['capacity'], 'is_active' => $request->boolean('is_active')]);
        return redirect('/ajustes#recursos')->with('settings_status', 'Capacidad actualizada correctamente.');
    }

    public function destroy(Request $request, FacilityResource $resource): RedirectResponse
    {
        $this->authorizeResource($request, $resource);
        $resource->services()->update(['facility_resource_id' => null, 'resource_units' => 1]);
        $resource->delete();
        return redirect('/ajustes#recursos')->with('settings_status', 'Puesto o equipamiento eliminado.');
    }

    public function assignServices(Request $request, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $data = $request->validate([
            'assignments' => ['nullable', 'array'],
            'assignments.*.resource_id' => ['nullable', 'integer'],
            'assignments.*.units' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);
        foreach ($clinic->services as $service) {
            $assignment = $data['assignments'][$service->id] ?? [];
            $resourceId = filled($assignment['resource_id'] ?? null) ? (int) $assignment['resource_id'] : null;
            abort_unless(! $resourceId || $clinic->facilityResources()->whereKey($resourceId)->exists(), 404);
            $service->update(['facility_resource_id' => $resourceId, 'resource_units' => max(1, (int) ($assignment['units'] ?? 1))]);
        }
        return redirect('/ajustes#recursos')->with('settings_status', 'Recursos asociados a los servicios correctamente.');
    }

    private function authorizeResource(Request $request, FacilityResource $resource): void
    {
        abort_unless($request->user()->primaryClinic()?->id === $resource->clinic_id, 404);
    }

    private function duplicateExists(int $clinicId, string $name, ?int $ignoreId = null): bool
    {
        return FacilityResource::query()
            ->where('clinic_id', $clinicId)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->exists();
    }
}
