<?php

namespace App\Http\Controllers;

use App\Services\GoogleTextToSpeechService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class GoogleTextToSpeechController extends Controller
{
    public function preview(Request $request, GoogleTextToSpeechService $tts): Response
    {
        $text = (string) $request->query(
            'text',
            'Hola, soy la secretaria virtual del salon. Puedo ayudarte a confirmar, cambiar o reservar una cita.'
        );
        $voice = (string) $request->query('voice', $request->user()?->primaryClinic()?->google_tts_voice ?: config('google.tts.voice'));

        $audio = $tts->synthesize($text, $voice);

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
        ])->save();

        $voice = $tts->voiceOptions()[$validated['voice']]['name'];

        return redirect('/ajustes')->with('google_tts_status', "Voz {$voice} activada para la secretaria.");
    }
}
