<?php

namespace App\Http\Controllers;

use App\Services\TwilioPhoneNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TwilioPhoneNumberController extends Controller
{
    public function assign(Request $request, TwilioPhoneNumberService $numbers): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();

        if (! $clinic) {
            return redirect('/ajustes#numero-asignado')->with('twilio_number_error', 'No hay salon asociado al usuario.');
        }

        $clinic = $numbers->assignToClinic($clinic, true);

        if ($clinic->twilio_number_status !== 'active') {
            return redirect('/ajustes#numero-asignado')->with('twilio_number_error', $clinic->twilio_number_error ?: 'No se pudo asignar el numero.');
        }

        return redirect('/ajustes#numero-asignado')->with('twilio_number_status', 'Numero Twilio asignado correctamente.');
    }

    public function incoming(Request $request): Response
    {
        $to = (string) $request->input('To');

        $message = 'Hola, gracias por llamar. La secretaria virtual esta configurando esta linea.';

        if ($to) {
            $message .= ' En unos momentos podra gestionar citas automaticamente.';
        }

        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response><Say language="es-US" voice="alice">'
            .htmlspecialchars($message, ENT_XML1)
            .'</Say></Response>';

        return response($twiml, 200, ['Content-Type' => 'text/xml']);
    }
}
