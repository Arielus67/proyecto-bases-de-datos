@extends('layouts.operativo')

@section('titulo', 'Gestión de Comandas')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/modulos/mesero.css') }}">
@endpush

@section('contenido')

    <div class="comandas-layout">

        <section class="comandas-mesas" id="mesas-estado">
            @include('mesero.mesas.partials.estado')
        </section>

        <aside class="ticket-panel">
            <div class="ticket-header">
                <div class="ticket-header-top">
                    <i class="ti ti-receipt"></i>
                    <h2>Detalle del pedido</h2>
                </div>
                <div class="ticket-fecha">
                    <span>Fecha</span>
                    <span id="ticket-fecha">{{ now()->format('d/m/Y H:i') }}</span>
                </div>
            </div>

            <div class="ticket-body">
                <span class="ticket-label">Mesa seleccionada</span>
                <p id="mesa-seleccionada" class="ticket-mesa">Ninguna</p>

                <h3 class="ticket-subtitulo">Productos</h3>

                <div id="ticket-productos">
                    <div class="ticket-vacio">
                        <i class="ti ti-shopping-cart"></i>
                        <span>Selecciona una mesa y agrega productos.</span>
                    </div>
                </div>

                <div class="ticket-fila">
                    <span class="gris">Subtotal</span>
                    <span id="ticket-subtotal" class="num">₡0,00</span>
                </div>
                <div class="ticket-fila">
                    <span class="gris">Impuestos</span>
                    <span id="ticket-impuestos" class="num">₡0,00</span>
                </div>
                <div class="ticket-total">
                    <span>Total</span>
                    <span id="ticket-total" class="num">₡0,00</span>
                </div>

                <button type="button" id="registrar-comanda" class="btn-registrar" disabled>
                    Registrar comanda
                </button>
            </div>
        </aside>

    </div>

    <div id="comanda-overlay" class="comanda-overlay" aria-hidden="true">
        <div class="comanda-modal" role="dialog" aria-modal="true" aria-label="Menú de productos">
            <div id="contenido-menu">
                <div class="menu-cargando">Selecciona una mesa para cargar el menú.</div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    @php
        $datosMesero = [
            'csrf' => csrf_token(),
            'urlMesas' => url('/mesero/mesas'),
            'urlPedidos' => url('/mesero/pedidos'),
            'urlGuardar' => route('mesero.guardar'),
            'urlEstado' => route('mesero.index'),
            'intervalo' => config('restaurante.intervalo_actualizacion_ms'),
        ];
    @endphp
    <script type="application/json" id="datos-mesero">@json($datosMesero)</script>
    <script src="{{ asset('js/modulos/mesero.js') }}"></script>
@endpush
