<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Clinic;
use App\Models\GoogleCalendarMapping;
use App\Models\Service;
use App\Models\Stylist;
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
            'google_calendar_organization_mode' => null,
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
            'google_calendar_organization_mode' => null,
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
            'google_connected_at' => null,
            'google_last_synced_at' => null,
            'google_sync_token' => null,
        ])->save();
    }

    public function discoverCalendars(Clinic $clinic): int
    {
        $this->ensureConnected($clinic);

        $calendar = new Calendar($this->authorizedClient($clinic));
        $detectedIds = [];
        $pageToken = null;
        $detected = 0;

        do {
            $calendarList = $calendar->calendarList->listCalendarList(array_filter([
                'maxResults' => 250,
                'pageToken' => $pageToken,
                'showDeleted' => false,
                'showHidden' => false,
            ]));

            foreach ($calendarList->getItems() as $item) {
                if (! $item->getId()) {
                    continue;
                }

                $calendarId = $item->getPrimary() ? 'primary' : $item->getId();
                $detectedIds[] = $calendarId;
                $detected++;

                if ($item->getPrimary() && $item->getId() !== 'primary') {
                    $legacyPrimary = GoogleCalendarMapping::query()
                        ->where('clinic_id', $clinic->id)
                        ->where('google_calendar_id', $item->getId())
                        ->first();

                    if ($legacyPrimary && ! GoogleCalendarMapping::query()->where('clinic_id', $clinic->id)->where('google_calendar_id', 'primary')->exists()) {
                        $legacyPrimary->update(['google_calendar_id' => 'primary']);
                    }
                }

                $mapping = GoogleCalendarMapping::query()->firstOrNew([
                    'clinic_id' => $clinic->id,
                    'google_calendar_id' => $calendarId,
                ]);

                if (! $mapping->exists) {
                    $mapping->is_enabled = (bool) $item->getPrimary();
                }

                $mapping->fill([
                    'google_calendar_name' => $item->getSummary() ?: 'Calendario sin nombre',
                    'access_role' => $item->getAccessRole(),
                    'is_primary' => (bool) $item->getPrimary(),
                    'is_available' => true,
                    'last_detected_at' => now(),
                ])->save();
            }

            $pageToken = $calendarList->getNextPageToken();
        } while ($pageToken);

        GoogleCalendarMapping::query()
            ->where('clinic_id', $clinic->id)
            ->when($detectedIds, fn ($query) => $query->whereNotIn('google_calendar_id', $detectedIds))
            ->update(['is_available' => false]);

        return $detected;
    }

    public function createCalendarsForStylists(Clinic $clinic): int
    {
        $this->ensureConnected($clinic);

        $google = new Calendar($this->authorizedClient($clinic));
        $created = 0;

        $clinic->stylists()
            ->where('is_internal', false)
            ->where('is_active', true)
            ->get()
            ->each(function (Stylist $stylist) use ($clinic, $google, &$created): void {
                $assignedMapping = $clinic->googleCalendarMappings()
                    ->where('stylist_id', $stylist->id)
                    ->where('is_available', true)
                    ->whereIn('access_role', ['owner', 'writer'])
                    ->first();

                if ($assignedMapping) {
                    $assignedMapping->update(['is_enabled' => true]);
                    return;
                }

                $resource = new \Google\Service\Calendar\Calendar();
                $resource->setSummary($clinic->name.' · '.$stylist->name);
                $resource->setTimeZone($clinic->localTimezone());
                $createdCalendar = $google->calendars->insert($resource);

                GoogleCalendarMapping::query()->updateOrCreate(
                    [
                        'clinic_id' => $clinic->id,
                        'google_calendar_id' => $createdCalendar->getId(),
                    ],
                    [
                        'stylist_id' => $stylist->id,
                        'google_calendar_name' => $createdCalendar->getSummary() ?: $resource->getSummary(),
                        'access_role' => 'owner',
                        'is_primary' => false,
                        'is_enabled' => true,
                        'is_available' => true,
                        'last_detected_at' => now(),
                    ],
                );

                $created++;
            });

        return $created;
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

            $mappings = $clinic->googleCalendarMappings()
                ->where('is_enabled', true)
                ->where('is_available', true)
                ->get();

            if ($mappings->isEmpty()) {
                $imported = $this->importUpcomingEvents($clinic);
            } else {
                $imported = $mappings->sum(fn (GoogleCalendarMapping $mapping) => $this->importUpcomingEvents($clinic, $mapping));
            }

            $clinic->forceFill([
                'google_last_synced_at' => now(),
                'google_ever_synced_at' => $clinic->google_ever_synced_at ?: now(),
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
        $calendarId = $appointment->google_calendar_id
            ?: $this->calendarIdForAppointment($appointment);
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
                'google_calendar_event_id' => null,
                'google_sync_status' => 'synced',
                'google_synced_at' => now(),
                'google_sync_error' => null,
            ])->save();
        } catch (\Throwable $exception) {
            if ($this->isAlreadyDeletedGoogleEvent($exception)) {
                $appointment->forceFill([
                    'google_calendar_event_id' => null,
                    'google_sync_status' => 'synced',
                    'google_synced_at' => now(),
                    'google_sync_error' => null,
                ])->save();

                return $appointment;
            }

            $appointment->forceFill([
                'google_sync_status' => 'failed',
                'google_sync_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }

        return $appointment;
    }

    private function isAlreadyDeletedGoogleEvent(\Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, '"code": 410')
            || str_contains($message, '"code": 404')
            || str_contains($message, 'Resource has been deleted')
            || str_contains($message, 'Not Found');
    }

    public function importUpcomingEvents(Clinic $clinic, ?GoogleCalendarMapping $mapping = null): int
    {
        $this->ensureConnected($clinic);

        $calendar = new Calendar($this->authorizedClient($clinic));
        $calendarId = $mapping?->google_calendar_id ?: $clinic->google_calendar_id ?: 'primary';

        $events = $calendar->events->listEvents($calendarId, [
            'singleEvents' => true,
            'orderBy' => 'startTime',
            'timeMin' => now($clinic->localTimezone())->startOfWeek()->toRfc3339String(),
            'timeMax' => now($clinic->localTimezone())->addDays(60)->endOfDay()->toRfc3339String(),
            'maxResults' => 100,
            'showDeleted' => true,
        ]);

        $imported = 0;
        $seenEventIds = [];
        $googleStylist = $mapping?->stylist ?: $this->calendarStylist($clinic);

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

            $appointment = $this->findAppointmentForGoogleEvent(
                $clinic,
                $event,
                $calendarId,
                $googleStylist,
                $startsAt,
                $endsAt
            );

            if (! $appointment) {
                $appointment = new Appointment([
                    'clinic_id' => $clinic->id,
                    'client_id' => $this->calendarClient($clinic, $event)->id,
                    'service_id' => $this->calendarService($clinic)->id,
                    'stylist_id' => $googleStylist->id,
                    'status' => 'pending',
                    'source' => 'google_calendar',
                    'priority' => 'normal',
                    'reminder_call_enabled' => false,
                    'reminder_sms_enabled' => false,
                ]);

                $imported++;
            }

            $syncedData = [
                'google_calendar_id' => $calendarId,
                'google_calendar_event_id' => $event->getId(),
                'google_synced_at' => now(),
                'google_sync_status' => 'synced',
                'google_sync_error' => null,
            ];

            if ($appointment->source === 'google_calendar') {
                $syncedData['status'] = 'pending';
                $syncedData['stylist_id'] = $googleStylist->id;
                $syncedData['starts_at'] = $startsAt;
                $syncedData['ends_at'] = $endsAt;
                $syncedData['reason'] = $event->getSummary() ?: 'Cita importada desde Google Calendar';
                $syncedData['client_comments'] = $event->getDescription();
            }

            $appointment->forceFill($syncedData)->save();
        }

        $this->cancelMissingGoogleAppointments($clinic, $calendarId, $seenEventIds);

        return $imported;
    }

    private function findAppointmentForGoogleEvent(
        Clinic $clinic,
        Event $event,
        string $calendarId,
        Stylist $stylist,
        Carbon $startsAt,
        Carbon $endsAt
    ): ?Appointment {
        $internalAppointmentId = data_get(
            $event->getExtendedProperties()?->getPrivate(),
            'secretaria_virtual_appointment_id'
        );

        if ($internalAppointmentId && ctype_digit((string) $internalAppointmentId)) {
            $appointment = Appointment::query()
                ->where('clinic_id', $clinic->id)
                ->whereKey((int) $internalAppointmentId)
                ->first();

            if ($appointment) {
                return $appointment;
            }
        }

        $appointment = Appointment::query()
            ->where('clinic_id', $clinic->id)
            ->where('google_calendar_event_id', $event->getId())
            ->first();

        if ($appointment) {
            return $appointment;
        }

        if ($stylist->is_internal) {
            return null;
        }

        $summary = trim((string) ($event->getSummary() ?: ''));

        return Appointment::query()
            ->with(['client', 'service'])
            ->where('clinic_id', $clinic->id)
            ->where('stylist_id', $stylist->id)
            ->where('source', '!=', 'google_calendar')
            ->where('starts_at', $startsAt)
            ->where('ends_at', $endsAt)
            ->get()
            ->first(function (Appointment $candidate) use ($summary): bool {
                $candidateSummary = trim(
                    ($candidate->service?->name ?? $candidate->reason ?? 'Cita de salon')
                    .' - '
                    .trim(($candidate->client?->first_name ?? '').' '.($candidate->client?->last_name ?? ''))
                );

                return $summary !== '' && mb_strtolower($candidateSummary) === mb_strtolower($summary);
            });
    }

    private function cancelMissingGoogleAppointments(Clinic $clinic, string $calendarId, array $seenEventIds): void
    {
        Appointment::query()
            ->where('clinic_id', $clinic->id)
            ->where('source', 'google_calendar')
            ->where('google_calendar_id', $calendarId)
            ->whereNotNull('google_calendar_event_id')
            ->where('starts_at', '>=', now($clinic->localTimezone())->startOfWeek()->timezone(config('app.timezone')))
            ->where('starts_at', '<=', now($clinic->localTimezone())->addDays(60)->endOfDay()->timezone(config('app.timezone')))
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

    private function calendarIdForAppointment(Appointment $appointment): string
    {
        if ($appointment->stylist_id) {
            $mapping = GoogleCalendarMapping::query()
                ->where('clinic_id', $appointment->clinic_id)
                ->where('stylist_id', $appointment->stylist_id)
                ->where('is_enabled', true)
                ->where('is_available', true)
                ->whereIn('access_role', ['owner', 'writer'])
                ->first();

            if ($mapping) {
                return $mapping->google_calendar_id;
            }
        }

        return $appointment->clinic->google_calendar_id ?: 'primary';
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
            'start' => $this->eventDateTime($appointment->starts_at, $appointment->clinic->localTimezone()),
            'end' => $this->eventDateTime($appointment->ends_at ?? $appointment->starts_at->copy()->addMinutes($service?->duration_minutes ?? 60), $appointment->clinic->localTimezone()),
            'extendedProperties' => [
                'private' => [
                    'secretaria_virtual_appointment_id' => (string) $appointment->id,
                    'source' => 'secretaria_virtual',
                ],
            ],
        ]);
    }

    private function eventDateTime(CarbonInterface $date, string $timezone): EventDateTime
    {
        return new EventDateTime([
            'dateTime' => Carbon::instance($date)->timezone($timezone)->toRfc3339String(),
            'timeZone' => $timezone,
        ]);
    }

    private function eventDateToCarbon(?EventDateTime $date): ?Carbon
    {
        if (! $date) {
            return null;
        }

        $value = $date->getDateTime() ?: $date->getDate();

        return $value ? Carbon::parse($value)->timezone(config('app.timezone')) : null;
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

    private function calendarStylist(Clinic $clinic): Stylist
    {
        return Stylist::query()->firstOrCreate(
            ['clinic_id' => $clinic->id, 'name' => 'Google', 'is_internal' => true],
            [
                'specialty' => 'Control interno de Google Calendar',
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'work_starts_at' => '00:00',
                'work_ends_at' => '23:59',
                'is_active' => true,
            ]
        );
    }
}
