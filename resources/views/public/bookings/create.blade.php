@extends('public.bookings.layout')

@section('title', 'Reservar en '.$clinic->name.' - Secretary365')

@section('content')
    <style>.booking-team,.booking-services{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.booking-person,.booking-service{min-width:150px;display:flex;align-items:center;gap:9px;padding:9px 12px;border:1px solid var(--line);border-radius:8px;background:#fff;cursor:pointer;text-align:left}.booking-person.selected,.booking-service.selected{border-color:#111827;background:#f3f4f6}.booking-avatar,.booking-service-image{width:42px;height:42px;display:grid;place-items:center;flex:0 0 42px;overflow:hidden;border-radius:50%;background:#f3f4f6;color:#111827;font-weight:900}.booking-service-image{width:58px;height:58px;flex-basis:58px;border-radius:9px;font-size:10px}.booking-avatar img,.booking-service-image img{width:100%;height:100%;object-fit:cover}.booking-person b,.booking-person small,.booking-service b,.booking-service small{display:block}.booking-person small,.booking-service small{margin-top:2px;color:var(--muted)}</style>
    <section class="hero">
        <h1>Reservar en {{ $clinic->name }}</h1>
        <p>Elige servicio, fecha y horario disponible. Luego deja tus datos para confirmar la cita.</p>
    </section>

    @if (session('booking_status'))
        <section class="card notice">
            {{ session('booking_status') }}
        </section>
    @endif

    @if ($googleCalendarError)
        <section class="card" style="border-color:#fbbf24;background:#fffbeb;color:#92400e;font-weight:900;">
            No pudimos sincronizar Google Calendar en este momento. Los horarios se calcularon con la agenda local del salon.
        </section>
    @endif

    @if ($errors->any())
        <section class="card" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:900;">
            Revisa los campos marcados antes de confirmar la cita.
        </section>
    @endif

    @if ($offerRecipient)
        <section class="card" style="border-color:#f9a8d4;background:#fff7fb;">
            <small style="color:#c0265a;font-weight:900;">OFERTA FLASH</small>
            <h2 style="margin:6px 0;">{{ $offerRecipient->campaign->discount_percent }}% de descuento en {{ $offerRecipient->campaign->service?->name }}</h2>
            <p class="subtitle" style="margin:0;">Precio final: <b>{{ $offerRecipient->campaign->discounted_price_cents !== null ? number_format($offerRecipient->campaign->discounted_price_cents / 100, 2).' EUR' : 'Consultar' }}</b>. Valida hasta {{ $offerRecipient->campaign->expires_at->timezone($clinic->localTimezone())->format('d/m/Y H:i') }}.</p>
        </section>
    @endif

    <section class="card">
        <form id="availability-filter" method="GET" action="/salones/{{ $clinic->id }}/reservar" class="grid-3" style="align-items:end;">
            @if ($offerRecipient)<input type="hidden" name="offer" value="{{ $offerRecipient->token }}">@endif
            <div>
                <label for="service_id_filter">Servicio</label>
                <select id="service_id_filter" name="service_id" @disabled($offerRecipient)>
                    <option value="">Cita general</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected($selectedService?->id === $service->id)>
                            {{ $service->name }} - {{ $service->duration_minutes }} min
                        </option>
                    @endforeach
                </select>
                @if ($offerRecipient)
                    <input type="hidden" name="service_id" value="{{ $selectedService?->id }}">
                    <small>Esta oferta es exclusiva para el servicio indicado.</small>
                @endif
            </div>
            <div>
                <label for="stylist_id_filter">Especialista</label>
                <select id="stylist_id_filter" name="stylist_id">
                    <option value="">Cualquier disponible</option>
                    @foreach ($stylists as $stylist)
                        <option value="{{ $stylist->id }}" @selected($selectedStylist?->id === $stylist->id)>
                            {{ $stylist->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <input type="hidden" name="date" value="{{ $selectedDate->format('Y-m-d') }}">
            <button class="btn" type="submit">Actualizar horarios</button>
        </form>
        @if($services->isNotEmpty() && ! $offerRecipient)
            <div class="booking-services" aria-label="Elegir servicio">
                @foreach($services as $service)
                    <button class="booking-service {{ $selectedService?->id === $service->id ? 'selected' : '' }}" type="button" data-service-card="{{ $service->id }}">
                        <span class="booking-service-image">@if($service->imageUrl())<img src="{{ $service->imageUrl() }}" alt="Resultado de {{ $service->name }}">@else{{ mb_strtoupper(mb_substr($service->name, 0, 2)) }}@endif</span>
                        <span><b>{{ $service->name }}</b><small>{{ $service->duration_minutes }} min{{ $service->price_cents !== null ? ' · '.number_format($service->price_cents / 100, 2).' EUR' : '' }}</small></span>
                    </button>
                @endforeach
            </div>
        @endif
        @if($stylists->isNotEmpty())
            <div class="booking-team" aria-label="Elegir especialista">
                @foreach($stylists as $stylist)
                    <button class="booking-person {{ $selectedStylist?->id === $stylist->id ? 'selected' : '' }}" type="button" data-stylist-card="{{ $stylist->id }}">
                        <span class="booking-avatar">@if($stylist->avatarUrl())<img src="{{ $stylist->avatarUrl() }}" alt="Foto de {{ $stylist->name }}">@else{{ $stylist->initials() }}@endif</span>
                        <span><b>{{ $stylist->name }}</b><small>{{ $stylist->specialty ?: 'Especialista' }}</small></span>
                    </button>
                @endforeach
            </div>
        @endif
    </section>

    <form method="POST" action="/salones/{{ $clinic->id }}/reservar" class="card">
        @csrf
        <input type="hidden" name="service_id" value="{{ $selectedService?->id }}">
        <input type="hidden" name="stylist_id" value="{{ $selectedStylist?->id }}">
        @if ($offerRecipient)<input type="hidden" name="offer_token" value="{{ $offerRecipient->token }}">@endif

        @if($selectedService?->activeAddons?->isNotEmpty())
            <section>
                <h2>Personaliza tu servicio</h2>
                <p class="subtitle">Elige solo los extras que quieras agregar.</p>
                <div style="display:grid;gap:9px;margin-top:12px;">
                    @foreach($selectedService->activeAddons as $addon)
                        <label style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px;border:1px solid var(--line);border-radius:8px;cursor:pointer;">
                            <span style="display:flex;align-items:center;gap:9px;"><input type="checkbox" name="addon_ids[]" value="{{ $addon->id }}" style="width:auto;" @checked(in_array($addon->id, old('addon_ids', [])))><b>{{ $addon->name }}</b></span>
                            <strong>+{{ number_format($addon->price_cents / 100, 2) }} EUR</strong>
                        </label>
                    @endforeach
                </div>
            </section>
        @endif

        <section>
            <h2>Fecha</h2>
            <div class="slots" style="margin-top:12px;">
                @foreach ($dates as $date)
                    <a class="btn {{ $date->isSameDay($selectedDate) ? 'primary' : '' }}" href="/salones/{{ $clinic->id }}/reservar?{{ http_build_query(['service_id' => $selectedService?->id, 'stylist_id' => $selectedStylist?->id, 'date' => $date->format('Y-m-d'), 'offer' => $offerRecipient?->token]) }}">
                        {{ ucfirst($date->locale('es')->isoFormat('ddd D MMM')) }}
                    </a>
                @endforeach
            </div>
        </section>

        <section style="padding:14px;border:1px solid var(--line);border-radius:8px;background:var(--soft);">
            <b>Ofertas y novedades (opcional)</b>
            <p class="subtitle" style="margin:5px 0 10px;">Puedes elegir cómo recibir promociones del salón. Esto no afecta a los avisos de tu cita.</p>
            <label style="display:flex;gap:8px;align-items:center;margin:7px 0;"><input type="hidden" name="marketing_email_consent" value="0"><input type="checkbox" name="marketing_email_consent" value="1" style="width:auto;" @checked(old('marketing_email_consent'))> Quiero recibir ofertas por correo</label>
            <label style="display:flex;gap:8px;align-items:center;margin:7px 0;"><input type="hidden" name="marketing_sms_consent" value="0"><input type="checkbox" name="marketing_sms_consent" value="1" style="width:auto;" @checked(old('marketing_sms_consent'))> Quiero recibir ofertas por SMS</label>
        </section>

        <section>
            <h2>Horario disponible</h2>
            <p class="subtitle">Los horarios se calculan con la agenda real, la duracion del servicio y la jornada configurada de los especialistas.</p>
            <div class="slots" style="margin-top:12px;">
                @forelse ($availableSlots as $slot)
                    <label class="slot">
                        <input type="radio" name="starts_at" value="{{ $slot->format('Y-m-d H:i:s') }}" @checked(old('starts_at') === $slot->format('Y-m-d H:i:s')) required>
                        <span>{{ $slot->format('g:i A') }}</span>
                    </label>
                @empty
                    <p class="subtitle">No hay horarios disponibles para esta fecha. Prueba con otro dia.</p>
                @endforelse
            </div>
            @error('starts_at') <div class="error">{{ $message }}</div> @enderror
        </section>

        <section class="grid-2">
            <div>
                <label for="first_name">Nombre</label>
                <input id="first_name" name="first_name" value="{{ old('first_name', $offerRecipient?->client?->first_name) }}" required>
                @error('first_name') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="last_name">Apellido</label>
                <input id="last_name" name="last_name" value="{{ old('last_name', $offerRecipient?->client?->last_name) }}">
                @error('last_name') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="phone">Telefono movil</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone', $offerRecipient?->client?->phone) }}" required>
                @error('phone') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="email">Correo electronico</label>
                <input id="email" name="email" type="email" value="{{ old('email', $offerRecipient?->client?->email) }}">
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
        </section>

        <section>
            <label for="client_comments">Notas para el salon</label>
            <textarea id="client_comments" name="client_comments" placeholder="Cuéntanos si tienes alguna preferencia o detalle importante.">{{ old('client_comments') }}</textarea>
            @error('client_comments') <div class="error">{{ $message }}</div> @enderror
        </section>

        <button class="btn primary" type="submit" @disabled($availableSlots->isEmpty())>Confirmar cita</button>
    </form>
    <script>document.querySelectorAll('[data-stylist-card]').forEach(card=>card.addEventListener('click',()=>{document.getElementById('stylist_id_filter').value=card.dataset.stylistCard;document.getElementById('availability-filter').requestSubmit();}));document.querySelectorAll('[data-service-card]').forEach(card=>card.addEventListener('click',()=>{document.getElementById('service_id_filter').value=card.dataset.serviceCard;document.getElementById('availability-filter').requestSubmit();}));</script>
@endsection
