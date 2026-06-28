<?php

namespace App\Services;

use App\Models\Stylist;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class StylistScheduleService
{
    private const DAYS = [
        'monday' => 'lunes',
        'tuesday' => 'martes',
        'wednesday' => 'miércoles',
        'thursday' => 'jueves',
        'friday' => 'viernes',
        'saturday' => 'sábado',
        'sunday' => 'domingo',
    ];

    public function validationMessage(Stylist $stylist, CarbonInterface $startsAt, CarbonInterface $endsAt): ?string
    {
        $vacation = $stylist->vacations()
            ->whereDate('starts_on', '<=', $startsAt->toDateString())
            ->whereDate('ends_on', '>=', $startsAt->toDateString())
            ->first();

        if ($vacation) {
            $range = $vacation->starts_on->format('d/m/Y').' al '.$vacation->ends_on->format('d/m/Y');
            $reason = $vacation->reason ? ' Motivo: '.$vacation->reason.'.' : '';

            return $stylist->name.' esta de vacaciones del '.$range.'.'.$reason;
        }

        $day = strtolower($startsAt->englishDayOfWeek);
        $daySchedule = $stylist->scheduleForDate($startsAt);
        $workStart = $this->atTime($startsAt, $daySchedule['start'] ?? '08:00');
        $workEnd = $this->atTime($startsAt, $daySchedule['end'] ?? '21:00');
        $schedule = $workStart->format('g:i A').' a '.$workEnd->format('g:i A');

        if (! $daySchedule) {
            $configuredDays = collect($stylist->weekly_schedule ?: array_fill_keys($stylist->work_days ?: [], ['enabled' => true]))
                ->filter(fn ($configured) => ! empty($configured['enabled']))->keys()
                ->map(fn (string $workDay): string => self::DAYS[$workDay] ?? $workDay)
                ->implode(', ');

            return $stylist->name.' no trabaja los '.(self::DAYS[$day] ?? $day)
                .'. Sus días configurados son '.$configuredDays.', de '.$schedule.'.';
        }

        if ($startsAt->lessThan($workStart) || $endsAt->greaterThan($workEnd)) {
            return 'La cita queda fuera del horario de '.$stylist->name.'. Ese día trabaja de '.$schedule
                .'. La cita debe comenzar y terminar dentro de su jornada.';
        }

        if ($stylist->isOnBreak($startsAt, $endsAt)) {
            $daySchedule = $stylist->scheduleForDate($startsAt);
            return 'La cita coincide con el descanso de '.$stylist->name.', de '
                .Carbon::parse($daySchedule['break_start'])->format('g:i A').' a '
                .Carbon::parse($daySchedule['break_end'])->format('g:i A').'.';
        }

        return null;
    }

    private function atTime(CarbonInterface $date, string $time): CarbonInterface
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $date->copy()->setTime($hour, $minute);
    }
}
