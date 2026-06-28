<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\InventoryItem;
use App\Services\ClinicResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ManagerController extends Controller
{
    public function index(Request $request, ClinicResolver $clinics)
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $timezone = $clinic->localTimezone();

        try {
            $selectedMonth = Carbon::createFromFormat('Y-m-d', ((string) $request->query('month')).'-01', $timezone)->startOfMonth();
        } catch (\Throwable) {
            $selectedMonth = now($timezone)->startOfMonth();
        }

        $periodStart = $selectedMonth->copy()->startOfMonth();
        $periodEnd = $selectedMonth->copy()->endOfMonth();
        $databaseStart = $periodStart->copy()->timezone(config('app.timezone'));
        $databaseEnd = $periodEnd->copy()->timezone(config('app.timezone'));

        $appointments = Appointment::query()
            ->with(['client', 'service', 'stylist'])
            ->where('clinic_id', $clinic->id)
            ->whereBetween('starts_at', [$databaseStart, $databaseEnd])
            ->orderBy('starts_at')
            ->get();
        $activeAppointments = $appointments->reject(fn (Appointment $appointment) => in_array($appointment->status, ['cancelled', 'canceled', 'no_show'], true));
        $attendedAppointments = $appointments->whereIn('status', ['attended', 'completed']);
        $cancelledAppointments = $appointments->whereIn('status', ['cancelled', 'canceled']);
        $noShowAppointments = $appointments->where('status', 'no_show');
        $expectedRevenueCents = $this->revenue($activeAppointments);
        $realizedRevenueCents = $this->revenue($attendedAppointments);
        $averageTicketCents = $activeAppointments->count() > 0
            ? (int) round($expectedRevenueCents / $activeAppointments->count())
            : 0;
        $cancellationRate = $appointments->count() > 0
            ? round(($cancelledAppointments->count() / $appointments->count()) * 100, 1)
            : 0;

        $previousStart = $periodStart->copy()->subMonthNoOverflow()->startOfMonth();
        $previousEnd = $previousStart->copy()->endOfMonth();
        $previousAppointments = Appointment::query()
            ->with('service')
            ->where('clinic_id', $clinic->id)
            ->whereBetween('starts_at', [
                $previousStart->copy()->timezone(config('app.timezone')),
                $previousEnd->copy()->timezone(config('app.timezone')),
            ])
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->get();
        $previousRevenueCents = $this->revenue($previousAppointments);
        $revenueChange = $previousRevenueCents > 0
            ? round((($expectedRevenueCents - $previousRevenueCents) / $previousRevenueCents) * 100, 1)
            : ($expectedRevenueCents > 0 ? 100 : 0);

        $stylists = $clinic->stylists()
            ->where('is_internal', false)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $scheduledMinutes = (int) $activeAppointments->sum(fn (Appointment $appointment) => max(0, $appointment->starts_at->diffInMinutes($appointment->ends_at ?? $appointment->starts_at)));
        $availableMinutes = $this->availableMinutes($stylists, $periodStart, $periodEnd);
        $occupancyRate = $availableMinutes > 0 ? min(100, round(($scheduledMinutes / $availableMinutes) * 100, 1)) : 0;

        $professionalReport = $stylists->map(function ($stylist) use ($appointments): array {
            $items = $appointments->where('stylist_id', $stylist->id);
            $active = $items->reject(fn (Appointment $appointment) => in_array($appointment->status, ['cancelled', 'canceled', 'no_show'], true));
            $cancelled = $items->whereIn('status', ['cancelled', 'canceled']);
            $revenue = $this->revenue($active);

            return [
                'stylist' => $stylist,
                'appointments' => $active->count(),
                'attended' => $items->whereIn('status', ['attended', 'completed'])->count(),
                'cancelled' => $cancelled->count(),
                'revenue_cents' => $revenue,
                'average_ticket_cents' => $active->count() ? (int) round($revenue / $active->count()) : 0,
            ];
        })->sortByDesc('revenue_cents')->values();

        $serviceReport = $clinic->services()->where('is_active', true)->orderBy('name')->get()
            ->map(function ($service) use ($activeAppointments): array {
                $items = $activeAppointments->where('service_id', $service->id);

                return [
                    'service' => $service,
                    'appointments' => $items->count(),
                    'revenue_cents' => $items->sum(fn (Appointment $appointment) => (int) ($appointment->campaign_price_cents ?? $service->price_cents ?? 0)),
                ];
            })->sortByDesc('revenue_cents')->values();

        $allClients = $clinic->clients()->with(['appointments.service'])->get();
        $newClients = $allClients->filter(fn ($client) => $client->created_at?->between($databaseStart, $databaseEnd))->count();
        $recurringClients = $allClients->filter(fn ($client) => $client->appointments->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])->count() >= 2)->count();
        $inactiveClientLimit = now()->subMonths(3);
        $inactiveClients = $allClients->filter(function ($client) use ($inactiveClientLimit): bool {
            $lastVisit = $client->appointments->whereIn('status', ['attended', 'completed'])->max('starts_at');

            return $lastVisit && Carbon::parse($lastVisit)->lt($inactiveClientLimit);
        })->count();
        $topClients = $allClients->map(function ($client): array {
            $valid = $client->appointments->reject(fn (Appointment $appointment) => in_array($appointment->status, ['cancelled', 'canceled', 'no_show'], true));

            return [
                'client' => $client,
                'appointments' => $valid->count(),
                'revenue_cents' => $this->revenue($client->appointments->whereIn('status', ['attended', 'completed'])),
                'last_visit' => $client->appointments->whereIn('status', ['attended', 'completed'])->sortByDesc('starts_at')->first()?->starts_at,
            ];
        })->sortByDesc(fn (array $row) => [$row['revenue_cents'], $row['appointments']])->take(10)->values();

        $trendStart = $periodStart->copy()->subMonths(5)->startOfMonth();
        $trendAppointments = Appointment::query()
            ->with('service')
            ->where('clinic_id', $clinic->id)
            ->whereBetween('starts_at', [
                $trendStart->copy()->timezone(config('app.timezone')),
                $periodEnd->copy()->timezone(config('app.timezone')),
            ])
            ->whereNotIn('status', ['cancelled', 'canceled', 'no_show'])
            ->get();
        $monthlyTrend = collect(range(0, 5))->map(function (int $offset) use ($trendStart, $trendAppointments, $timezone): array {
            $month = $trendStart->copy()->addMonths($offset);
            $items = $trendAppointments->filter(fn (Appointment $appointment) => $appointment->starts_at->copy()->timezone($timezone)->format('Y-m') === $month->format('Y-m'));

            return [
                'label' => ucfirst($month->copy()->locale('es')->isoFormat('MMM')),
                'revenue_cents' => $this->revenue($items),
                'appointments' => $items->count(),
            ];
        });
        $maxTrendRevenue = max(1, (int) $monthlyTrend->max('revenue_cents'));

        $weekdayReport = $activeAppointments
            ->groupBy(fn (Appointment $appointment) => $appointment->starts_at->copy()->timezone($timezone)->dayOfWeekIso)
            ->map->count();
        $busiestWeekdayNumber = $weekdayReport->sortDesc()->keys()->first();
        $weekdayNames = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        $busiestWeekday = $busiestWeekdayNumber ? $weekdayNames[$busiestWeekdayNumber] : 'Sin datos';

        $inventoryItems = $clinic->inventoryItems()->where('is_active', true)->orderBy('name')->get();
        $lowStockItems = $inventoryItems->filter->isLowStock();
        $outOfStockItems = $inventoryItems->filter(fn (InventoryItem $item) => (float) $item->current_stock <= 0);
        $inventoryCostCents = (int) $inventoryItems->sum(fn (InventoryItem $item) => (float) $item->current_stock * (int) ($item->cost_cents ?? 0));
        $inventoryRetailCents = (int) $inventoryItems->sum(fn (InventoryItem $item) => (float) $item->current_stock * (int) ($item->sale_price_cents ?? 0));

        return view('manager.index', compact(
            'clinic', 'timezone', 'selectedMonth', 'periodStart', 'periodEnd', 'appointments', 'activeAppointments',
            'attendedAppointments', 'cancelledAppointments', 'noShowAppointments', 'expectedRevenueCents',
            'realizedRevenueCents', 'averageTicketCents', 'cancellationRate', 'previousRevenueCents', 'revenueChange',
            'occupancyRate', 'professionalReport', 'serviceReport', 'allClients', 'newClients', 'recurringClients',
            'inactiveClients', 'topClients', 'monthlyTrend', 'maxTrendRevenue', 'busiestWeekday', 'inventoryItems',
            'lowStockItems', 'outOfStockItems', 'inventoryCostCents', 'inventoryRetailCents'
        ));
    }

    public function storeInventory(Request $request, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:80', 'unique:inventory_items,sku,NULL,id,clinic_id,'.$clinic->id],
            'category' => ['nullable', 'string', 'max:100'],
            'current_stock' => ['required', 'numeric', 'min:0'],
            'minimum_stock' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:30'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $clinic->inventoryItems()->create([
            'name' => $data['name'],
            'sku' => filled($data['sku'] ?? null) ? $data['sku'] : null,
            'category' => filled($data['category'] ?? null) ? $data['category'] : null,
            'current_stock' => $data['current_stock'],
            'minimum_stock' => $data['minimum_stock'],
            'unit' => $data['unit'],
            'cost_cents' => filled($data['cost'] ?? null) ? (int) round((float) $data['cost'] * 100) : null,
            'sale_price_cents' => filled($data['sale_price'] ?? null) ? (int) round((float) $data['sale_price'] * 100) : null,
            'is_active' => true,
        ]);

        return redirect('/manager#inventario')->with('manager_status', 'Producto agregado al inventario.');
    }

    public function adjustInventory(Request $request, InventoryItem $inventoryItem, ClinicResolver $clinics): RedirectResponse
    {
        $clinic = $clinics->currentOrCreate($request->user());
        abort_unless($inventoryItem->clinic_id === $clinic->id, 404);
        $data = $request->validate(['adjustment' => ['required', 'numeric', 'not_in:0']]);
        $newStock = max(0, (float) $inventoryItem->current_stock + (float) $data['adjustment']);
        $inventoryItem->update(['current_stock' => $newStock]);

        return redirect('/manager#inventario')->with('manager_status', 'Existencias actualizadas para '.$inventoryItem->name.'.');
    }

    private function revenue(Collection $appointments): int
    {
        return (int) $appointments->sum(fn (Appointment $appointment) => (int) ($appointment->campaign_price_cents ?? $appointment->service?->price_cents ?? 0));
    }

    private function availableMinutes(Collection $stylists, Carbon $start, Carbon $end): int
    {
        $minutes = 0;

        foreach ($stylists as $stylist) {
            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                $schedule = $stylist->scheduleForDate($day);
                if (! $schedule) continue;
                $dailyMinutes = max(0, Carbon::parse($schedule['start'])->diffInMinutes(Carbon::parse($schedule['end'])));
                if ($schedule['break_start'] && $schedule['break_end']) {
                    $dailyMinutes = max(0, $dailyMinutes - Carbon::parse($schedule['break_start'])->diffInMinutes(Carbon::parse($schedule['break_end'])));
                }
                $minutes += $dailyMinutes;
            }
        }

        return $minutes;
    }
}
