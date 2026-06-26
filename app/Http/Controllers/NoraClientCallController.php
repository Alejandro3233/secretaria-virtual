<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Services\AppointmentReminderCallService;
use App\Services\ClinicResolver;
use App\Services\GoogleTextToSpeechService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class NoraClientCallController extends Controller
{
    public function store(Request $request, ClinicResolver $clinics, GoogleTextToSpeechService $voices): JsonResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $matches = $this->matchingClients($clinic->id, (string) $data['name']);

        if ($matches->isEmpty()) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'No encontre un cliente llamado '.trim((string) $data['name']).'.',
            ], 404);
        }

        if ($matches->count() > 1) {
            return response()->json([
                'status' => 'ambiguous',
                'message' => 'Encontre varios clientes parecidos: '.$matches->map(fn (Client $client): string => $this->clientName($client))->join(', ').'.',
                'clients' => $matches->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'name' => $this->clientName($client),
                    'phone' => $client->phone,
                ])->values(),
            ], 409);
        }

        $client = $matches->first();
        $to = $this->normalizePhone($client->phone);
        $from = $this->normalizePhone($clinic->twilio_phone_number ?: config('services.twilio.from'));

        if (! $to) {
            return response()->json([
                'status' => 'no_phone',
                'message' => $this->clientName($client).' no tiene un telefono valido registrado.',
            ], 422);
        }

        if (! $from || ! config('services.twilio.account_sid') || ! config('services.twilio.auth_token')) {
            return response()->json([
                'status' => 'missing_twilio',
                'message' => 'Falta configurar Twilio o el numero de salida del salon.',
            ], 422);
        }

        $client->loadMissing('clinic');
        $body = $this->callMessage($client, $clinic->name);
        $twilioPayload = [
            'To' => $to,
            'From' => $from,
            'Twiml' => $this->clientCallTwiml($client, $clinic->name, $voices->twilioSayAttributes($clinic->google_tts_voice)),
        ];

        if ($this->appUrlIsPublic()) {
            $twilioPayload = array_merge($twilioPayload, [
                'StatusCallback' => route('twilio.voice.client-call-status'),
                'StatusCallbackMethod' => 'POST',
                'StatusCallbackEvent[0]' => 'initiated',
                'StatusCallbackEvent[1]' => 'ringing',
                'StatusCallbackEvent[2]' => 'answered',
                'StatusCallbackEvent[3]' => 'completed',
            ]);
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth(config('services.twilio.account_sid'), config('services.twilio.auth_token'))
                ->post('https://api.twilio.com/2010-04-01/Accounts/'.config('services.twilio.account_sid').'/Calls.json', $twilioPayload);

            if ($response->failed()) {
                throw new RuntimeException($response->json('message') ?: 'Twilio no pudo crear la llamada.');
            }

            $sid = (string) $response->json('sid');
            $this->recordNotification($client, 'queued', $to, $body, $sid);
            $this->recordCallLog($client, $from, $to, 'queued', $sid, $body);

            return response()->json([
                'status' => 'queued',
                'message' => 'Listo. Estoy llamando a '.$this->clientName($client).'.',
                'client' => $this->clientName($client),
                'phone' => $to,
            ]);
        } catch (\Throwable $exception) {
            $this->recordNotification($client, 'failed', $to, $body, null, $exception->getMessage());

            return response()->json([
                'status' => 'failed',
                'message' => 'No pude iniciar la llamada: '.$exception->getMessage(),
            ], 422);
        }
    }

    public function twiml(Client $client, string $token, GoogleTextToSpeechService $voices): Response
    {
        abort_unless(hash_equals($this->tokenFor($client), $token), 403);
        $client->loadMissing('clinic');
        $voice = $voices->twilioSayAttributes($client->clinic?->google_tts_voice);

        return response(
            $this->clientCallTwiml($client, $client->clinic?->name ?: 'tu salon', $voice),
            200,
            ['Content-Type' => 'text/xml'],
        );
    }

    public function status(Request $request): Response
    {
        $callSid = (string) $request->input('CallSid');
        $status = (string) $request->input('CallStatus') ?: 'callback';

        if ($callSid !== '') {
            DB::table('notifications')
                ->where('provider_message_id', $callSid)
                ->where('event', 'nora_client_call')
                ->update([
                    'status' => $status,
                    'sent_at' => in_array($status, ['answered', 'completed'], true) ? now() : DB::raw('sent_at'),
                    'updated_at' => now(),
                ]);

            DB::table('call_logs')
                ->where('twilio_call_sid', $callSid)
                ->where('intent', 'nora_client_call')
                ->update([
                    'status' => $status,
                    'metadata' => json_encode($request->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now(),
                ]);
        }

        return response('OK');
    }

    public function remindClientAppointment(Request $request, ClinicResolver $clinics, AppointmentReminderCallService $reminders): JsonResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $name = trim((string) $data['name']);
        $matches = $this->matchingClients($clinic->id, $name);

        if ($matches->isEmpty()) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'No encontre un cliente llamado '.$name.'.',
            ], 404);
        }

        if ($matches->count() > 1) {
            return response()->json([
                'status' => 'ambiguous',
                'message' => 'Encontre varios clientes parecidos: '.$matches->map(fn (Client $client): string => $this->clientName($client))->join(', ').'.',
                'clients' => $matches->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'name' => $this->clientName($client),
                    'phone' => $client->phone,
                ])->values(),
            ], 409);
        }

        $client = $matches->first();
        $appointment = Appointment::query()
            ->with(['clinic', 'client'])
            ->where('clinic_id', $clinic->id)
            ->where('client_id', $client->id)
            ->where('starts_at', '>=', now())
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->orderBy('starts_at')
            ->first();

        if (! $appointment) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'No encontre una proxima cita para '.$this->clientName($client).'.',
            ], 404);
        }

        $activeCall = DB::table('notifications')
            ->where('appointment_id', $appointment->id)
            ->where('event', AppointmentReminderCallService::EVENT)
            ->where(function ($query): void {
                $query->where(function ($pending): void {
                    $pending->whereIn('status', ['queued', 'initiated', 'ringing'])
                        ->where('created_at', '>=', now()->subMinutes(2));
                })->orWhere(function ($connected): void {
                    $connected->where('status', 'in-progress')
                        ->where('created_at', '>=', now()->subMinutes(10));
                });
            })
            ->exists();

        if ($activeCall) {
            return response()->json([
                'status' => 'active_call',
                'message' => 'Nora ya esta llamando o tiene esta llamada de recordatorio en proceso.',
            ], 409);
        }

        $sid = $reminders->call($appointment);

        if (! $sid) {
            return response()->json([
                'status' => 'failed',
                'message' => 'No pude iniciar el recordatorio. Revisa el telefono del cliente y la configuracion de Twilio.',
            ], 422);
        }

        return response()->json([
            'status' => 'queued',
            'message' => 'Listo. Estoy recordandole su cita a '.$this->clientName($client).'.',
            'client' => $this->clientName($client),
            'appointment_id' => $appointment->id,
        ]);
    }

    public function storeClientNote(Request $request, ClinicResolver $clinics): JsonResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'note' => ['required', 'string', 'max:1000'],
        ]);

        $name = trim((string) $data['name']);
        $note = trim((string) $data['note']);
        $matches = $this->matchingClients($clinic->id, $name, false);

        if ($matches->isEmpty()) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'No encontre un cliente llamado '.$name.'.',
            ], 404);
        }

        if ($matches->count() > 1) {
            return response()->json([
                'status' => 'ambiguous',
                'message' => 'Encontre varios clientes parecidos: '.$matches->map(fn (Client $client): string => $this->clientName($client))->join(', ').'.',
                'clients' => $matches->map(fn (Client $client): array => [
                    'id' => $client->id,
                    'name' => $this->clientName($client),
                    'phone' => $client->phone,
                ])->values(),
            ], 409);
        }

        $client = $matches->first();
        $existingNotes = trim((string) $client->notes);
        $datedNote = now($clinic->localTimezone())->format('d/m/Y H:i').' - '.$note;
        $client->update([
            'notes' => $existingNotes !== '' ? $existingNotes."\n".$datedNote : $datedNote,
        ]);

        return response()->json([
            'status' => 'saved',
            'message' => 'Listo. Guarde la nota en el cliente '.$this->clientName($client).'.',
            'client' => $this->clientName($client),
            'note' => $note,
        ]);
    }

    public function notifyNextAppointmentDelay(Request $request, ClinicResolver $clinics, GoogleTextToSpeechService $voices): JsonResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:240'],
        ]);

        $appointment = Appointment::query()
            ->with('client')
            ->where('clinic_id', $clinic->id)
            ->where('starts_at', '>=', now())
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->orderBy('starts_at')
            ->first();

        if (! $appointment || ! $appointment->client) {
            return response()->json([
                'status' => 'not_found',
                'message' => 'No encontre una proxima cita para avisar.',
            ], 404);
        }

        $client = $appointment->client;
        $to = $this->normalizePhone($client->phone);
        $from = $this->normalizePhone($clinic->twilio_phone_number ?: config('services.twilio.from'));

        if (! $to) {
            return response()->json([
                'status' => 'no_phone',
                'message' => $this->clientName($client).' no tiene un telefono valido registrado.',
            ], 422);
        }

        if (! $from || ! config('services.twilio.account_sid') || ! config('services.twilio.auth_token')) {
            return response()->json([
                'status' => 'missing_twilio',
                'message' => 'Falta configurar Twilio o el numero de salida del salon.',
            ], 422);
        }

        $minutes = (int) $data['minutes'];
        $body = $this->delayMessage($client, $clinic->name, $minutes);
        $twilioPayload = [
            'To' => $to,
            'From' => $from,
            'Twiml' => $this->twimlString($body, $voices->twilioSayAttributes($clinic->google_tts_voice)),
        ];

        if ($this->appUrlIsPublic()) {
            $twilioPayload = array_merge($twilioPayload, [
                'StatusCallback' => route('twilio.voice.client-call-status'),
                'StatusCallbackMethod' => 'POST',
                'StatusCallbackEvent[0]' => 'initiated',
                'StatusCallbackEvent[1]' => 'ringing',
                'StatusCallbackEvent[2]' => 'answered',
                'StatusCallbackEvent[3]' => 'completed',
            ]);
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth(config('services.twilio.account_sid'), config('services.twilio.auth_token'))
                ->post('https://api.twilio.com/2010-04-01/Accounts/'.config('services.twilio.account_sid').'/Calls.json', $twilioPayload);

            if ($response->failed()) {
                throw new RuntimeException($response->json('message') ?: 'Twilio no pudo crear la llamada.');
            }

            $sid = (string) $response->json('sid');
            $this->recordNotification($client, 'queued', $to, $body, $sid, null, 'nora_delay_call', $appointment->id);
            $this->recordCallLog($client, $from, $to, 'queued', $sid, $body, 'nora_delay_call', $appointment->id);

            return response()->json([
                'status' => 'queued',
                'message' => 'Listo. Estoy avisando a '.$this->clientName($client).' que vamos retrasados '.$minutes.' minutos.',
                'client' => $this->clientName($client),
                'minutes' => $minutes,
                'appointment_id' => $appointment->id,
                'phone' => $to,
            ]);
        } catch (\Throwable $exception) {
            $this->recordNotification($client, 'failed', $to, $body, null, $exception->getMessage(), 'nora_delay_call', $appointment->id);

            return response()->json([
                'status' => 'failed',
                'message' => 'No pude iniciar la llamada: '.$exception->getMessage(),
            ], 422);
        }
    }

    private function matchingClients(int $clinicId, string $name, bool $requirePhone = true)
    {
        $needle = $this->normalizedText($name);
        $tokens = collect(explode(' ', $needle))->filter()->values();

        if ($tokens->isEmpty()) {
            return collect();
        }

        $clients = Client::query()
            ->where('clinic_id', $clinicId)
            ->when($requirePhone, fn ($query) => $query->whereNotNull('phone'))
            ->get();

        $scored = $clients->map(function (Client $client) use ($needle, $tokens): array {
            $haystack = $this->normalizedText($this->clientName($client));
            $allTokensMatch = $tokens->every(fn (string $token): bool => str_contains($haystack, $token));
            similar_text($needle, $haystack, $similarity);

            return [
                'client' => $client,
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
            ->map(fn (array $item): Client => $item['client'])
            ->values();
    }

    private function clientName(Client $client): string
    {
        return trim($client->first_name.' '.$client->last_name) ?: 'Cliente';
    }

    private function normalizedText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', Str::lower(Str::ascii($value))) ?: '');
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (! $phone || str_starts_with($phone, 'google:')) {
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

    private function tokenFor(Client $client): string
    {
        return hash_hmac('sha256', $client->id.'|'.$client->clinic_id.'|nora-client-call', config('app.key'));
    }

    private function callMessage(Client $client, string $clinicName): string
    {
        $firstName = trim((string) $client->first_name) ?: 'cliente';

        return "Hola {$firstName}, soy Nora, la asistente virtual de {$clinicName}. Te estamos llamando desde el salon. Un momento, te comunico.";
    }

    private function delayMessage(Client $client, string $clinicName, int $minutes): string
    {
        $firstName = trim((string) $client->first_name) ?: 'cliente';

        return "Hola {$firstName}, soy Nora, la asistente virtual de {$clinicName}. Te llamamos para avisarte que vamos con un retraso aproximado de {$minutes} minutos para tu cita. Gracias por tu paciencia.";
    }

    private function twimlString(string $message, array $voice): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Response>'
            .'<Say language="'.htmlspecialchars($voice['language'], ENT_XML1).'" voice="'.htmlspecialchars($voice['voice'], ENT_XML1).'">'
            .htmlspecialchars($message, ENT_XML1)
            .'</Say>'
            .'</Response>';
    }

    private function clientCallTwiml(Client $client, string $clinicName, array $voice): string
    {
        $twiml = '<?xml version="1.0" encoding="UTF-8"?><Response>'
            .'<Say language="'.htmlspecialchars($voice['language'], ENT_XML1).'" voice="'.htmlspecialchars($voice['voice'], ENT_XML1).'">'
            .htmlspecialchars($this->callMessage($client, $clinicName), ENT_XML1)
            .'</Say>';

        if ($this->browserVoiceConfigured() && $client->clinic) {
            $twiml .= '<Dial answerOnBridge="true" timeout="30">'
                .'<Client>'
                .'<Identity>'.htmlspecialchars($this->browserIdentity($client->clinic), ENT_XML1).'</Identity>'
                .'<Parameter name="callerName" value="'.htmlspecialchars($this->clientName($client), ENT_XML1).'" />'
                .'<Parameter name="callerPhone" value="'.htmlspecialchars((string) $client->phone, ENT_XML1).'" />'
                .'</Client>'
                .'</Dial>'
                .'<Say language="'.htmlspecialchars($voice['language'], ENT_XML1).'" voice="'.htmlspecialchars($voice['voice'], ENT_XML1).'">'
                .htmlspecialchars('No pude comunicarte con el salon ahora mismo. Gracias.', ENT_XML1)
                .'</Say>';
        }

        return $twiml.'</Response>';
    }

    private function browserVoiceConfigured(): bool
    {
        return collect([
            config('services.twilio.account_sid'),
            config('services.twilio.api_key_sid'),
            config('services.twilio.api_key_secret'),
            config('services.twilio.twiml_app_sid'),
        ])->every(fn ($value) => is_string($value) && $value !== '');
    }

    private function browserIdentity(Clinic $clinic): string
    {
        return 'clinic-'.$clinic->id;
    }

    private function appUrlIsPublic(): bool
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host)
            && ! in_array($host, ['127.0.0.1', 'localhost'], true)
            && ! str_ends_with($host, '.test')
            && ! str_ends_with($host, '.local');
    }

    private function recordNotification(Client $client, string $status, string $recipient, string $body, ?string $providerMessageId = null, ?string $error = null, string $event = 'nora_client_call', ?int $appointmentId = null): void
    {
        DB::table('notifications')->insert([
            'clinic_id' => $client->clinic_id,
            'client_id' => $client->id,
            'appointment_id' => $appointmentId,
            'channel' => 'voice',
            'event' => $event,
            'recipient' => $recipient,
            'status' => $status,
            'provider_message_id' => $providerMessageId,
            'body' => $body,
            'error' => $error,
            'sent_at' => in_array($status, ['sent', 'queued'], true) ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function recordCallLog(Client $client, string $from, string $to, string $status, ?string $callSid, string $body, string $intent = 'nora_client_call', ?int $appointmentId = null): void
    {
        DB::table('call_logs')->insert([
            'clinic_id' => $client->clinic_id,
            'client_id' => $client->id,
            'appointment_id' => $appointmentId,
            'twilio_call_sid' => $callSid,
            'from_phone' => $from,
            'to_phone' => $to,
            'status' => $status,
            'intent' => $intent,
            'transcript' => $body,
            'metadata' => json_encode(['source' => 'nora_console_voice'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
