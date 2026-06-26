<?php

namespace App\Http\Controllers;

use App\Models\Client;
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

        $body = $this->callMessage($client, $clinic->name);
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
            $this->twimlString($this->callMessage($client, $client->clinic?->name ?: 'tu salon'), $voice),
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

    private function matchingClients(int $clinicId, string $name)
    {
        $needle = $this->normalizedText($name);
        $tokens = collect(explode(' ', $needle))->filter()->values();

        if ($tokens->isEmpty()) {
            return collect();
        }

        $clients = Client::query()
            ->where('clinic_id', $clinicId)
            ->whereNotNull('phone')
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

        return "Hola {$firstName}, soy Nora, la asistente virtual de {$clinicName}. Te estamos llamando desde el salon.";
    }

    private function twimlString(string $message, array $voice): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><Response>'
            .'<Say language="'.htmlspecialchars($voice['language'], ENT_XML1).'" voice="'.htmlspecialchars($voice['voice'], ENT_XML1).'">'
            .htmlspecialchars($message, ENT_XML1)
            .'</Say>'
            .'</Response>';
    }

    private function appUrlIsPublic(): bool
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host)
            && ! in_array($host, ['127.0.0.1', 'localhost'], true)
            && ! str_ends_with($host, '.test')
            && ! str_ends_with($host, '.local');
    }

    private function recordNotification(Client $client, string $status, string $recipient, string $body, ?string $providerMessageId = null, ?string $error = null): void
    {
        DB::table('notifications')->insert([
            'clinic_id' => $client->clinic_id,
            'client_id' => $client->id,
            'appointment_id' => null,
            'channel' => 'voice',
            'event' => 'nora_client_call',
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

    private function recordCallLog(Client $client, string $from, string $to, string $status, ?string $callSid, string $body): void
    {
        DB::table('call_logs')->insert([
            'clinic_id' => $client->clinic_id,
            'client_id' => $client->id,
            'appointment_id' => null,
            'twilio_call_sid' => $callSid,
            'from_phone' => $from,
            'to_phone' => $to,
            'status' => $status,
            'intent' => 'nora_client_call',
            'transcript' => $body,
            'metadata' => json_encode(['source' => 'nora_console_voice'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
