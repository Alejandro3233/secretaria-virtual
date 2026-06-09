@extends('public.bookings.layout')

@section('title', 'Reservar en '.$clinic->name.' - Secretaria Virtual')

@section('content')
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

    <section class="card">
        <form method="GET" action="/salones/{{ $clinic->id }}/reservar" class="grid-3" style="align-items:end;">
            <div>
                <label for="service_id_filter">Servicio</label>
                <select id="service_id_filter" name="service_id">
                    <option value="">Cita general</option>
                    @foreach ($services as $service)
                        <option value="{{ $service->id }}" @selected($selectedService?->id === $service->id)>
                            {{ $service->name }} - {{ $service->duration_minutes }} min
                        </option>
                    @endforeach
                </select>
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
    </section>

    <form method="POST" action="/salones/{{ $clinic->id }}/reservar" class="card">
        @csrf
        <input type="hidden" name="service_id" value="{{ $selectedService?->id }}">
        <input type="hidden" name="stylist_id" value="{{ $selectedStylist?->id }}">

        <section>
            <h2>Fecha</h2>
            <div class="slots" style="margin-top:12px;">
                @foreach ($dates as $date)
                    <a class="btn {{ $date->isSameDay($selectedDate) ? 'primary' : '' }}" href="/salones/{{ $clinic->id }}/reservar?{{ http_build_query(['service_id' => $selectedService?->id, 'stylist_id' => $selectedStylist?->id, 'date' => $date->format('Y-m-d')]) }}">
                        {{ ucfirst($date->locale('es')->isoFormat('ddd D MMM')) }}
                    </a>
                @endforeach
            </div>
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
                <input id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                @error('first_name') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="last_name">Apellido</label>
                <input id="last_name" name="last_name" value="{{ old('last_name') }}">
                @error('last_name') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="phone">Telefono movil</label>
                <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" required>
                @error('phone') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div>
                <label for="email">Correo electronico</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}">
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
@endsection
