@extends('layouts.operativo')

@section('titulo', 'Caja')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/modulos/caja.css') }}">
@endpush

@section('contenido')

    <div class="caja-barra">
        <a href="{{ route('caja.facturas') }}" class="btn-turno">
            <i class="ti ti-file-invoice"></i> Facturas emitidas
        </a>
        <button type="button" id="abrir-cierre" class="btn-turno">
            <i class="ti ti-lock"></i> Cerrar turno
        </button>
    </div>

    <div class="caja-layout">

                <section class="caja-lista">
            <h2 class="caja-titulo">Pedidos pendientes de cobro</h2>
            <p class="caja-subtitulo" id="caja-estado">Selecciona un pedido para cobrar.</p>

            <div class="caja-grid" id="caja-grid">
                @include('cajero.facturas.partials.pedidos')
            </div>
        </section>

                <aside class="cobro-panel">
            <div class="cobro-header">
                <div class="cobro-header-top">
                    <i class="ti ti-cash"></i>
                    <h2>Cobro del pedido</h2>
                </div>
                <div class="cobro-header-pie">
                    <span id="cobro-titulo">Ninguno seleccionado</span>
                </div>
            </div>

            <div class="cobro-body">

                <div id="cobro-vacio" class="cobro-vacio">
                    <i class="ti ti-receipt-2"></i>
                    <span>Selecciona un pedido para cobrar.</span>
                </div>

                <div id="cobro-form" class="cobro-form" hidden>

                    <h3 class="cobro-sub">Productos</h3>
                    <div id="cobro-items"></div>

                    @if ($cajeros->isEmpty())
                        <div class="cobro-alerta">
                            @if ($supervisaCajas)
                                No hay ningún cajero con turno abierto.
                                @accesible(route('admin.turnos.create'))
                                    <a href="{{ route('admin.turnos.create') }}">Abrir un turno</a>
                                @endaccesible
                            @else
                                No tienes un turno de caja abierto. Solicita al administrador que lo abra para poder cobrar.
                            @endif
                        </div>
                    @endif

                    <label class="cobro-label" for="cobro-cajero">Cajero</label>
                    <select id="cobro-cajero" class="cobro-input">
                        @foreach ($cajeros as $c)
                            <option value="{{ $c->id_empleado }}">{{ $c->nombre }}</option>
                        @endforeach
                    </select>

                    <label class="cobro-label" for="cobro-cliente">Cliente (opcional)</label>
                    <select id="cobro-cliente" class="cobro-input">
                        <option value="">Sin cliente</option>
                        @foreach ($clientes as $cl)
                            <option value="{{ $cl->id_cliente }}">{{ $cl->nombre }}</option>
                        @endforeach
                    </select>

                    <label class="cobro-label" for="cobro-descuento">Descuento (%)</label>
                    <input type="number" id="cobro-descuento" class="cobro-input" min="0" max="100"
                        step="0.01" value="0">

                    <div class="cobro-fila"><span class="gris">Subtotal</span><span id="r-subtotal" class="num">₡0,00</span></div>
                    <div class="cobro-fila"><span class="gris">Descuento</span><span id="r-descuento" class="num">₡0,00</span></div>
                    <div class="cobro-fila"><span class="gris">IVA (13%)</span><span id="r-iva" class="num">₡0,00</span></div>
                    <div class="cobro-fila"><span class="gris">Servicio (10%)</span><span id="r-servicio" class="num">₡0,00</span></div>
                    <div class="cobro-total"><span>Total</span><span id="r-total" class="num">₡0,00</span></div>

                    <h3 class="cobro-sub cobro-sub--separado">Pagos</h3>
                    <div id="cobro-pagos"></div>
                    <button type="button" id="agregar-pago" class="btn-agregar-pago">
                        <i class="ti ti-plus"></i> Agregar pago
                    </button>

                    <div class="cobro-fila"><span class="gris">Pagado</span><span id="r-pagado" class="num">₡0,00</span></div>
                    <div class="cobro-fila"><span class="gris" id="r-dif-label">Falta</span><span
                            id="r-dif" class="num">₡0,00</span></div>

                    <button type="button" id="btn-cobrar" class="btn-cobrar" disabled><i class="ti ti-printer"></i> <span>Cobrar y emitir factura</span></button>
                </div>
            </div>
        </aside>

    </div>

    <div id="turno-overlay" class="turno-overlay" aria-hidden="true">
        <div class="turno-modal" role="dialog" aria-modal="true" aria-label="Cierre de turno">
            <div class="turno-header">
                <h2>Cierre de turno</h2>
                <button type="button" id="turno-x" class="boton-icono turno-x" aria-label="Cerrar"><i class="ti ti-x"></i></button>
            </div>

            <div class="turno-body">
                @if ($cajeros->isEmpty())
                    <p class="turno-aviso">No hay ningún turno abierto para cerrar.</p>
                @else
                    <label class="cobro-label cobro-label--primero" for="turno-select">Turno / cajero</label>
                    <select id="turno-select" class="cobro-input">
                        @foreach ($cajeros as $c)
                            <option value="{{ $c->id_turno }}">{{ $c->nombre }} (turno #{{ $c->id_turno }})
                            </option>
                        @endforeach
                    </select>

                    <div id="turno-detalle" class="turno-detalle">Cargando...</div>

                    <label class="cobro-label" for="turno-contado">Efectivo contado en caja (₡)</label>
                    <input type="number" id="turno-contado" class="cobro-input" min="0" step="0.01"
                        value="0">

                    <div class="cobro-fila">
                        <span class="gris" id="turno-dif-label">Diferencia</span>
                        <span id="turno-dif" class="num">₡0,00</span>
                    </div>

                    <div class="turno-acciones">
                        <button type="button" id="turno-cancelar" class="btn btn--secundario">Cancelar</button>
                        <button type="button" id="turno-confirmar" class="btn btn--peligro btn-cerrar-turno" disabled>Cerrar turno</button>
                    </div>
                @endif
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    @php
        $datosCaja = [
            'metodos' => $metodos,
            'hayCajero' => $cajeros->isNotEmpty(),
            'urlCobrar' => url('/caja/cobrar'),
            'urlTurnos' => url('/caja/turnos'),
            'urlEstado' => route('caja.index'),
            'csrf' => csrf_token(),
            'intervalo' => config('restaurante.intervalo_actualizacion_ms'),
        ];
    @endphp
    <script type="application/json" id="datos-caja">@json($datosCaja)</script>
    <script src="{{ asset('js/modulos/caja.js') }}"></script>
@endpush
