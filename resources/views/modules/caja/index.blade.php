@extends('app')

@section('titulo', 'Módulo de Caja - Cobro y Facturación')

@section('botones')
    <button type="button" class="btn btn-outline-light btn-sm fw-bold me-2" onclick="location.reload()">
        <i class="fas fa-sync-alt me-1"></i> Actualizar
    </button>
    <button type="button" class="btn btn-danger btn-sm fw-bold">
        <i class="fas fa-lock me-1"></i> Cerrar Turno
    </button>
@endsection

@section('contenido')
<div class="row g-3">
    {{-- COLUMNA IZQUIERDA: Mesas pendientes de cobro --}}
    <div class="col-lg-6 col-xl-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0 fs-6"><i class="fas fa-concierge-bell me-2"></i>Mesas por Cobrar</h5>
            </div>
            <div class="card-body bg-light">
                <div class="row row-cols-1 row-cols-md-2 g-3">
                    {{-- Iteramos sobre los pedidos que están listos o entregados --}}
                    @forelse ($pedidos_pendientes as $pedido)
                        <div class="col">
                            <div class="card h-100 border-primary shadow-sm cursor-pointer btn-select-pedido"
                                 onclick="selectPedido({{ $pedido->id_pedido }}, {{ json_encode($pedido->mesa->numero_mesa ?? 'N/A') }}, {{ $pedido->subtotal }}, {{ json_encode($pedido->detalles) }})"
                                 id="pedido-card-{{ $pedido->id_pedido }}">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="fw-bold mb-0 text-primary"><i class="fas fa-chair me-1"></i> Mesa {{ $pedido->mesa->numero_mesa ?? 'N/A' }}</h6>
                                        <span class="badge bg-secondary">Pedido #{{ $pedido->id_pedido }}</span>
                                    </div>
                                    <p class="small text-muted mb-1"><i class="fas fa-user me-1"></i> Mesero: {{ $pedido->empleado->nombre ?? 'N/A' }}</p>
                                    <h5 class="fw-bold text-success text-end mb-0 mt-3">
                                        Subtotal: ₡{{ number_format($pedido->subtotal, 0, ',', '.') }}
                                    </h5>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center text-muted py-5">
                            <i class="fas fa-check-circle fa-3x mb-3 text-success d-block"></i>
                            <h5>No hay cuentas pendientes por cobrar.</h5>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- COLUMNA DERECHA: Detalle de Facturación --}}
    <div class="col-lg-6 col-xl-5">
        <div class="card shadow-sm sticky-top" style="top: 1rem;">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-6"><i class="fas fa-file-invoice-dollar me-2"></i>Facturación</h5>
                <span class="badge bg-light text-primary fw-bold" id="lblMesaFactura">Seleccione una mesa</span>
            </div>

            <div class="card-body p-3">
                <form id="formCaja" action="{{ route('caja.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="id_pedido" id="inputIdPedido" value="">
                    <input type="hidden" name="subtotal_base" id="inputSubtotalBase" value="0">

                    {{-- Resumen de la orden seleccionada --}}
                    <div class="table-responsive mb-3" style="max-height: 200px; overflow-y: auto;">
                        <table class="table table-sm table-striped align-middle text-muted small" id="tablaDetallesPedido">
                            <thead>
                                <tr>
                                    <th>Cant.</th>
                                    <th>Producto</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody id="detallesPedidoBody">
                                <tr>
                                    <td colspan="3" class="text-center py-3">Esperando selección...</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    {{-- Datos del Cliente (Opcional) --}}
                    <div class="row g-2 mb-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold mb-1">Cliente (Opcional)</label>
                            <select name="id_cliente" class="form-select form-select-sm">
                                <option value="">Consumidor Final</option>
                                @foreach ($clientes as $cliente)
                                    <option value="{{ $cliente->id_cliente }}">{{ $cliente->nombre }} {{ $cliente->apellido }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Controles financieros --}}
                    <div class="row g-2 mb-3 border-bottom pb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Método de Pago</label>
                            <select name="id_metodo_pago" class="form-select form-select-sm" required>
                                @foreach ($metodos_pago as $metodo)
                                    <option value="{{ $metodo->id_metodo_pago }}">{{ $metodo->nombre_metodo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-1">Descuento (%)</label>
                            <input type="number" name="descuento" id="inputDescuento" class="form-control form-control-sm text-end" value="0" min="0" max="100" onchange="calcularTotales()">
                        </div>
                    </div>

                    {{-- Cálculos finales --}}
                    <div class="bg-light p-3 rounded mb-3">
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Subtotal:</span>
                            <span class="fw-bold" id="txtSubtotal">₡0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Descuento:</span>
                            <span class="fw-bold text-danger" id="txtDescuento">-₡0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Servicio (10%):</span>
                            <span id="txtServicio">₡0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">I.V.A. (13%):</span>
                            <span id="txtIva">₡0</span>
                        </div>
                        <hr class="my-2 border-dark">
                        <div class="d-flex justify-content-between fs-4 fw-bold text-success mb-2">
                            <span>Total a Pagar:</span>
                            <span id="txtTotal">₡0</span>
                        </div>

                        {{-- Control de Vuelto --}}
                        <div class="input-group input-group-sm mt-3">
                            <span class="input-group-text bg-white fw-bold">Paga con: ₡</span>
                            <input type="number" name="monto_entregado" id="inputPagaCon" class="form-control text-end fs-6" placeholder="0" onkeyup="calcularVuelto()">
                        </div>
                        <div class="d-flex justify-content-between mt-2 fw-bold text-primary">
                            <span>Su Vuelto:</span>
                            <span class="fs-5" id="txtVuelto">₡0</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm" id="btnCobrar" disabled>
                        <i class="fas fa-print me-1"></i> Cobrar y Emitir Factura
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let currentSubtotal = 0;
    let currentTotal = 0;

    // Función al seleccionar una tarjeta de pedido
    function selectPedido(id_pedido, numero_mesa, subtotal, detalles) {
        // Asignar valores ocultos
        $('#inputIdPedido').val(id_pedido);
        $('#inputSubtotalBase').val(subtotal);
        currentSubtotal = parseFloat(subtotal);

        // Actualizar UI
        $('#lblMesaFactura').text(`Mesa ${numero_mesa}`);
        $('.btn-select-pedido').removeClass('bg-primary text-white').addClass('border-primary');
        $(`#pedido-card-${id_pedido}`).removeClass('border-primary').addClass('bg-primary text-white');

        // Llenar tabla de detalles
        const tbody = $('#detallesPedidoBody');
        tbody.empty();

        if (detalles.length > 0) {
            detalles.forEach(item => {
                const montoTotalItem = item.cantidad * item.precio_unitario;
                const nombre = item.producto ? item.producto.nombre_producto : 'Producto #' + item.id_producto;

                tbody.append(`
                    <tr>
                        <td class="text-center">${item.cantidad}</td>
                        <td>${nombre}</td>
                        <td class="text-end">₡${montoTotalItem.toLocaleString('es-CR')}</td>
                    </tr>
                `);
            });
        } else {
            tbody.append(`<tr><td colspan="3" class="text-center">Sin detalles registrados</td></tr>`);
        }

        // Habilitar botón y recalcular
        $('#btnCobrar').prop('disabled', false);
        $('#inputDescuento').val(0);
        $('#inputPagaCon').val('');
        $('#txtVuelto').text('₡0');

        calcularTotales();
    }

    // Calcular montos de impuestos y descuentos
    function calcularTotales() {
        if (currentSubtotal === 0) return;

        let descuentoPorcentaje = parseFloat($('#inputDescuento').val()) || 0;
        if (descuentoPorcentaje < 0) descuentoPorcentaje = 0;
        if (descuentoPorcentaje > 100) descuentoPorcentaje = 100;

        let montoDescuento = currentSubtotal * (descuentoPorcentaje / 100);
        let baseImponible = currentSubtotal - montoDescuento;

        let servicio = baseImponible * 0.10;
        let iva = baseImponible * 0.13;
        currentTotal = baseImponible + servicio + iva;

        // Actualizar textos
        $('#txtSubtotal').text(`₡${currentSubtotal.toLocaleString('es-CR')}`);
        $('#txtDescuento').text(`-₡${montoDescuento.toLocaleString('es-CR')}`);
        $('#txtServicio').text(`₡${servicio.toLocaleString('es-CR')}`);
        $('#txtIva').text(`₡${iva.toLocaleString('es-CR')}`);
        $('#txtTotal').text(`₡${currentTotal.toLocaleString('es-CR')}`);

        calcularVuelto();
    }

    // Calcular el cambio / vuelto
    function calcularVuelto() {
        let pagaCon = parseFloat($('#inputPagaCon').val()) || 0;
        let vuelto = pagaCon - currentTotal;

        if (vuelto >= 0 && currentTotal > 0 && pagaCon > 0) {
            $('#txtVuelto').text(`₡${vuelto.toLocaleString('es-CR')}`);
            $('#txtVuelto').removeClass('text-danger').addClass('text-primary');
        } else {
            $('#txtVuelto').text('₡0');
            if (pagaCon > 0) {
                $('#txtVuelto').removeClass('text-primary').addClass('text-danger');
                $('#txtVuelto').text('Monto Insuficiente');
            }
        }
    }
</script>
@endpush