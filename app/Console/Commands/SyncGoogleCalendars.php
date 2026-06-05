<?php

namespace App\Console\Commands;

use App\Models\Clinic;
use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;

class SyncGoogleCalendars extends Command
{
    protected $signature = 'google-calendar:sync {--clinic= : Sync a single clinic id}';

    protected $description = 'Synchronize connected Google Calendars with local appointments.';

    public function handle(GoogleCalendarService $calendar): int
    {
        $query = Clinic::query()
            ->whereNotNull('google_connected_at')
            ->whereNotNull('google_refresh_token');

        if ($clinicId = $this->option('clinic')) {
            $query->whereKey($clinicId);
        }

        $clinics = $query->get();

        if ($clinics->isEmpty()) {
            $this->info('No connected Google Calendars found.');

            return self::SUCCESS;
        }

        foreach ($clinics as $clinic) {
            try {
                $result = $calendar->syncClinic($clinic);
                $this->info("Clinic {$clinic->id}: {$result['exported']} exported, {$result['imported']} imported.");
            } catch (\Throwable $exception) {
                $this->error("Clinic {$clinic->id}: {$exception->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
