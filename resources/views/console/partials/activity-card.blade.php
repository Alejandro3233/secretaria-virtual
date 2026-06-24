@php
    $isInbound = $kind === 'inbound';
    $isMessage = in_array($kind, ['sms', 'email'], true);
    $clientName = trim(($item->first_name ?? '').' '.($item->last_name ?? ''));
    $clientName = $clientName ?: ($item->from_phone ?? $item->recipient ?? 'Cliente no identificado');
    $occurredAt = \Illuminate\Support\Carbon::parse($item->sent_at ?? $item->created_at)->timezone($timezone);
    $appointmentStart = ! empty($item->appointment_starts_at)
        ? \Illuminate\Support\Carbon::parse($item->appointment_starts_at)->timezone($timezone)
        : null;
    $appointmentEnd = ! empty($item->appointment_ends_at)
        ? \Illuminate\Support\Carbon::parse($item->appointment_ends_at)->timezone($timezone)
        : null;
    $statusLabel = match ($item->status ?? null) {
        'sent' => 'Enviado',
        'completed', 'answered', 'resolved' => $isInbound ? 'Contestada' : 'Completado',
        'received' => 'Recibida',
        'queued' => 'En cola',
        'initiated' => 'Iniciando',
        'ringing' => 'Sonando',
        'in-progress' => 'En proceso',
        'no-answer' => 'No contestada',
        'busy' => 'Ocupado',
        'failed' => 'Fallido',
        'canceled' => 'Cancelado',
        default => ucfirst((string) ($item->status ?? 'Registrado')),
    };
    $statusClass = match ($item->status ?? null) {
        'sent', 'completed', 'answered', 'resolved' => 'ok',
        'failed', 'busy', 'no-answer', 'canceled' => 'danger',
        'received', 'queued', 'initiated', 'ringing', 'in-progress' => 'info',
        default => 'wait',
    };
    $eventCode = (string) ($isInbound ? ($item->intent ?? '') : ($item->event ?? ''));
    $title = match ($eventCode) {
        'appointment_created' => 'Cita creada',
        'appointment_updated' => 'Cita actualizada',
        'appointment_reconfirmed' => 'Cita reconfirmada',
        'appointment_confirmed' => 'Cita confirmada',
        'appointment_cancelled', 'appointment_canceled' => 'Cita cancelada',
        'appointment_rescheduled' => 'Cita reagendada',
        'appointment_reminder_call' => 'Llamada de recordatorio de cita',
        'appointment_lookup' => null,
        'appointment_reminder_sms' => 'SMS de recordatorio de cita',
        'appointment_reschedule_link' => 'Enlace para reagendar la cita',
        'appointment_client_response' => 'Respuesta del cliente',
        'billing_invoice_paid' => 'Factura pagada',
        'salon_registered' => 'Salón registrado',
        'confirm_appointment', 'appointment_confirmation' => 'Confirmación de cita',
        'cancel_appointment', 'appointment_cancellation' => 'Cancelación de cita',
        'reschedule_appointment', 'appointment_reschedule' => 'Reagendado de cita',
        'create_appointment', 'book_appointment', 'appointment_booking' => 'Reserva de cita',
        'update_appointment' => 'Modificación de cita',
        'cancel_appointment_request' => 'Solicitud para cancelar la cita',
        '' => $isInbound ? 'Llamada recibida' : ($isMessage ? strtoupper($kind) : 'Llamada realizada'),
        default => ucfirst(str_replace('_', ' ', $eventCode)),
    };
    $metadata = $isInbound && ! empty($item->metadata)
        ? json_decode((string) $item->metadata, true)
        : null;
    $handledBy = is_array($metadata) ? ($metadata['handled_by'] ?? null) : null;
    $handledByName = is_array($metadata) ? ($metadata['handled_by_name'] ?? null) : null;
    $callHandlerLabel = match (true) {
        ! $isInbound => null,
        $handledBy === 'salon' => 'Llamada contestada por '.($handledByName ?: 'el salón'),
        $handledBy === 'nora' => 'Llamada contestada por Nora',
        $handledBy === 'pending' && in_array($item->status ?? null, ['answered', 'completed', 'resolved'], true) => 'Llamada contestada por '.($clinic?->name ?: 'el salón'),
        in_array($item->status ?? null, ['queued', 'initiated', 'ringing'], true) => 'Esperando respuesta del salón',
        ($item->status ?? null) === 'in-progress' => 'Llamada en curso',
        ($item->status ?? null) === 'no-answer' => 'Llamada no contestada',
        ($item->status ?? null) === 'busy' => 'Línea ocupada',
        ($item->status ?? null) === 'failed' => 'No se pudo completar la llamada',
        ($item->status ?? null) === 'canceled' => 'Llamada cancelada',
        in_array($item->status ?? null, ['received', 'answered', 'completed', 'resolved'], true) => 'Llamada contestada por Nora',
        default => 'Llamada recibida',
    };
    $callHandlerClass = match ($handledBy) {
        'salon' => 'salon',
        'nora' => 'nora',
        default => 'pending',
    };
    $appointmentStatus = match ($item->appointment_status ?? null) {
        'confirmed' => 'Confirmada',
        'pending' => 'Pendiente',
        'cancelled', 'canceled' => 'Cancelada',
        default => $item->appointment_status ? ucfirst((string) $item->appointment_status) : null,
    };
    $hasClientDetails = ! empty($item->client_email) || ! empty($item->client_address)
        || ! empty($item->notification_preference) || ! empty($item->client_notes)
        || ! empty($item->hair_type) || ! empty($item->preferred_stylist)
        || ! empty($item->color_formula) || ! empty($item->allergies) || ! empty($item->beauty_notes);
    $hasAppointmentDetails = ! empty($item->appointment_id) || $appointmentStart;
    $hasProviderDetails = ! empty($item->provider_message_id) || ! empty($item->twilio_call_sid)
        || ! empty($item->error) || ! empty($metadata);
    $hasContent = ! empty($item->body) || ! empty($item->transcript);
    $responseLabel = match ($item->client_response ?? null) {
        'confirm' => 'Confirmó la cita',
        'cancel' => 'Canceló la cita',
        'reschedule' => 'Reagendó la cita',
        default => 'Sin respuesta registrada',
    };
    $responseClass = match ($item->client_response ?? null) {
        'confirm', 'reschedule' => 'ok',
        'cancel' => 'danger',
        default => 'wait',
    };
    $callResultLabel = match ($item->status ?? null) {
        'completed', 'answered', 'resolved', 'received' => 'Contestó',
        'no-answer' => 'No contestó',
        'busy' => 'Estaba ocupado',
        'failed' => 'Llamada fallida',
        'canceled' => 'Llamada cancelada',
        'queued' => 'En cola',
        'initiated' => 'Iniciando llamada',
        'ringing' => 'Está sonando',
        'in-progress' => 'Llamada en curso',
        'sent' => 'Llamada enviada',
        default => 'Resultado no disponible',
    };
    $callResultDescription = match ($item->status ?? null) {
        'completed', 'answered', 'resolved', 'received' => 'La llamada fue atendida por el destinatario.',
        'no-answer' => 'El destinatario no respondió la llamada.',
        'busy' => 'La línea del destinatario estaba ocupada.',
        'failed' => 'El proveedor no pudo completar la llamada.',
        'canceled' => 'La llamada fue cancelada antes de completarse.',
        'queued', 'initiated', 'ringing', 'in-progress' => 'La llamada todavía no tiene un resultado definitivo.',
        'sent' => 'El proveedor aceptó el envío; no consta todavía si fue atendida.',
        default => 'El proveedor no informó un resultado reconocible.',
    };
