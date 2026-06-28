@extends('layouts.app')

@section('title', 'Pago confirmado - Secretary365')
@section('page_title', 'Pago confirmado')
@section('page_subtitle', 'Stripe confirmo la suscripcion del salon.')
@section('page_actions')
    <a class="btn primary" href="/consola">Ir a consola</a>
@endsection

@section('content')
    @if ($error)
        <section class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            {{ $error }}
        </section>
    @endif

    <section class="card" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534;font-weight:800;">
        {{ $status }}
    </section>

    <section class="card" style="margin-top:18px;">
        <div class="section-title">
            <div>
                <h2>{{ $clinic?->name ?? 'Salon' }}</h2>
                <span class="subtitle">Puedes revisar la factura generada por Stripe en el apartado de facturacion.</span>
            </div>
            <a class="btn primary" href="/facturacion">Ver facturacion</a>
        </div>
    </section>
@endsection
