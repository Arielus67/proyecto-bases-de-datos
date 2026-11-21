@extends('layouts.operativo')

@section('titulo', 'Facturas emitidas')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/modulos/caja.css') }}">
@endpush

@section('contenido')

    <div class="caja-barra">
        <a href="{{ route('caja.index') }}" class="btn-turno">
            <i class="ti ti-arrow-left"></i> Volver a caja
        </a>
    </div>

    <section class="caja-lista">
        <h2 class="caja-titulo">Facturas de los últimos 7 días</h2>
        <p class="caja-subtitulo">Selecciona una factura para ver o reimprimir su ticket.</p>

        <div class="caja-grid">
            @forelse ($facturas as $f)
                <a href="{{ route('caja.ticket', $f->numero_factura) }}" class="pedido-card pedido-card--enlace">
                    <div class="pedido-top">
                        <i class="ti ti-file-invoice"></i>
                        <span class="pedido-estado">{{ $f->estado }}</span>
                    </div>
                    <h3 class="pedido-mesa">{{ $f->numero_factura }}</h3>
                    <p class="pedido-info">Mesa {{ $f->numero_mesa }} · Turno #{{ $f->id_turno }} · {{ \Carbon\Carbon::parse($f->fecha_hora)->format('d/m/Y H:i') }}</p>
                    <p class="pedido-total num">₡<span data-odometro>{{ number_format((float) $f->total, 2, ',', ' ') }}</span></p>
                </a>
            @empty
                <div class="caja-vacio">
                    <i class="ti ti-file-off"></i>
                    <p class="caja-vacio-titulo">No hay facturas recientes</p>
                    <p>Las facturas que emitas aparecerán aquí.</p>
                </div>
            @endforelse
        </div>
    </section>

@endsection
