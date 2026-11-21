@extends('layouts.operativo')

@section('titulo', 'KDS - Pantalla de Cocina')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/modulos/cocina.css') }}">
@endpush

@section('contenido')
    <div class="kds-top">
        <div class="kds-top-info">
            <div class="kds-top-icono">
                <i class="ti ti-tools-kitchen-2"></i>
            </div>
            <div>
                <h2 class="kds-titulo">Kitchen Display System (KDS)</h2>
                <p class="kds-subtitulo">
                    <span class="kds-en-vivo" id="kds-en-vivo"></span>
                    Comandas en tiempo real desde MongoDB · se actualiza cada 5 s (última: <span id="kds-hora-act" class="num">{{ now()->format('H:i:s') }}</span>)
                </p>
            </div>
        </div>

        <button type="button" id="kds-actualizar" class="btn btn--secundario kds-btn-actualizar">
            <i class="ti ti-rotate-clockwise"></i>
            Actualizar pantalla
        </button>
    </div>

    <div class="kds-grid" id="kds-grid" aria-live="polite">
        @include('cocina.comandas.partials.grid')
    </div>
@endsection

@push('scripts')
    @php
        $datosCocina = [
            'urlEstado' => route('cocina.index'),
            'urlActualizar' => url('/cocina/actualizar-estado'),
            'csrf' => csrf_token(),
            'intervalo' => config('restaurante.intervalo_actualizacion_ms'),
        ];
    @endphp
    <script type="application/json" id="datos-cocina">@json($datosCocina)</script>
    <script src="{{ asset('js/modulos/cocina.js') }}"></script>
@endpush
