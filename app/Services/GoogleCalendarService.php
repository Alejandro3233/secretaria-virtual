<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\Service;
use Carbon\CarbonInterface;
use Google\Client as GoogleClient;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class GoogleCalendarService
{
    public function makeClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId((string) config('google.calendar.client_id'));
        $client->setClientSecret((string) config('google.calendar.client_secret'));
        $client->setRedirectUri((string) config('google.calendar.redirect_uri'));
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        $client->setIncludeGrantedScopes(true);
        $client->addScope(Calendar::CALENDAR);

        return $client;
    }

    public function authUrl(string $state): string
    {
        $client = $this->makeClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function connectClinic(Clinic $clinic, string $authCode): void
    {
        $client = $this->makeClient();
        $token = $client->fetchAccessTokenWithAuthCode($authCode);

        if (isset($token['error'])) {
            throw new RuntimeException($token['error_description'] ?? $token['error']);
        }

        $client->setAccessToken($token);

        $calendar = new Calendar($client);
        $calendarSummary = 'Google Calendar';

        try {
            $primary = $calendar->calendars->get('primary');
            $calendarSummary = $primary->getSummary() ?: $calendarSummary;
        } catch (\Throwable) {
            $calendarSummary = 'Calendario principal';
        }

        $clinic->forceFill([
            'google_calendar_id' => 'primary',
            'google_calendar_summary' => $calendarSummary,
            'google_access_token' => $token,
            'google_refresh_token' => $token['refresh_token'] ?? $clinic->google_refresh_token,
            'google_token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
            'google_connected_at' => now(),
            'google_last_synced_at' => null,
            'google_sync_token' => null,
        ])->save();
    }

    public function disconnectClinic(Clinic $clinic): void
    {
        $clinic->forceFill([
            'google_calendar_id' => null,
            'google_calendar_summary' => null,
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
            'google_connected_at' => null,
            'google_last_synced_at' => null,
            'google_sync_token' => null,
        ])->save();
    }

    public function syncClinic(Clinic $clinic): array
    {
        $this->ensureConnected($clinic);

        return DB::transaction(function () use ($clinic): array {
            $exported = 0;

            $clinic->appointments()
                ->where(function ($query): void {
                    $query->whereNull('google_calendar_event_id')
                        ->orWhere('google_sync_status', 'pending');
                })
                ->get()
                ->each(function (Appointment $appointment) use (&$exported): void {
                    $this->upsertAppointment($appointment);
                    $exported++;
                });

            $imported = $this->importUpcomingEvents($clinic);

            $clinic->forceFill([
                'google_last_synced_at' => now(),
            ])->save();

            return [
                'exported' => $exported,
                'imported' => $imported,
            ];
        });
    }

    public function upsertAppointment(Appointment $appointment): Appointment
    {
        if (in_array($appointment->status, ['cancelled', 'canceled'], true)) {
            return $this->cancelAppointment($appointment);
        }

        $clinic = $appointment->clinic;
        $this->ensureConnected($clinic);

        $calendar = new Calendar($this->authorizedClient($clinic));
        $calendarId = $clinic->google_calendar_id ?: 'primary';
        $event = $this->appointmentToEvent($appointment);

        try {
            if ($appointment->google_calendar_event_id) {
                $savedEvent = $calendar->events->update($calendarId, $appointment->google_calendar_event_id, $event);
            } else {
                $savedEvent = $calendar->events->insert($calendarId, $event);
            }

            $appointment->forceFill([
                'google_calendar_id' => $calendarId,
                'google_calendar_event_id' => $savedEvent->getId(),
                'google_synced_at' => now(),
                'google_sync_status' => 'synced',
                'google_sync_error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $appointment->forceFill([
                'google_sync_status' => 'failed',
                'google_sync_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }

        return $appointment;
    }

    public function cancelAppointment(Appointment $appointment): Appointment
    {
        $clinic = $appointment->clinic;
        $this->ensureConnected($clinic);

        if (! $appointment->google_calendar_event_id) {
            $appointment->forceFill([
                'google_sync_status' => 'synced',
                'google_synced_at' => now(),
                'google_sync_error' => null,
            ])->save();

            return $appointment;
        }

        $calendar = new Calendar($this->authorizedClient($clinic));
        $calendarId = $appointment->google_calendar_id ?: $clinic->google_calendar_id ?: 'primary';

        try {
            $calendar->events->delete($calendarId, $appointment->google_calendar_event_id);

            $appointment->forceFill([
                'google_sync_status' => 'synced',
                'google_synced_at' => now(),
                'google_sync_error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            $appointment->forceFill([
                'google_sync_status' => 'failed',
                'google_sync_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }

        return $appointment;
    }

    public function importUpcomingEvents(Clinic $clinic): int
    {
        $this->ensureConnected($clinic);

        $calendar = new Calendar($this->authorizedClient($clinic));
        $calendarId = $clinic->google_calendar_id ?: 'primary';

        $events = $calendar->events->listEvents($calendarId, [
            'singleEvents' => true,
            'orderBy' => 'startTime',
            'timeMin' => now()->startOfWeek()->toRfc3339String(),
            'timeMax' => now()->addDays(60)->endOfDay()->toRfc3339String(),
            'maxResults' => 100,
            'showDeleted' => true,
        ]);

        $imported = 0;
        $seenEventIds = [];

        foreach ($events->getItems() as $event) {
            if (! $event->getId()) {
                continue;
            }

            $seenEventIds[] = $event->getId();

            if ($event->getStatus() === 'cancelled') {
                Appointment::query()
                    ->where('clinic_id', $clinic->id)
                    ->where('google_calendar_event_id', $event->getId())
                    ->update([
                        'status' => 'cancelled',
                        'google_sync_status' => 'synced',
                        'google_synced_at' => now(),
                        'google_sync_error' => null,
                        'updated_at' => now(),
                    ]);

                continue;
            }

            $startsAt = $this->eventDateToCarbon($event->getStart());
            if (! $startsAt) {
                continue;
            }

            $endsAt = $this->eventDateToCarbon($event->getEnd()) ?? $startsAt->copy()->addMinutes(60);

            $appointment = Appointment::query()
                ->where('clinic_id', $clinic->id)
                ->where('google_calendar_event_id', $event->getId())
                ->first();

            if (! $appointment) {
                $appointment = new Appointment([
                    'clinic_id' => $clinic->id,
                    'client_id' => $this->calendarClient($clinic, $event)->id,
                    'service_id' => $this->calendarService($clinic)->id,
                    'status' => 'confirmed',
                    'source' => 'google_calendar',
                    'priority' => 'normal',
                ]);

                $imported++;
            }

            $appointment->forceFill([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'reason' => $event->getSummary() ?: 'Cita importada desde Google Calendar',
                'client_comments' => $event->getDescription(),
                'google_calendar_id' => $calendarId,
                'google_calendar_event_id' => $event->getId(),
                'google_synced_at' => now(),
                'google_sync_status' => 'synced',
                'google_sync_error' => null,
            ])->save();
        }

        $this->cancelMissingGoogleAppointments($clinic, $calendarId, $seenEventIds);

        return $imported;
    }

    private function cancelMissingGoogleAppointments(Clinic $clinic, string $calendarId, array $seenEventIds): void
    {
        Appointment::query()
            ->where('clinic_id', $clinic->id)
            ->where('source', 'google_calendar')
            ->where('google_calendar_id', $calendarId)
            ->whereNotNull('google_calendar_event_id')
            ->where('starts_at', '>=', now()->startOfWeek())
            ->where('starts_at', '<=', now()->addDays(60)->endOfDay())
            ->whereNotIn('google_calendar_event_id', $seenEventIds ?: ['__none__'])
            ->update([
                'status' => 'cancelled',
                'google_sync_status' => 'synced',
                'google_synced_at' => now(),
                'google_sync_error' => null,
                'updated_at' => now(),
            ]);
    }

    private function authorizedClient(Clinic $clinic): GoogleClient
    {
        $client = $this->makeClient();
        $client->setAccessToken($clinic->google_access_token ?? []);

        if ($client->isAccessTokenExpired() && $clinic->google_refresh_token) {
            $token = $client->fetchAccessTokenWithRefreshToken($clinic->google_refresh_token);

            if (isset($token['error'])) {
                throw new RuntimeException($token['error_description'] ?? $token['error']);
            }

            $clinic->forceFill([
                'google_access_token' => $client->getAccessToken(),
                'google_token_expires_at' => isset($token['expires_in']) ? now()->addSeconds((int) $token['expires_in']) : null,
            ])->save();
        }

        return $client;
    }

    private function ensureConnected(Clinic $clinic): void
    {
        if (! $clinic->google_connected_at || ! $clinic->google_refresh_token) {
            throw new RuntimeException('El salon no tiene Google Calendar conectado.');
        }
    }

    private function appointmentToEvent(Appointment $appointment): Event
    {
        $client = $appointment->client;
        $service = $appointment->service;
        $stylist = $appointment->stylist;

        $summary = trim(($service?->name ?? $appointment->reason ?? 'Cita de salon').' - '.$client->first_name.' '.$client->last_name);
        $description = collect([
            $stylist ? 'Estilista: '.$stylist->name : null,
            $appointment->chair_station ? 'Estacion: '.$appointment->chair_station : null,
            $appointment->client_comments ? 'Comentarios: '.$appointment->client_comments : null,
            $appointment->internal_notes ? 'Notas internas: '.$appointment->internal_notes : null,
        ])->filter()->implode("\n");

        return new Event([
            'summary' => $summary,
            'description' => $description,
            'start' => $this->eventDateTime($appointment->starts_at),
            'end' => $this->eventDateTime($appointment->ends_at ?? $appointment->starts_at->copy()->addMinutes($service?->duration_minutes ?? 60)),
            'extendedProperties' => [
                'private' => [
                    'secretaria_virtual_appointment_id' => (string) $appointment->id,
                    'source' => 'secretaria_virtual',
                ],
            ],
        ]);
    }

    private function eventDateTime(CarbonInterface $date): EventDateTime
    {
        return new EventDateTime([
            'dateTime' => $date->toRfc3339String(),
            'timeZone' => config('app.timezone'),
        ]);
    }

    private function eventDateToCarbon(?EventDateTime $date): ?Carbon
    {
        if (! $date) {
            return null;
        }

        $value = $date->getDateTime() ?: $date->getDate();

        return $value ? Carbon::parse($value) : null;
    }

    private function calendarClient(Clinic $clinic, Event $event): Client
    {
        $phone = 'google:'.$event->getId();
        $summary = $event->getSummary() ?: 'Cliente Google Calendar';

        return Client::query()->firstOrCreate(
            ['clinic_id' => $clinic->id, 'phone' => $phone],
            [
                'first_name' => $summary,
                'last_name' => null,
                'email' => null,
                'notification_preference' => 'email',
                'notes' => 'Cliente creado automaticamente desde Google Calendar.',
            ]
        );
    }

    private function calendarService(Clinic $clinic): Service
    {
        return Service::query()->firstOrCreate(
            ['clinic_id' => $clinic->id, 'name' => 'Google Calendar'],
            ['duration_minutes' => 60, 'is_active' => true]
        );
    }
}
