<?php

namespace App\Http\Controllers;

use App\Services\ClinicResolver;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GoogleCalendarController extends Controller
{
    public function connect(Request $request, GoogleCalendarService $calendar, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());

        $state = Str::random(40);
        $request->session()->put('google_calendar_oauth_state', $state);

        return redirect()->away($calendar->authUrl($state));
    }

    public function callback(Request $request, GoogleCalendarService $calendar, ClinicResolver $clinics): RedirectResponse
    {
        $expectedState = $request->session()->pull('google_calendar_oauth_state');

        if (! $expectedState || ! hash_equals($expectedState, (string) $request->query('state'))) {
            return redirect('/ajustes')->with('google_calendar_error', 'La conexion con Google Calendar no pudo validarse.');
        }

        $clinic = $clinics->currentOrCreate($request->user());

        if (! $request->query('code')) {
            return redirect('/ajustes')->with('google_calendar_error', 'Google no devolvio un codigo de autorizacion valido.');
        }

        try {
            $calendar->connectClinic($clinic, (string) $request->query('code'));
            $calendar->discoverCalendars($clinic->fresh());
        } catch (\Throwable $exception) {
            return redirect('/ajustes')->with('google_calendar_error', $exception->getMessage());
        }

        return redirect('/ajustes')->with(
            'google_calendar_status',
            'Google Calendar conectado correctamente. Elige ahora cómo quieres organizar las agendas antes de sincronizar.'
        );
    }

    public function organize(Request $request, GoogleCalendarService $calendar): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();

        if (! $clinic || ! $clinic->google_connected_at) {
            return redirect('/ajustes#google-calendar')->with('google_calendar_error', 'Primero conecta una cuenta de Google Calendar.');
        }

        $validated = $request->validate([
            'organization_mode' => ['required', Rule::in(['per_specialist', 'single', 'existing'])],
        ]);
        $mode = $validated['organization_mode'];
        $clinic->forceFill(['google_calendar_organization_mode' => $mode])->save();

        try {
            if ($mode === 'existing') {
                $detected = $calendar->discoverCalendars($clinic);

                return redirect('/ajustes#google-calendar')->with(
                    'google_calendar_status',
                    "Detectamos {$detected} calendario(s). Asígnalos a los especialistas y guarda los cambios."
                );
            }

            if ($mode === 'single') {
                $clinic->googleCalendarMappings()->update(['is_enabled' => false]);
                $result = $calendar->syncClinic($clinic->fresh());

                return redirect('/ajustes#google-calendar')->with(
                    'google_calendar_status',
                    "Usaremos el calendario principal del salón. Sincronización: {$result['exported']} enviadas y {$result['imported']} importadas."
                );
            }

            $created = $calendar->createCalendarsForStylists($clinic);
            $result = $calendar->syncClinic($clinic->fresh());

            return redirect('/ajustes#google-calendar')->with(
                'google_calendar_status',
                "Organización completada: {$created} calendario(s) creado(s). Sincronización: {$result['exported']} enviadas y {$result['imported']} importadas."
            );
        } catch (\Throwable $exception) {
            return redirect('/ajustes#google-calendar')->with('google_calendar_error', $exception->getMessage());
        }
    }

    public function disconnect(Request $request, GoogleCalendarService $calendar): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();

        if ($clinic) {
            $calendar->disconnectClinic($clinic);
        }

        return redirect('/ajustes')->with('google_calendar_status', 'Google Calendar fue desconectado.');
    }

    public function sync(Request $request, GoogleCalendarService $calendar): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();

        if (! $clinic) {
            return redirect('/ajustes')->with('google_calendar_error', 'No hay salon asociado al usuario.');
        }

        try {
            $result = $calendar->syncClinic($clinic);
        } catch (\Throwable $exception) {
            return redirect('/ajustes')->with('google_calendar_error', $exception->getMessage());
        }

        return redirect('/ajustes')->with(
            'google_calendar_status',
            "Sincronizacion completada: {$result['exported']} citas enviadas y {$result['imported']} citas importadas."
        );
    }

    public function detect(Request $request, GoogleCalendarService $calendar): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();

        if (! $clinic) {
            return redirect('/ajustes#google-calendar')->with('google_calendar_error', 'No hay salon asociado al usuario.');
        }

        try {
            $detected = $calendar->discoverCalendars($clinic);
        } catch (\Throwable $exception) {
            return redirect('/ajustes#google-calendar')->with('google_calendar_error', $exception->getMessage());
        }

        return redirect('/ajustes#google-calendar')->with(
            'google_calendar_status',
            "Detectamos {$detected} calendario(s). Ahora puedes asignarlos a los especialistas."
        );
    }

    public function updateMappings(Request $request): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();

        if (! $clinic) {
            return redirect('/ajustes#google-calendar')->with('google_calendar_error', 'No hay salon asociado al usuario.');
        }

        $mappings = $clinic->googleCalendarMappings()->get();
        $allowedStylistIds = $clinic->stylists()->where('is_internal', false)->pluck('id')->all();

        $validated = $request->validate([
            'calendars' => ['nullable', 'array'],
            'calendars.*.stylist_id' => ['nullable', 'integer', Rule::in($allowedStylistIds)],
            'calendars.*.enabled' => ['nullable', 'boolean'],
        ]);

        $submitted = collect($validated['calendars'] ?? []);

        foreach ($mappings as $mapping) {
            $data = $submitted->get((string) $mapping->id, []);
            $mapping->update([
                'stylist_id' => ! empty($data['stylist_id']) ? (int) $data['stylist_id'] : null,
                'is_enabled' => (bool) ($data['enabled'] ?? false),
            ]);
        }

        $clinic->forceFill(['google_calendar_organization_mode' => 'existing'])->save();

        return redirect('/ajustes#google-calendar')->with(
            'google_calendar_status',
            'Asignacion de calendarios guardada. Los eventos sin especialista se mostraran en el personal interno Google.'
        );
    }

}
