<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\DailyBriefing;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DailyBriefingService
{
    public function __construct(private readonly ScheduleOptimizationService $optimizer)
    {
    }

    public function forToday(Clinic $clinic, User $user): DailyBriefing
    {
        $timezone = $clinic->localTimezone();
        $today = now($timezone)->startOfDay();
        $briefing = DailyBriefing::query()
            ->where('clinic_id', $clinic->id)
            ->where('user_id', $user->id)
            ->whereDate('briefing_date', $today->toDateString())
            ->first();

        if (! $briefing) {
            $briefing = new DailyBriefing([
                'clinic_id' => $clinic->id,
                'user_id' => $user->id,
                'briefing_date' => $today->toDateString(),
            ]);
        }

        if (! $briefing->played_at) {
            $briefing->message = $this->buildMessage($clinic, $today);
            $briefing->save();
        }

        return $briefing;
    }

    public function markPlayed(Clinic $clinic, User $user): void
    {
        DailyBriefing::query()
            ->where('clinic_id', $clinic->id)
            ->where('user_id', $user->id)
            ->whereDate('briefing_date', now($clinic->localTimezone())->toDateString())
            ->whereNull('played_at')
            ->update(['played_at' => now(), 'updated_at' => now()]);
    }

    private function buildMessage(Clinic $clinic, Carbon $today): string
    {
        $timezone = $clinic->localTimezone();
        $start = $today->copy()->timezone(config('app.timezone'));
        $end = $today->copy()->endOfDay()->timezone(config('app.timezone'));
        $appointments = Appointment::query()
            ->with(['client', 'service', 'stylist'])
            ->where('clinic_id', $clinic->id)
            ->whereBetween('starts_at', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->orderBy('starts_at')
            ->get()
            ->each(function (Appointment $appointment) use ($timezone): void {
                $appointment->starts_at = $appointment->starts_at->copy()->timezone($timezone);
                $appointment->ends_at = $appointment->ends_at?->copy()->timezone($timezone);
            });

        $hour = now($timezone)->hour;
        $greeting = $hour < 12 ? 'Buenos días' : ($hour < 20 ? 'Buenas tardes' : 'Buenas noches');
        $parts = ["{$greeting}. Soy Nora. Este es el resumen de hoy para {$clinic->name}."];

        if ($appointments->isEmpty()) {
            $parts[] = 'Hoy no hay citas programadas.';
        } else {
            $parts[] = 'Tenemos '.$appointments->count().' '.($appointments->count() === 1 ? 'cita programada' : 'citas programadas').'.';
            $next = $appointments->first(fn (Appointment $appointment) => $appointment->starts_at->greaterThanOrEqualTo(now($timezone)));
            if ($next) {
                $clientName = trim(($next->client?->first_name ?? '').' '.($next->client?->last_name ?? '')) ?: 'un cliente';
                $parts[] = 'La próxima es a las '.$next->starts_at->format('g:i A').' con '.$clientName.'.';
            }
        }

        if ($risk = $this->highestRiskClient($clinic, $appointments)) {
            $parts[] = $risk;
        }

        try {
            $optimization = $this->optimizer->suggestion($clinic, $today, $appointments);
        } catch (\Throwable) {
            $optimization = null;
        }

        if ($optimization) {
            $client = $optimization['appointment']->client;
            $clientName = trim(($client?->first_name ?? '').' '.($client?->last_name ?? '')) ?: 'un cliente';
            $parts[] = 'Podemos completar la agenda más temprano proponiendo a '.$clientName.' mover su cita de las '
                .$optimization['current_start']->format('g:i A').' a las '.$optimization['proposed_start']->format('g:i A').'.';
        }

        $vipCount = $appointments
            ->filter(fn (Appointment $appointment) => (int) ($appointment->client?->loyalty_level ?? 0) === 2)
            ->pluck('client_id')
            ->filter()
            ->unique()
            ->count();
        if ($vipCount > 0) {
            $parts[] = $vipCount === 1 ? 'Hoy recibimos a un cliente VIP.' : "Hoy recibimos a {$vipCount} clientes VIP.";
        }

        $expectedRevenue = (int) $appointments->sum(fn (Appointment $appointment) => (int) ($appointment->service?->price_cents ?? 0));
        if ($expectedRevenue > 0) {
            $parts[] = 'Los ingresos previstos del día son '.number_format($expectedRevenue / 100, 2).' dólares.';
        }

        $missingStylist = $appointments->whereNull('stylist_id')->count();
        if ($missingStylist > 0) {
            $parts[] = "Hay {$missingStylist} cita(s) sin profesional asignado.";
        }

        $lowStock = InventoryItem::query()
            ->where('clinic_id', $clinic->id)
            ->where('is_active', true)
            ->whereColumn('current_stock', '<=', 'minimum_stock')
            ->count();
        if ($lowStock > 0) {
            $parts[] = "Manager muestra {$lowStock} producto(s) que necesitan reposición.";
        }

        $parts[] = 'Que tengas un excelente día.';

        return implode(' ', array_slice($parts, 0, 8));
    }

    private function highestRiskClient(Clinic $clinic, Collection $todayAppointments): ?string
    {
        $clientIds = $todayAppointments->pluck('client_id')->filter()->unique();
        if ($clientIds->isEmpty()) {
            return null;
        }

        $cancelled = Appointment::query()
            ->with('client')
            ->where('clinic_id', $clinic->id)
            ->whereIn('client_id', $clientIds)
            ->whereIn('status', ['cancelled', 'canceled'])
            ->get();

        $ranked = $cancelled->groupBy('client_id')->map(function (Collection $items): array {
            $lateCancellations = $items->filter(function (Appointment $appointment): bool {
                $responseAt = DB::table('notifications')
                    ->where('appointment_id', $appointment->id)
                    ->where('event', 'appointment_client_response')
                    ->where('body', 'cancel')
                    ->max('created_at');
                $cancelledAt = Carbon::parse($responseAt ?: $appointment->updated_at);

                return $cancelledAt->diffInHours($appointment->starts_at, false) < 6;
            })->count();

            return [
                'client' => $items->first()->client,
                'total' => $items->count(),
                'late' => $lateCancellations,
                'score' => ($lateCancellations * 10) + $items->count(),
            ];
        })->sortByDesc('score')->first();

        if (! $ranked || ($ranked['late'] === 0 && $ranked['total'] < 2)) {
            return null;
        }

        $name = trim(($ranked['client']?->first_name ?? '').' '.($ranked['client']?->last_name ?? '')) ?: 'Un cliente de hoy';
        $risk = $ranked['late'] > 0 ? 'alto' : 'medio';

        return "Atención: {$name} tiene riesgo {$risk} de cancelación y registra {$ranked['total']} cancelación(es) anteriores.";
    }
}
