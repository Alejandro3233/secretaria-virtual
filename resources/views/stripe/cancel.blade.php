@extends('layouts.app')

@section('title', 'Pago cancelado - Secretary365')
@section('page_title', 'Pago cancelado')
@section('page_subtitle', 'No se realizo ningun cambio en tu plan.')
@section('page_actions')
    <a class="btn" href="/#planes">Ver planes</a>
@endsection

@section('content')
    <section class="card">
        Puedes volver a elegir un plan cuando quieras. Tu salon sigue funcionando con el estado actual.
    </section>
@endsection
