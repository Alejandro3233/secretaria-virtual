<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Services\ClinicResolver;
use App\Services\ExcelWorkbookService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ActivityExportController extends Controller
{
    public function __invoke(Request $request, ClinicResolver $clinics, ExcelWorkbookService $excel): Response
    {
        $clinic = $clinics->currentOrCreate($request->user());
        $timezone = $clinic->localTimezone();

        $appointments = Appointment::query()
            ->with(['client', 'service', 'stylist'])
            ->where('clinic_id', $clinic->id)
            ->orderByDesc('starts_at')
            ->get();

        $appointmentRows = [[
            'ID', 'Cliente', 'Telefono', 'Correo', 'Servicio', 'Profesional', 'Inicio', 'Fin', 'Duracion (min)',
            'Estado', 'Origen', 'Motivo', 'Llamada recordatorio', 'SMS recordatorio', 'Deposito', 'Comentarios cliente',
            'Notas internas', 'Creada', 'Actualizada',
        ]];

        foreach ($appointments as $appointment) {
            $start = $appointment->starts_at?->copy()->timezone($timezone);
            $end = $appointment->ends_at?->copy()->timezone($timezone);
            $appointmentRows[] = [
                $appointment->id,
                trim(($appointment->client?->first_name ?? '').' '.($appointment->client?->last_name ?? '')),
                $appointment->client?->phone,
                $appointment->client?->email,
                $appointment->service?->name,
                $appointment->stylist?->name,
                $start?->format('d/m/Y H:i'),
                $end?->format('d/m/Y H:i'),
                $start && $end ? $start->diffInMinutes($end) : null,
                $appointment->status,
                $appointment->source,
                $appointment->reason,
                $appointment->reminder_call_enabled ? 'Si' : 'No',
                $appointment->reminder_sms_enabled ? 'Si' : 'No',
                $appointment->deposit_cents !== null ? number_format($appointment->deposit_cents / 100, 2, ',', '.') : null,
                $appointment->client_comments,
                $appointment->internal_notes,
                $this->localDate($appointment->created_at, $timezone),
                $this->localDate($appointment->updated_at, $timezone),
            ];
        }

        $voiceRows = [[
            'ID', 'Cita ID', 'Cliente', 'Telefono', 'Fecha de la cita', 'Tipo', 'Estado', 'SID Twilio',
            'Fecha de envio', 'Ultima actualizacion', 'Mensaje', 'Error',
        ]];
        $smsRows = [[
            'ID', 'Cita ID', 'Cliente', 'Destinatario', 'Fecha de la cita', 'Tipo', 'Estado', 'SID Twilio',
            'Fecha de envio', 'Ultima actualizacion', 'Mensaje', 'Error',
        ]];

        $notifications = DB::table('notifications as notifications')
            ->leftJoin('clients', 'clients.id', '=', 'notifications.client_id')
            ->leftJoin('appointments', 'appointments.id', '=', 'notifications.appointment_id')
            ->where('notifications.clinic_id', $clinic->id)
            ->whereIn('notifications.channel', ['voice', 'sms'])
            ->orderByDesc('notifications.created_at')
            ->select([
                'notifications.*', 'clients.first_name', 'clients.last_name', 'appointments.starts_at as appointment_starts_at',
            ])
            ->get();

        foreach ($notifications as $notification) {
            $row = [
                $notification->id,
                $notification->appointment_id,
                trim(($notification->first_name ?? '').' '.($notification->last_name ?? '')),
                $notification->recipient,
                $this->localDate($notification->appointment_starts_at, $timezone),
                $notification->event,
                $notification->status,
                $notification->provider_message_id,
                $this->localDate($notification->sent_at ?? $notification->created_at, $timezone),
                $this->localDate($notification->updated_at, $timezone),
                $notification->body,
                $notification->error,
            ];

            if ($notification->channel === 'voice') {
                $voiceRows[] = $row;
            } else {
                $smsRows[] = $row;
            }
        }

        $contents = $excel->create([
            ['name' => 'Citas', 'rows' => $appointmentRows],
            ['name' => 'Llamadas', 'rows' => $voiceRows],
            ['name' => 'SMS', 'rows' => $smsRows],
        ]);
        $filename = 'secretary365-'.$clinic->id.'-'.now($timezone)->format('Y-m-d').'.xlsx';

        return response($contents, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($contents),
        ]);
    }

    private function localDate(mixed $value, string $timezone): ?string
    {
        return $value ? \Illuminate\Support\Carbon::parse($value)->timezone($timezone)->format('d/m/Y H:i:s') : null;
    }
}
