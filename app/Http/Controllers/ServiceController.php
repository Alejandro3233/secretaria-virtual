<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\ClinicResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request, ClinicResolver $clinics): View
    {
        $clinic = $clinics->currentOrCreate($request->user());

        return view('services.index', [
            'clinic' => $clinic,
            'services' => $clinic->services()->orderBy('name')->get(),
            'serviceTemplates' => $this->serviceTemplates(),
        ]);
    }

    public function store(Request $request, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());

        $clinic->services()->create($this->validatedData($request));

        return redirect('/personal/servicios')->with('service_status', 'Servicio agregado correctamente.');
    }

    public function seedTemplates(Request $request, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $created = 0;

        foreach ($this->serviceTemplates() as $template) {
            $service = $clinic->services()->firstOrCreate(
                ['name' => $template['name']],
                [
                    'duration_minutes' => $template['duration'],
                    'price_cents' => $template['price'],
                    'is_active' => true,
                ]
            );

            $created += $service->wasRecentlyCreated ? 1 : 0;
        }

        $message = $created > 0
            ? "Catalogo base cargado: {$created} servicios agregados."
            : 'El catalogo base ya estaba cargado.';

        return redirect('/personal/servicios')->with('service_status', $message);
    }

    public function update(Request $request, Service $service): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic && $service->clinic_id === $clinic->id, 404);

        $service->update($this->validatedData($request));

        return redirect('/personal/servicios')->with('service_status', 'Servicio actualizado correctamente.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => $data['name'],
            'duration_minutes' => $data['duration_minutes'],
            'price_cents' => filled($data['price'] ?? null) ? (int) round(((float) $data['price']) * 100) : null,
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function serviceTemplates(): array
    {
        return [
            ['name' => 'Corte de cabello', 'duration' => 45, 'price' => 3500],
            ['name' => 'Corte + blower', 'duration' => 60, 'price' => 5500],
            ['name' => 'Blower / peinado', 'duration' => 45, 'price' => 4000],
            ['name' => 'Color raiz', 'duration' => 90, 'price' => 7500],
            ['name' => 'Color completo', 'duration' => 150, 'price' => 13000],
            ['name' => 'Mechas / balayage', 'duration' => 180, 'price' => 18000],
            ['name' => 'Tratamiento hidratante', 'duration' => 60, 'price' => 6500],
            ['name' => 'Keratina', 'duration' => 180, 'price' => 22000],
            ['name' => 'Manicure regular', 'duration' => 45, 'price' => 3000],
            ['name' => 'Manicure gel', 'duration' => 60, 'price' => 4500],
            ['name' => 'Pedicure', 'duration' => 60, 'price' => 5000],
            ['name' => 'Unas acrilicas', 'duration' => 120, 'price' => 9000],
            ['name' => 'Depilacion cejas', 'duration' => 20, 'price' => 1800],
            ['name' => 'Maquillaje social', 'duration' => 75, 'price' => 8500],
        ];
    }
}
