<?php

namespace App\Http\Controllers;

use App\Services\GoogleTextToSpeechService;
use App\Services\NoraLanguageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GoogleTextToSpeechController extends Controller
{
    public function preview(Request $request, GoogleTextToSpeechService $tts): Response
    {
        $text = (string) $request->query(
            'text',
            'Hola, soy la secretaria virtual del salon. Puedo ayudarte a confirmar, cambiar o reservar una cita.'
        );
        $voice = (string) $request->query(
            'voice',
            $request->user()?->primaryClinic()?->google_tts_voice ?: GoogleTextToSpeechService::TWILIO_VOICE_ID
        );

        if ($tts->isTwilioVoice($voice)) {
            return response('La Secretaria estandar se reproduce directamente dentro de la llamada. Usa el boton de prueba en ajustes para escuchar una previsualizacion aproximada.', 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        try {
            $audio = $tts->synthesize($text, $voice);
        } catch (\Throwable $exception) {
            return response($this->friendlyError($exception), 422, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        return response($audio, 200, [
            'Content-Type' => 'audio/mpeg',
            'Content-Disposition' => 'inline; filename="secretaria-virtual.mp3"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function activate(Request $request, GoogleTextToSpeechService $tts): RedirectResponse
    {
        $validated = $request->validate([
            'voice' => ['required', 'string'],
        ]);

        if (! $tts->validVoice($validated['voice'])) {
            return redirect('/ajustes')->with('google_tts_error', 'La voz seleccionada no esta disponible.');
        }

        $clinic = $request->user()->primaryClinic();

        if (! $clinic) {
            return redirect('/ajustes')->with('google_tts_error', 'No hay salon asociado al usuario.');
        }

        $clinic->forceFill([
            'google_tts_voice' => $validated['voice'],
            'notification_preferences' => array_merge($clinic->notification_preferences ?? [], [
                'nora_language' => 'es',
                'nora_language_source' => 'manual',
            ]),
        ])->save();

        $voice = $tts->voiceOptions()[$validated['voice']]['name'];

        return redirect('/ajustes#avanzadas')->with('google_tts_status', "Voz {$voice} activada para la secretaria.");
    }

    public function previewCall(Request $request, GoogleTextToSpeechService $tts, NoraLanguageService $languages): RedirectResponse
    {
        $validated = $request->validate([
            'voice' => ['required', 'string'],
        ]);

        if (! $tts->isTwilioVoice($validated['voice'])) {
            return redirect('/ajustes#avanzadas')->with('google_tts_error', 'La llamada de prueba solo esta disponible para voces Twilio.');
        }

        $user = $request->user();
        $clinic = $user->primaryClinic();
        $to = $this->normalizePhone($user->mobile_phone ?: $clinic?->phone);
        $from = $this->normalizePhone($clinic?->twilio_phone_number ?: config('services.twilio.from'));
        $accountSid = config('services.twilio.account_sid');
        $authToken = config('services.twilio.auth_token');

        if (! $to || ! $from || ! $accountSid || ! $authToken) {
            return redirect('/ajustes#avanzadas')->with('google_tts_error', 'Falta telefono de prueba o configuracion de Twilio.');
        }

        $voice = $tts->twilioSayAttributes($validated['voice']);
        $voiceName = $tts->voiceOptions()[$validated['voice']]['name'];
        $clinicName = trim((string) ($clinic?->name ?: 'tu salon'));
        $previewMessage = $languages->text($clinic, 'appointment', [
            'name' => '', 'clinic' => $clinicName, 'datetime' => $languages->dateTime(now()->addDay(), $clinic),
        ]);
        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            .'<Say language="'.htmlspecialchars($voice['language'], ENT_XML1).'" voice="'.htmlspecialchars($voice['voice'], ENT_XML1).'">'
            .htmlspecialchars($previewMessage, ENT_XML1)
            .'</Say>'
            .'</Response>';

        try {
            Http::asForm()
                ->withBasicAuth($accountSid, $authToken)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}/Calls.json", [
                    'To' => $to,
                    'From' => $from,
                    'Twiml' => $twiml,
                ])
                ->throw();
        } catch (\Throwable $exception) {
            return redirect('/ajustes#avanzadas')->with('google_tts_error', 'No se pudo iniciar la llamada de prueba: '.$exception->getMessage());
        }

        return redirect('/ajustes#avanzadas')->with('google_tts_status', 'Llamada de prueba enviada a '.$to.' con la voz '.$voiceName.'.');
    }

    private function friendlyError(\Throwable $exception): string
    {
        $message = $exception->getMessage();

        if (Str::contains($message, 'requires billing to be enabled')) {
            return 'Google Text-to-Speech respondio que el proyecto de Google Cloud necesita facturacion activa para generar esta voz.';
        }

        if (Str::contains($message, ['PERMISSION_DENIED', 'permission', '403'])) {
            return 'Google Text-to-Speech rechazo la solicitud. Revisa que la API este habilitada y que la cuenta de servicio tenga permisos.';
        }

        if (Str::contains($message, ['INVALID_ARGUMENT', 'voice', '400'])) {
            return 'Google Text-to-Speech no acepto esta voz. Puede que no este disponible para el idioma configurado.';
        }

        return 'No se pudo generar la prueba de voz: '.$message;
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $phone = trim($phone);

        if (str_starts_with($phone, '+')) {
            return preg_replace('/[^\d+]/', '', $phone);
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 10) {
            return '+1'.$digits;
        }

        if (strlen($digits) >= 11) {
            return '+'.$digits;
        }

        return null;
    }
}
