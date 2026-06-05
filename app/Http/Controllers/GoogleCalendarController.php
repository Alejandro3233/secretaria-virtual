<?php

namespace App\Http\Controllers;

use App\Services\ClinicResolver;
use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            $result = $calendar->syncClinic($clinic->fresh());
        } catch (\Throwable $exception) {
            return redirect('/ajustes')->with('google_calendar_error', $exception->getMessage());
        }

        return redirect('/ajustes')->with(
            'google_calendar_status',
            "Google Calendar conectado correctamente. Sincronizacion inicial: {$result['exported']} citas enviadas y {$result['imported']} citas importadas."
        );
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

}
