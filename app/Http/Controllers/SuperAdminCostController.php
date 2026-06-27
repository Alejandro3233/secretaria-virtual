<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SuperAdminCostController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->is_super_admin, 403);

        $selectedMonth = $this->selectedMonth($request);
        $monthStart = $selectedMonth->copy()->startOfMonth();
        $monthEnd = $selectedMonth->copy()->endOfMonth();
        $today = now();
        $dayStart = $today->copy()->startOfDay();
        $dayEnd = $today->copy()->endOfDay();
        $machineMonthlyUsd = $this->cost('machine_monthly_usd');
        $activeClinics = max(1, Clinic::query()->count());
        $unitCosts = [
            'sms' => $this->cost('sms_usd'),
            'call' => $this->cost('call_usd'),
            'email' => $this->cost('email_usd'),
            'machine_day' => $machineMonthlyUsd / max(1, $selectedMonth->daysInMonth),
            'machine_month' => $machineMonthlyUsd,
        ];

        $monthUsage = $this->usageFor($monthStart, $monthEnd);
        $dayUsage = $this->usageFor($dayStart, $dayEnd);
        $monthShares = $this->usageShares($monthUsage, $activeClinics);
        $dayShares = $this->usageShares($dayUsage, $activeClinics);

        $clinics = Clinic::query()
            ->orderBy('name')
            ->get()
            ->map(function (Clinic $clinic) use ($monthStart, $monthEnd, $dayStart, $dayEnd, $unitCosts, $monthUsage, $dayUsage, $monthShares, $dayShares): array {
                $counts = [
                    'day' => $this->countsFor($clinic, $dayStart, $dayEnd),
                    'month' => $this->countsFor($clinic, $monthStart, $monthEnd),
                ];

                $clinicUnitCosts = $unitCosts;
                $clinicUnitCosts['machine_day'] *= $dayShares[$clinic->id] ?? 0;
                $clinicUnitCosts['machine_month'] *= $monthShares[$clinic->id] ?? 0;

                return [
                    'clinic' => $clinic,
                    'day' => $this->totalsFor($counts['day'], $clinicUnitCosts, 'day') + ['usage' => $dayUsage[$clinic->id] ?? $this->emptyUsage()],
                    'month' => $this->totalsFor($counts['month'], $clinicUnitCosts, 'month') + ['usage' => $monthUsage[$clinic->id] ?? $this->emptyUsage()],
                ];
            });

        $summary = [
            'day' => $this->sumPeriod($clinics, 'day'),
            'month' => $this->sumPeriod($clinics, 'month'),
        ];

        return view('super-admin.costs', [
            'selectedMonth' => $selectedMonth,
            'unitCosts' => $unitCosts,
            'machineMonthlyUsd' => $machineMonthlyUsd,
            'hasUsageMetrics' => collect($monthUsage)->sum('requests') > 0,
            'activeClinics' => $activeClinics,
            'clinics' => $clinics,
            'summary' => $summary,
        ]);
    }

    private function usageFor(Carbon $start, Carbon $end): array
    {
        $rows = DB::table('clinic_request_metrics')
            ->whereBetween('recorded_at', [$start, $end])
            ->select('clinic_id', DB::raw('count(*) as requests'), DB::raw('sum(duration_ms) as duration_ms'), DB::raw('sum(memory_bytes) as memory_bytes'), DB::raw('sum(disk_bytes) as disk_bytes'))
            ->groupBy('clinic_id')
            ->get()
            ->keyBy('clinic_id');

        $timestamps = DB::table('clinic_request_metrics')
            ->whereBetween('recorded_at', [$start, $end])
            ->orderBy('recorded_at')
            ->get(['clinic_id', 'recorded_at'])
            ->groupBy('clinic_id');

        return $rows->mapWithKeys(function ($row) use ($timestamps): array {
            $activeSeconds = 0;
            $previous = null;

            foreach ($timestamps->get($row->clinic_id, collect()) as $metric) {
                $current = Carbon::parse($metric->recorded_at);
                $gap = $previous ? $previous->diffInSeconds($current) : 60;
                $activeSeconds += $gap <= 900 ? max(0, $gap) : 60;
                $previous = $current;
            }

            return [(int) $row->clinic_id => [
                'requests' => (int) $row->requests,
                'duration_ms' => (int) $row->duration_ms,
                'memory_bytes' => (int) $row->memory_bytes,
                'disk_bytes' => (int) $row->disk_bytes,
                'active_minutes' => (int) ceil($activeSeconds / 60),
            ]];
        })->all();
    }

    private function usageShares(array $usage, int $clinicCount): array
    {
        if (collect($usage)->sum('requests') === 0) {
            return Clinic::query()->pluck('id')->mapWithKeys(fn ($id): array => [(int) $id => 1 / $clinicCount])->all();
        }

        $totals = collect(['requests', 'duration_ms', 'memory_bytes', 'disk_bytes'])
            ->mapWithKeys(fn (string $metric): array => [$metric => collect($usage)->sum($metric)])
            ->filter(fn ($total): bool => $total > 0);
        $metrics = $totals->keys();

        return collect($usage)->mapWithKeys(function (array $row, int $clinicId) use ($metrics, $totals): array {
            $share = $metrics->avg(fn (string $metric): float => $row[$metric] / $totals[$metric]);

            return [$clinicId => $share];
        })->all();
    }

    private function emptyUsage(): array
    {
        return ['requests' => 0, 'duration_ms' => 0, 'memory_bytes' => 0, 'disk_bytes' => 0, 'active_minutes' => 0];
    }

    private function selectedMonth(Request $request): Carbon
    {
        $value = (string) $request->query('month', '');

        if (preg_match('/^\d{4}-\d{2}$/', $value)) {
            return Carbon::createFromFormat('Y-m-d', $value.'-01')->startOfMonth();
        }

        return now()->startOfMonth();
    }

    private function countsFor(Clinic $clinic, Carbon $start, Carbon $end): array
    {
        $notifications = DB::table('notifications')
            ->where('clinic_id', $clinic->id)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['failed', 'cancelled', 'canceled'])
            ->select('channel', DB::raw('count(*) as total'))
            ->groupBy('channel')
            ->pluck('total', 'channel');

        $inboundCalls = DB::table('call_logs')
            ->where('clinic_id', $clinic->id)
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', ['failed', 'cancelled', 'canceled'])
            ->count();

        return [
            'sms' => (int) ($notifications['sms'] ?? 0),
            'calls' => (int) ($notifications['voice'] ?? 0) + $inboundCalls,
            'emails' => (int) ($notifications['email'] ?? 0),
        ];
    }

    private function totalsFor(array $counts, array $unitCosts, string $period): array
    {
        $smsCost = $counts['sms'] * $unitCosts['sms'];
        $callCost = $counts['calls'] * $unitCosts['call'];
        $emailCost = $counts['emails'] * $unitCosts['email'];
        $machineCost = $period === 'day' ? $unitCosts['machine_day'] : $unitCosts['machine_month'];

        return [
            'sms_count' => $counts['sms'],
            'call_count' => $counts['calls'],
            'email_count' => $counts['emails'],
            'sms_cost' => $smsCost,
            'call_cost' => $callCost,
            'email_cost' => $emailCost,
            'machine_cost' => $machineCost,
            'total_cost' => $smsCost + $callCost + $emailCost + $machineCost,
        ];
    }

    private function sumPeriod($clinics, string $period): array
    {
        $keys = ['sms_count', 'call_count', 'email_count', 'sms_cost', 'call_cost', 'email_cost', 'machine_cost', 'total_cost'];
        $totals = array_fill_keys($keys, 0);

        foreach ($clinics as $row) {
            foreach ($keys as $key) {
                $totals[$key] += $row[$period][$key];
            }
        }

        return $totals;
    }

    private function cost(string $key): float
    {
        return (float) config('services.usage_costs.'.$key, 0);
    }
}
