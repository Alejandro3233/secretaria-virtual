<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceAddon;
use App\Services\ClinicResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request, ClinicResolver $clinics): View
    {
        $clinic = $clinics->currentOrCreate($request->user());

        return view('services.index', [
            'clinic' => $clinic,
            'services' => $clinic->services()->with('addons')->orderBy('name')->get(),
            'serviceTemplates' => $this->serviceTemplates(),
            'addonSuggestions' => $this->addonSuggestions(),
            'serviceSampleImages' => $this->serviceSampleImages(),
        ]);
    }

    public function store(Request $request, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());

        $service = $clinic->services()->create($this->validatedData($request));
        $this->storeImage($request, $service);

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
        $this->storeImage($request, $service);

        return redirect('/personal/servicios')->with('service_status', 'Servicio actualizado correctamente.');
    }

    public function storeAddon(Request $request, Service $service): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic && $service->clinic_id === $clinic->id, 404);

        $data = $request->validate([
            'addon_name' => ['required', 'string', 'max:255'],
            'addon_price' => ['required', 'numeric', 'min:0', 'max:99999'],
        ]);

        $service->addons()->create([
            'name' => $data['addon_name'],
            'price_cents' => (int) round(((float) $data['addon_price']) * 100),
            'is_active' => true,
        ]);

        return redirect('/personal/servicios?extras='.$service->id.'#service-'.$service->id)->with('service_status', 'Extra agregado correctamente.');
    }

    public function destroyAddon(Request $request, Service $service, ServiceAddon $addon): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic && $service->clinic_id === $clinic->id && $addon->service_id === $service->id, 404);
        $addon->delete();

        return redirect('/personal/servicios?extras='.$service->id.'#service-'.$service->id)->with('service_status', 'Extra eliminado correctamente.');
    }

    public function updateAddon(Request $request, Service $service, ServiceAddon $addon): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic && $service->clinic_id === $clinic->id && $addon->service_id === $service->id, 404);
        $data = $request->validate([
            'addon_name' => ['required', 'string', 'max:255'],
            'addon_price' => ['required', 'numeric', 'min:0', 'max:99999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $addon->update([
            'name' => $data['addon_name'],
            'price_cents' => (int) round(((float) $data['addon_price']) * 100),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect('/personal/servicios?extras='.$service->id.'#service-'.$service->id)->with('service_status', 'Extra actualizado correctamente.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'sample_image' => ['nullable', 'string', 'in:'.implode(',', array_values($this->serviceSampleImages()))],
        ]);

        return [
            'name' => $data['name'],
            'duration_minutes' => $data['duration_minutes'],
            'price_cents' => filled($data['price'] ?? null) ? (int) round(((float) $data['price']) * 100) : null,
            'is_active' => $request->boolean('is_active', true),
        ];
    }

    private function storeImage(Request $request, Service $service): void
    {
        if (! $request->hasFile('image') && ! $request->filled('sample_image')) {
            return;
        }

        $oldPath = $service->image_path;
        $path = $request->hasFile('image')
            ? $request->file('image')->store('service-images/'.$service->clinic_id, 'public')
            : 'sample:'.$request->string('sample_image');
        $service->update(['image_path' => $path]);

        if ($oldPath && ! str_starts_with($oldPath, 'sample:')) {
            Storage::disk('public')->delete($oldPath);
        }
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

    private function addonSuggestions(): array
    {
        return [
            'Corte de cabello' => [['Lavado especial', 5], ['Arreglo de barba', 8], ['Masaje capilar', 5]],
            'Corte + blower' => [['Tratamiento hidratante', 10], ['Plancha', 5], ['Masaje capilar', 5]],
            'Blower / peinado' => [['Plancha', 5], ['Ondas', 10], ['Lavado especial', 5]],
            'Color raiz' => [['Matiz', 15], ['Tratamiento protector', 10], ['Secado y peinado', 10]],
            'Color completo' => [['Cabello extra largo', 20], ['Matiz', 15], ['Tratamiento protector', 10]],
            'Mechas / balayage' => [['Matiz', 20], ['Tratamiento Olaplex', 25], ['Cabello extra largo', 30]],
            'Tratamiento hidratante' => [['Ampolla intensiva', 8], ['Masaje capilar', 5], ['Secado y peinado', 10]],
            'Keratina' => [['Cabello extra largo', 30], ['Lavado especial', 10], ['Corte de puntas', 8]],
            'Manicure regular' => [['Corte de cuticula', 1], ['Retirada de esmalte', 3], ['Diseno especial', 5]],
            'Manicure gel' => [['Corte de cuticula', 1], ['Retirada de gel', 5], ['Diseno especial', 5]],
            'Pedicure' => [['Retirada de gel', 5], ['Tratamiento de durezas', 8], ['Francesa', 4]],
            'Unas acrilicas' => [['Retirada de acrilico', 10], ['Largo extra', 8], ['Diseno especial', 5]],
            'Depilacion cejas' => [['Tinte de cejas', 5], ['Depilacion de labio', 3], ['Diseno de cejas', 5]],
            'Maquillaje social' => [['Pestanas postizas', 10], ['Productos premium', 15], ['Retoque de peinado', 10]],
            '*' => [['Servicio adicional', 5], ['Tratamiento premium', 10], ['Tiempo extra', 5]],
        ];
    }

    private function serviceSampleImages(): array
    {
        return [
            'Corte de cabello' => 'corte-cabello.png', 'Corte + blower' => 'corte-blower.png',
            'Blower / peinado' => 'blower-peinado.png', 'Color raiz' => 'color-raiz.png',
            'Color completo' => 'color-completo.png', 'Mechas / balayage' => 'mechas-balayage.png',
            'Tratamiento hidratante' => 'tratamiento-hidratante.png', 'Keratina' => 'keratina.png',
            'Manicure regular' => 'manicure-regular.png', 'Manicure gel' => 'manicure-gel.png',
            'Pedicure' => 'pedicure.png', 'Unas acrilicas' => 'unas-acrilicas.png',
            'Depilacion cejas' => 'depilacion-cejas.png', 'Maquillaje social' => 'maquillaje-social.png',
        ];
    }
}
