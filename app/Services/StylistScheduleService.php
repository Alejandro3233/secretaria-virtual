<?php

namespace App\Services;

use App\Models\Stylist;
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
        $workDays = $stylist->work_days ?: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
        $day = strtolower($startsAt->englishDayOfWeek);
        $workStart = $this->atTime($startsAt, $stylist->work_starts_at ?: '08:00');
        $workEnd = $this->atTime($startsAt, $stylist->work_ends_at ?: '21:00');
        $schedule = $workStart->format('g:i A').' a '.$workEnd->format('g:i A');

        if (! in_array($day, $workDays, true)) {
            $configuredDays = collect($workDays)
                ->map(fn (string $workDay): string => self::DAYS[$workDay] ?? $workDay)
                ->implode(', ');

            return $stylist->name.' no trabaja los '.(self::DAYS[$day] ?? $day)
                .'. Sus días configurados son '.$configuredDays.', de '.$schedule.'.';
        }

        if ($startsAt->lessThan($workStart) || $endsAt->greaterThan($workEnd)) {
            return 'La cita queda fuera del horario de '.$stylist->name.'. Ese día trabaja de '.$schedule
                .'. La cita debe comenzar y terminar dentro de su jornada.';
        }

        return null;
    }

    private function atTime(CarbonInterface $date, string $time): CarbonInterface
    {
        [$hour, $minute] = array_pad(array_map('intval', explode(':', $time)), 2, 0);

        return $date->copy()->setTime($hour, $minute);
    }
}
