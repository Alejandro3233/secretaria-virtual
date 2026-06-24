<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Stylist;
use App\Services\AppointmentService;
use App\Services\ScheduleOptimizationService;
use App\Services\StylistScheduleService;
use App\Services\TwilioSmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class ScheduleOptimizationController extends Controller
{
    public function send(Request $request, Appointment $appointment, TwilioSmsService $sms): RedirectResponse
    {
        $clinic = $request->user()->primaryClinic();
        abort_unless($clinic && $appointment->clinic_id === $clinic->id, 404);
        $appointment->load(['client', 'service', 'stylist']);
        $data = $request->validate([
            'proposed_start' => ['required', 'date'],
            'proposed_stylist_id' => ['required', 'integer'],
        ]);
        $target = Carbon::parse($data['proposed_start'], $clinic->localTimezone());
        $targetStylist = $clinic->stylists()->where('is_active', true)->findOrFail($data['proposed_stylist_id']);

        if ($error = $this->validationError($appointment, $target, $targetStylist)) {
            return back()->with('optimization_error', $error);
        }

        $link = URL::temporarySignedRoute('schedule-optimization.show', now()->addHours(48), [
            'appointment' => $appointment->id,
            'target' => $target->timestamp,
            'stylist' => $targetStylist->id,
        ]);
        $clientName = trim(($appointment->client?->first_name ?? '').' '.($appointment->client?->last_name ?? '')) ?: 'Hola';
        $body = $clientName.', tenemos disponible un horario más temprano para tu cita en '.$clinic->name
            .': '.$target->locale('es')->isoFormat('dddd D [de] MMMM [a las] h:mm A')
            .' con '.$targetStylist->name
            .'. Puedes aceptarlo aquí: '.$link;

        try {
            $providerId = $sms->send((string) $appointment->client?->phone, $body);
            if (! $providerId) return back()->with('optimization_error', 'No se pudo enviar el SMS. Revisa el número del cliente y la configuración de Twilio.');

            DB::table('notifications')->insert([
                'clinic_id' => $clinic->id,
                'client_id' => $appointment->client_id,
                'appointment_id' => $appointment->id,
                'channel' => 'sms',
                'event' => 'appointment_optimization_offer',
                'recipient' => $appointment->client?->phone,
                'status' => 'sent',
                'body' => json_encode([
                    'original_start' => $appointment->starts_at->copy()->timezone($clinic->localTimezone())->toIso8601String(),
                    'proposed_start' => $target->toIso8601String(),
                    'proposed_stylist_id' => $targetStylist->id,
                    'message' => $body,
                ], JSON_UNESCAPED_UNICODE),
                'provider_message_id' => $providerId,
                'sent_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            return back()->with('optimization_error', 'No se pudo enviar la propuesta por SMS. Inténtalo de nuevo.');
        }

        return back()->with('optimization_status', 'Propuesta enviada al cliente correctamente. La cita solo cambiará si el cliente la acepta.');
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $appointment->load(['clinic', 'client', 'service', 'stylist']);
        $target = Carbon::createFromTimestamp((int) $request->query('target'), $appointment->clinic->localTimezone());
        $targetStylist = Stylist::query()->where('clinic_id', $appointment->clinic_id)->findOrFail((int) ($request->query('stylist') ?: $appointment->stylist_id));

        return view('public.optimization.show', compact('appointment', 'target', 'targetStylist'));
    }

    public function accept(Request $request, Appointment $appointment, AppointmentService $appointments, ScheduleOptimizationService $optimizer): View
    {
        $appointment->load(['clinic', 'client', 'service', 'stylist']);
        $target = Carbon::createFromTimestamp((int) $request->query('target'), $appointment->clinic->localTimezone());
        $targetStylist = Stylist::query()->where('clinic_id', $appointment->clinic_id)->findOrFail((int) ($request->query('stylist') ?: $appointment->stylist_id));

        if ($error = $this->validationError($appointment, $target, $targetStylist)) {
            $alternative = $optimizer->alternativeFor($appointment);
            $alternativeUrl = $alternative
                ? URL::temporarySignedRoute('schedule-optimization.show', now()->addHours(24), [
                    'appointment' => $appointment->id,
                    'target' => $alternative['proposed_start']->timestamp,
                    'stylist' => $alternative['stylist']->id,
                ])
                : null;

            return view('public.optimization.status', [
                'appointment' => $appointment,
                'accepted' => false,
                'message' => $error,
                'alternative' => $alternative,
                'alternativeUrl' => $alternativeUrl,
            ]);
        }

        $duration = $appointment->starts_at->diffInMinutes($appointment->ends_at ?? $appointment->starts_at->copy()->addMinutes(60));
        $appointments->update($appointment, [
            'starts_at' => $target,
            'ends_at' => $target->copy()->addMinutes($duration),
            'stylist_id' => $targetStylist->id,
        ]);

        DB::table('notifications')->insert([
            'clinic_id' => $appointment->clinic_id,
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'channel' => 'web',
            'event' => 'appointment_optimization_accepted',
            'recipient' => $appointment->client?->phone ?: $appointment->client?->email ?: 'unknown',
            'status' => 'received',
            'body' => json_encode(['accepted_start' => $target->toIso8601String()]),
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return view('public.optimization.status', [
            'appointment' => $appointment->fresh(['clinic', 'client', 'service', 'stylist']),
            'accepted' => true,
            'message' => 'Tu cita se adelantó correctamente.',
            'alternative' => null,
            'alternativeUrl' => null,
        ]);
    }

    private function validationError(Appointment $appointment, Carbon $target, Stylist $targetStylist): ?string
    {
        if (! $appointment->client?->phone) return 'La cita necesita un teléfono válido.';
        $currentStart = $appointment->starts_at->copy()->timezone($appointment->clinic->localTimezone());
        if ($target->isPast() || ! $target->isSameDay($currentStart)) return 'Esta propuesta ya no está disponible.';
        if (! $target->lessThan($appointment->starts_at)) {
            return 'La cita cambió de horario y esta propuesta ya no sirve para adelantarla.';
        }

        $duration = $appointment->starts_at->diffInMinutes($appointment->ends_at ?? $appointment->starts_at->copy()->addMinutes(60));
        $targetEnd = $target->copy()->addMinutes($duration);
        $scheduleError = app(StylistScheduleService::class)->validationMessage($targetStylist, $target, $targetEnd);
        if ($scheduleError) return $scheduleError;

        $conflict = Appointment::query()
            ->where('clinic_id', $appointment->clinic_id)
            ->where('stylist_id', $targetStylist->id)
            ->where('id', '!=', $appointment->id)
            ->whereNotIn('status', ['cancelled', 'canceled'])
            ->where('starts_at', '<', $targetEnd->copy()->timezone(config('app.timezone')))
            ->where('ends_at', '>', $target->copy()->timezone(config('app.timezone')))
            ->exists();

        return $conflict ? 'Ese horario acaba de ocuparse. La cita original no ha cambiado.' : null;
    }
}
