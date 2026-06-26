<?php

namespace App\Http\Controllers;

use App\Services\ClinicResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NoraReminderController extends Controller
{
    public function index(Request $request, ClinicResolver $clinics): JsonResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());

        return response()->json([
            'reminders' => DB::table('nora_reminders')
                ->where('clinic_id', $clinic->id)
                ->whereNull('canceled_at')
                ->whereNull('delivered_at')
                ->where('due_at', '>', now())
                ->orderBy('due_at')
                ->get()
                ->map(fn ($reminder): array => $this->serializeReminder($reminder))
                ->values(),
        ]);
    }

    public function store(Request $request, ClinicResolver $clinics): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:255'],
            'due_at' => ['required', 'date', 'after:now', 'before_or_equal:'.now()->addDay()->toDateTimeString()],
        ]);
        $clinic = $clinics->currentOrCreate($request->user());
        $dueAt = Carbon::parse($data['due_at'])->utc();

        $id = DB::table('nora_reminders')->insertGetId([
            'clinic_id' => $clinic->id,
            'user_id' => $request->user()?->id,
            'message' => trim($data['message']),
            'due_at' => $dueAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reminder = DB::table('nora_reminders')->where('id', $id)->first();

        return response()->json([
            'reminder' => $this->serializeReminder($reminder),
        ], 201);
    }

    public function cancel(Request $request, ClinicResolver $clinics): JsonResponse
    {
        $data = $request->validate([
            'all' => ['sometimes', 'boolean'],
            'query' => ['nullable', 'string', 'max:255'],
        ]);
        $clinic = $clinics->currentOrCreate($request->user());
        $query = trim((string) ($data['query'] ?? ''));

        $reminders = DB::table('nora_reminders')
            ->where('clinic_id', $clinic->id)
            ->whereNull('canceled_at')
            ->whereNull('delivered_at')
            ->where('due_at', '>', now())
            ->orderBy('due_at')
            ->get();

        $cancelAll = (bool) ($data['all'] ?? false) || $query === '';
        $matches = $cancelAll
            ? $reminders
            : $reminders->filter(fn ($reminder): bool => str_contains($this->normalize($reminder->message), $this->normalize($query)));

        if ($matches->isNotEmpty()) {
            DB::table('nora_reminders')
                ->whereIn('id', $matches->pluck('id')->all())
                ->update([
                    'canceled_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        return response()->json([
            'canceled' => $matches
                ->map(fn ($reminder): array => $this->serializeReminder($reminder))
                ->values(),
        ]);
    }

    public function due(Request $request, ClinicResolver $clinics): JsonResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $now = now();
        $ids = DB::table('nora_reminders')
            ->where('clinic_id', $clinic->id)
            ->whereNull('canceled_at')
            ->whereNull('delivered_at')
            ->where('due_at', '<=', $now)
            ->orderBy('due_at')
            ->limit(5)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return response()->json(['reminders' => []]);
        }

        DB::table('nora_reminders')
            ->whereIn('id', $ids->all())
            ->whereNull('delivered_at')
            ->update([
                'delivered_at' => $now,
                'updated_at' => $now,
            ]);

        return response()->json([
            'reminders' => DB::table('nora_reminders')
                ->whereIn('id', $ids->all())
                ->orderBy('due_at')
                ->get()
                ->map(fn ($reminder): array => $this->serializeReminder($reminder))
                ->values(),
        ]);
    }

    private function serializeReminder(object $reminder): array
    {
        return [
            'id' => $reminder->id,
            'message' => $reminder->message,
            'due_at' => Carbon::parse($reminder->due_at)->toIso8601String(),
        ];
    }

    private function normalize(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return strtolower(trim($value));
    }
}
