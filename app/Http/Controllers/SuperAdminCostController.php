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
        $machineMonthlyShare = $machineMonthlyUsd / $activeClinics;
        $machineDailyShare = $machineMonthlyShare / max(1, $selectedMonth->daysInMonth);
        $unitCosts = [
            'sms' => $this->cost('sms_usd'),
            'call' => $this->cost('call_usd'),
            'email' => $this->cost('email_usd'),
            'machine_day' => $machineDailyShare,
            'machine_month' => $machineMonthlyShare,
        ];

        $clinics = Clinic::query()
            ->orderBy('name')
            ->get()
            ->map(function (Clinic $clinic) use ($monthStart, $monthEnd, $dayStart, $dayEnd, $unitCosts): array {
                $counts = [
                    'day' => $this->countsFor($clinic, $dayStart, $dayEnd),
                    'month' => $this->countsFor($clinic, $monthStart, $monthEnd),
                ];

                return [
                    'clinic' => $clinic,
                    'day' => $this->totalsFor($counts['day'], $unitCosts, 'day'),
                    'month' => $this->totalsFor($counts['month'], $unitCosts, 'month'),
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
            'activeClinics' => $activeClinics,
            'clinics' => $clinics,
            'summary' => $summary,
        ]);
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
