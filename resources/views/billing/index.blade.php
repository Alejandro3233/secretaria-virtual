@extends('layouts.app')

@section('title', 'Facturacion - Secretaria Virtual')
@section('page_title', 'Facturacion')
@section('page_subtitle', 'Consulta pagos, facturas y documentos emitidos por Stripe.')
@section('page_actions')
    <a class="btn" href="/#planes">Cambiar plan</a>
@endsection

@section('content')
    @if (session('billing_error') || $billingError)
        <section class="card" style="margin-bottom:18px;border-color:#fecaca;background:#fef2f2;color:#991b1b;font-weight:800;">
            {{ session('billing_error') ?: $billingError }}
        </section>
    @endif

    <section class="grid-3" style="margin-bottom:18px;">
        <article class="card">
            <div class="metric-label">Salon</div>
            <div style="margin-top:8px;font-size:22px;font-weight:900;">{{ $clinic->name }}</div>
            <div class="trend">{{ $clinic->email ?? 'Correo pendiente' }}</div>
        </article>
        <article class="card">
            <div class="metric-label">Plan actual</div>
            <div style="margin-top:8px;font-size:22px;font-weight:900;">{{ $clinic->plan?->name ?? 'Sin plan asignado' }}</div>
            <div class="trend">{{ ucfirst($clinic->subscription_status ?? 'pendiente') }}</div>
        </article>
        <article class="card">
            <div class="metric-label">Suscripcion</div>
            <div style="margin-top:8px;font-size:18px;font-weight:900;">
                Compra: {{ $subscriptionSummary['purchased_at']?->format('d/m/Y') ?? 'Pendiente' }}
            </div>
            <div class="trend">
                Proximo pago: {{ $subscriptionSummary['renews_at']?->format('d/m/Y') ?? 'Pendiente' }}
            </div>
        </article>
    </section>

    <section class="card">
        <div class="section-title">
            <div>
                <h2>Facturas</h2>
                <span class="subtitle">Los PDF se descargan desde el documento oficial generado por Stripe.</span>
            </div>
            <span class="status info">{{ $invoices->count() }} factura{{ $invoices->count() === 1 ? '' : 's' }}</span>
        </div>

        @if ($invoices->isEmpty())
            <div class="item">
                <div>
                    <b>No hay facturas disponibles</b>
                    <span>Cuando completes un pago de prueba desde los planes, la factura aparecera aqui si Stripe ya la genero.</span>
                </div>
                <a class="btn primary" href="/#planes">Probar un plan</a>
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Fecha</th>
                        <th>Periodo</th>
                        <th>Importe</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td>
                                <b>{{ $invoice['number'] }}</b><br>
                                <span class="subtitle">{{ $invoice['id'] }}</span>
                            </td>
                            <td>{{ $invoice['created_at']?->format('d/m/Y') ?? 'Pendiente' }}</td>
                            <td>{{ $invoice['period'] }}</td>
                            <td>{{ $invoice['amount'] }}</td>
                            <td>
                                <span class="status {{ $invoice['status'] === 'paid' ? 'ok' : ($invoice['status'] === 'open' ? 'wait' : 'info') }}">
                                    {{ ucfirst($invoice['status']) }}
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    @if ($invoice['hosted_invoice_url'])
                                        <a class="btn" href="{{ $invoice['hosted_invoice_url'] }}" target="_blank" rel="noopener">Ver</a>
                                    @endif
                                    <a class="btn primary" href="/facturacion/facturas/{{ $invoice['id'] }}/pdf">PDF</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