@endphp

<article class="activity-card activity-card--{{ $statusClass }}">
    <div class="activity-card-head">
        <div class="activity-time">
            <strong>{{ $occurredAt->format('g:i A') }}</strong>
            <span>{{ $occurredAt->locale('es')->isoFormat('D MMM YYYY') }}</span>
        </div>
        <div class="activity-title">
            <strong>{{ $clientName }}</strong>
            @if ($callHandlerLabel)
                <span class="activity-handler activity-handler--{{ $callHandlerClass }}">{{ $callHandlerLabel }}</span>
            @endif
            @if ($title)
                <span>{{ $title }}</span>
            @endif
        </div>
        <span class="status {{ $statusClass }}">{{ $kind === 'outbound' ? $callResultLabel : $statusLabel }}</span>
    </div>

    <div class="activity-info-grid">
        <section class="activity-info-block">
            <span class="activity-label">Contacto</span>
            <b>{{ $item->recipient ?? $item->from_phone ?? $item->client_phone ?? 'Sin teléfono' }}</b>
            @if ($isInbound && ! empty($item->to_phone))
                <span>Destino: {{ $item->to_phone }}</span>
            @elseif (! $isInbound && ! empty($item->client_phone) && $item->client_phone !== ($item->recipient ?? null))
                <span>Ficha: {{ $item->client_phone }}</span>
            @endif
            @if (! empty($item->client_email) && strcasecmp(trim((string) $item->client_email), trim((string) ($item->recipient ?? ''))) !== 0)
                <span>{{ $item->client_email }}</span>
            @endif
        </section>

        @if ($kind === 'outbound')
            <section class="activity-info-block response-block response-block--{{ $statusClass }}">
                <span class="activity-label">Resultado de la llamada</span>
                <b>{{ $callResultLabel }}</b>
                <span>{{ $callResultDescription }}</span>
            </section>
        @elseif ($isMessage && $hasAppointmentDetails)
            <section class="activity-info-block response-block response-block--{{ $responseClass }}">
                <span class="activity-label">Respuesta del cliente</span>
                <b>{{ $responseLabel }}</b>
                @if (! empty($item->client_responded_at))
                    <span>Respondió el {{ \Illuminate\Support\Carbon::parse($item->client_responded_at)->timezone($timezone)->locale('es')->isoFormat('D MMM YYYY, h:mm A') }}</span>
                @else
                    <span>Todavía no ha respondido desde el enlace enviado.</span>
                @endif
                @if (($item->client_response ?? null) === 'reschedule' && $appointmentStart)
                    <span>Nueva fecha: {{ $appointmentStart->locale('es')->isoFormat('D MMM YYYY, h:mm A') }}</span>
                @endif
            </section>
        @else
            <section class="activity-info-block">
                <span class="activity-label">Registro</span>
                <b>{{ $isMessage ? strtoupper($kind) : ($isInbound ? 'Llamada entrante' : 'Llamada saliente') }}</b>
                @if (! empty($item->created_at))
                    <span>Creado: {{ \Illuminate\Support\Carbon::parse($item->created_at)->timezone($timezone)->format('d/m/Y g:i:s A') }}</span>
                @endif
                @if (! empty($item->sent_at))
                    <span>Enviado: {{ \Illuminate\Support\Carbon::parse($item->sent_at)->timezone($timezone)->format('d/m/Y g:i:s A') }}</span>
                @endif
            </section>
        @endif

        <section class="activity-info-block {{ $hasAppointmentDetails ? '' : 'muted-block' }}">
            <span class="activity-label">Cita relacionada</span>
            @if ($hasAppointmentDetails)
                <b>{{ $item->service_name ?? $item->appointment_reason ?? 'Cita' }}</b>
                @if ($appointmentStart)
                    <span>{{ $appointmentStart->locale('es')->isoFormat('ddd D MMM YYYY, h:mm A') }}@if($appointmentEnd) – {{ $appointmentEnd->format('g:i A') }}@endif</span>
                @endif
                <span>{{ $appointmentStatus ?? 'Estado no indicado' }} · {{ $item->stylist_name ?? 'Sin profesional' }}</span>
            @else
                <b>Sin cita asociada</b>
                <span>Este registro no modificó una cita.</span>
            @endif
        </section>

        @if (($isMessage && $hasAppointmentDetails) || $kind === 'outbound')
            <section class="activity-info-block">
                <span class="activity-label">Registro</span>
                <b>{{ $kind === 'outbound' ? 'Llamada saliente' : strtoupper($kind) }}</b>
                @if (! empty($item->created_at))
                    <span>Creado: {{ \Illuminate\Support\Carbon::parse($item->created_at)->timezone($timezone)->format('d/m/Y g:i:s A') }}</span>
                @endif
                @if (! empty($item->sent_at))
                    <span>Enviado: {{ \Illuminate\Support\Carbon::parse($item->sent_at)->timezone($timezone)->format('d/m/Y g:i:s A') }}</span>
                @endif
            </section>
        @endif

        @if ($hasAppointmentDetails)
            <section class="activity-info-block">
                <span class="activity-label">Servicio y agenda</span>
                <b>{{ $item->service_name ?? 'Servicio no indicado' }}</b>
                @if (! empty($item->duration_minutes))<span>{{ $item->duration_minutes }} min</span>@endif
                @if (isset($item->price_cents) && $item->price_cents !== null)<span>Precio: {{ number_format($item->price_cents / 100, 2, ',', '.') }} €</span>@endif
                @if (! empty($item->chair_station))<span>Puesto: {{ $item->chair_station }}</span>@endif
                @if (isset($item->deposit_cents) && $item->deposit_cents !== null)<span>Depósito: {{ number_format($item->deposit_cents / 100, 2, ',', '.') }} €</span>@endif
            </section>
        @endif
    </div>

    @if ($hasClientDetails || $hasAppointmentDetails || $hasProviderDetails || $hasContent)
        <details class="activity-more">
            <summary>Ver toda la información relacionada</summary>
            @if ($hasContent)
                <section class="activity-message">
                    <span class="activity-label">{{ ! empty($item->transcript) ? 'Transcripción completa' : 'Contenido enviado' }}</span>
                    <p>{{ $item->transcript ?? $item->body }}</p>
                </section>
            @endif
            <div class="activity-more-grid">
                @if ($hasClientDetails)
                    <section>
                        <h3>Ficha del cliente</h3>
                        @if (! empty($item->client_address))<p><b>Dirección:</b> {{ $item->client_address }}</p>@endif
                        @if (! empty($item->notification_preference))<p><b>Preferencia de avisos:</b> {{ ucfirst((string) $item->notification_preference) }}</p>@endif
                        @if (! empty($item->client_notes))<p><b>Notas:</b> {{ $item->client_notes }}</p>@endif
                        @if (! empty($item->hair_type))<p><b>Tipo de cabello:</b> {{ $item->hair_type }}</p>@endif
                        @if (! empty($item->preferred_stylist))<p><b>Profesional preferido:</b> {{ $item->preferred_stylist }}</p>@endif
                        @if (! empty($item->color_formula))<p><b>Fórmula de color:</b> {{ $item->color_formula }}</p>@endif
                        @if (! empty($item->allergies))<p class="activity-error"><b>Alergias:</b> {{ $item->allergies }}</p>@endif
                        @if (! empty($item->beauty_notes))<p><b>Notas de belleza:</b> {{ $item->beauty_notes }}</p>@endif
                    </section>
                @endif
                @if ($hasAppointmentDetails)
                    <section>
                        <h3>Detalles de la cita</h3>
                        @if (! empty($item->appointment_reason))<p><b>Motivo:</b> {{ $item->appointment_reason }}</p>@endif
                        @if (! empty($item->appointment_priority))<p><b>Prioridad:</b> {{ ucfirst((string) $item->appointment_priority) }}</p>@endif
                        @if (! empty($item->appointment_source))<p><b>Origen:</b> {{ ucfirst(str_replace('_', ' ', (string) $item->appointment_source)) }}</p>@endif
                        @if (! empty($item->client_comments))<p><b>Comentarios del cliente:</b> {{ $item->client_comments }}</p>@endif
                        @if (! empty($item->internal_notes))<p><b>Notas internas:</b> {{ $item->internal_notes }}</p>@endif
                        @if (! empty($item->appointment_id))<a class="btn" href="/citas/{{ $item->appointment_id }}/editar">Abrir cita</a>@endif
                    </section>
                    <section>
                        <h3>Profesional</h3>
                        <p><b>Nombre:</b> {{ $item->stylist_name ?? 'Sin asignar' }}</p>
                        @if (! empty($item->stylist_specialty))<p><b>Especialidad:</b> {{ $item->stylist_specialty }}</p>@endif
                        @if (! empty($item->stylist_phone))<p><b>Teléfono:</b> {{ $item->stylist_phone }}</p>@endif
                        @if (! empty($item->stylist_email))<p><b>Email:</b> {{ $item->stylist_email }}</p>@endif
                    </section>
                @endif
                @if ($hasProviderDetails)
                    <section>
                        <h3>Datos técnicos</h3>
                        @if (! empty($item->twilio_call_sid))<p><b>SID de llamada:</b> {{ $item->twilio_call_sid }}</p>@endif
                        @if (! empty($item->provider_message_id))<p><b>ID del proveedor:</b> {{ $item->provider_message_id }}</p>@endif
                        @if (! empty($item->error))<p class="activity-error"><b>Error:</b> {{ $item->error }}</p>@endif
                        @if (is_array($metadata))
                            @foreach ($metadata as $key => $value)
                                @php
                                    $metadataLabel = match ((string) $key) {
                                        'matched_clinic' => 'Salón identificado',
                                        'matched_client' => 'Cliente identificado',
                                        'matched_appointment' => 'Cita identificada',
                                        'handled_by' => 'Atendida por',
                                        'handled_by_name' => 'Responsable',
                                        default => ucfirst(str_replace('_', ' ', (string) $key)),
                                    };
                                    $metadataValue = match ($value) {
                                        true => 'Sí',
                                        false => 'No',
                                        'salon' => 'Salón',
                                        'nora' => 'Nora',
                                        'pending' => 'Pendiente',
                                        default => is_scalar($value) || $value === null
                                            ? ($value ?? '—')
                                            : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                    };
                                @endphp
                                <p><b>{{ $metadataLabel }}:</b> {{ $metadataValue }}</p>
                            @endforeach
                        @endif
                    </section>
                @endif
            </div>
        </details>
    @endif
</article>
