<?php

namespace App\Console\Commands;

use App\Services\AppointmentReminderCallService;
use Illuminate\Console\Command;

class SendAppointmentReminderCalls extends Command
{
    protected $signature = 'appointments:reminder-calls';

    protected $description = 'Envia llamadas y SMS 24 horas antes de la cita segun las preferencias de cada cita.';

    public function handle(AppointmentReminderCallService $reminders): int
    {
        $appointments = $reminders->dueAppointments();
        $queued = 0;
        $smsAppointments = $reminders->dueSmsAppointments();
        $smsSent = 0;

        foreach ($appointments as $appointment) {
            if ($reminders->call($appointment)) {
                $queued++;
            }
        }

        foreach ($smsAppointments as $appointment) {
            if ($reminders->sendSms($appointment)) {
                $smsSent++;
            }
        }

        $this->info("Llamadas recordatorio encoladas: {$queued}");
        $this->info("SMS recordatorio enviados: {$smsSent}");

        return self::SUCCESS;
    }
}
