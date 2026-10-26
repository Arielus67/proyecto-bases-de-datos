@extends('app')

@section('titulo', 'Gestión de Comandas - Mesero')

@section('botones')
    <button type="button" class="btn btn-light btn-sm fw-bold" onclick="clearOrder()">
        <i class="fas fa-trash me-1"></i> Limpiar Comanda
    </button>
@endsection

@section('contenido')
<div class="row g-3">
    {{-- COLUMNA IZQUIERDA: Selección de Mesas y Catálogo de Productos --}}
    <div class="col-lg-7 col-xl-8">

        {{-- 1. Estado de Mesas --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-6"><i class="fas fa-chair me-2"></i>Estado de Mesas</h5>
                <span class="badge bg-primary" id="selectedTableBadge">Seleccione una mesa</span>
            </div>
            <div class="card-body">
                <div class="row row-cols-2 row-cols-md-4 g-2">
                    @forelse ($mesas as $mesa)
                        @php
                            $badgeColor = match (strtoupper($mesa->estado)) {
                                'DISPONIBLE' => 'success',
                                'OCUPADA'    => 'danger',
                                'RESERVADA'  => 'warning',
                                default      => 'secondary',
                            };
                        @endphp
                        <div class="col">
                            <div class="card text-center border-{{ $badgeColor }} btn-select-table cursor-pointer"
                                 onclick="selectTable({{ $mesa->id_mesa }}, '{{ $mesa->numero_mesa }}', '{{ $mesa->estado }}')"
                                 id="mesa-card-{{ $mesa->id_mesa }}">
                                <div class="card-body p-2">
                                    <h6 class="fw-bold mb-0">Mesa {{ $mesa->numero_mesa }}</h6>
                                    <small class="text-muted d-block">{{ $mesa->capacidad }} pers. ({{ $mesa->zona }})</small>
                                    <span class="badge bg-{{ $badgeColor }}">
                                        {{ ucfirst(strtolower($mesa->estado)) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center text-muted py-3">
                            <i class="fas fa-info-circle me-1"></i> No hay mesas registradas en la base de datos.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- 2. Menú de Productos --}}
        <div class="card shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-6"><i class="fas fa-utensils me-2"></i>Platillos y Bebidas</h5>
                <input type="text" id="searchProduct" class="form-control form-control-sm w-50" placeholder="Buscar producto...">
            </div>
            <div class="card-body">
                <div class="row row-cols-2 row-cols-md-3 g-2" id="productsGrid">
                    @forelse ($productos as $producto)
                        <div class="col product-item">
                            <div class="card h-100 bg-light border-0 text-center p-2 cursor-pointer shadow-sm"
                                 onclick="addToOrder({{ $producto->id_producto }}, {{ json_encode($producto->nombre_producto) }}, {{ $producto->precio }})">
                                <h6 class="fw-bold mb-1 small">{{ $producto->nombre_producto }}</h6>
                                <span class="text-primary fw-bold small">₡{{ number_format($producto->precio, 0, ',', '.') }}</span>
                                <button type="button" class="btn btn-sm btn-outline-primary mt-2 py-0">+ Agregar</button>
                            </div>
                        </div>
                    @empty
                        <div class="col-12 text-center text-muted py-3">
                            <i class="fas fa-exclamation-triangle me-1"></i> No hay productos activos en la base de datos.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    {{-- COLUMNA DERECHA: Resumen de Comanda --}}
    <div class="col-lg-5 col-xl-4">
        <div class="card shadow-sm sticky-top" style="top: 1rem;">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fs-6"><i class="fas fa-receipt me-2"></i>Comanda Activa</h5>
                <span class="badge bg-light text-success fw-bold" id="currentMesaTitle">Mesa: N/A</span>
            </div>

            <div class="card-body p-3">
                <form id="formComanda" action="{{ route('mesero.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="id_mesa" id="inputMesaId" value="">

                    {{-- Inputs dinámicos para los ítems al enviar --}}
                    <div id="hiddenItemsInputs"></div>

                    <div class="table-responsive mb-2" style="max-height: 280px; overflow-y: auto;">
                        <table class="table table-sm align-middle" id="orderTable">
                            <thead>
                                <tr class="small text-muted">
                                    <th>Cant.</th>
                                    <th>Producto</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-center">Ajuste</th>
                                </tr>
                            </thead>
                            <tbody id="orderItemsBody">
                                <tr id="emptyRow">
                                    <td colspan="4" class="text-center text-muted py-4 small">
                                        Selecciona una mesa y agrega productos a la comanda.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mb-3">
                        <label for="notas" class="form-label small fw-bold">Notas para Cocina / Observaciones:</label>
                        <textarea name="notas" id="notas" class="form-control form-control-sm" rows="2" placeholder="Ej: Poca sal, sin cebolla..."></textarea>
                    </div>

                    <div class="bg-light p-2 rounded mb-3 small">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Subtotal:</span>
                            <span class="fw-bold" id="subtotalText">₡0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 text-muted">
                            <span>Servicio (10%):</span>
                            <span id="servicioText">₡0</span>
                        </div>
                        <div class="d-flex justify-content-between mb-1 text-muted">
                            <span>IVA (13%):</span>
                            <span id="ivaText">₡0</span>
                        </div>
                        <hr class="my-1">
                        <div class="d-flex justify-content-between fs-6 fw-bold text-success">
                            <span>Total:</span>
                            <span id="totalText">₡0</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-success w-100 fw-bold" id="btnSubmitOrder" disabled>
                        <i class="fas fa-paper-plane me-1"></i> Enviar a Cocina
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    let selectedMesaId = null;
    let orderItems = [];

    function selectTable(id_mesa, numero_mesa, estado) {
        selectedMesaId = id_mesa;
        $('#inputMesaId').val(id_mesa);
        $('#currentMesaTitle').text(`Mesa ${numero_mesa}`);
        $('#selectedTableBadge').text(`Mesa ${numero_mesa} (${estado})`);

        $('.btn-select-table').removeClass('border-primary shadow');
        $(`#mesa-card-${id_mesa}`).addClass('border-primary shadow');

        checkSubmitButton();
    }

    function addToOrder(id_producto, nombre_producto, precio) {
        if (!selectedMesaId) {
            alert('Por favor selecciona una mesa primero.');
            return;
        }

        const item = orderItems.find(i => i.id_producto === id_producto);
        if (item) {
            item.cantidad++;
        } else {
            orderItems.push({ id_producto, nombre_producto, precio: parseFloat(precio), cantidad: 1 });
        }

        renderOrder();
    }

    function changeQty(id_producto, delta) {
        const item = orderItems.find(i => i.id_producto === id_producto);
        if (!item) return;

        item.cantidad += delta;
        if (item.cantidad <= 0) {
            orderItems = orderItems.filter(i => i.id_producto !== id_producto);
        }
        renderOrder();
    }

    function renderOrder() {
        const tbody = $('#orderItemsBody');
        const hiddenInputsContainer = $('#hiddenItemsInputs');

        tbody.empty();
        hiddenInputsContainer.empty();

        if (orderItems.length === 0) {
            tbody.append(`
                <tr id="emptyRow">
                    <td colspan="4" class="text-center text-muted py-4 small">
                        Selecciona una mesa y agrega productos a la comanda.
                    </td>
                </tr>
            `);
            updateTotals(0);
            checkSubmitButton();
            return;
        }

        let subtotal = 0;

        orderItems.forEach((item, index) => {
            const totalItem = item.precio * item.cantidad;
            subtotal += totalItem;

            tbody.append(`
                <tr>
                    <td><span class="badge bg-secondary">${item.cantidad}</span></td>
                    <td class="small fw-bold">${item.nombre_producto}</td>
                    <td class="text-end small">₡${totalItem.toLocaleString('es-CR')}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-success py-0 px-1" onclick="changeQty(${item.id_producto}, 1)">+</button>
                        <button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="changeQty(${item.id_producto}, -1)">-</button>
                    </td>
                </tr>
            `);

            hiddenInputsContainer.append(`
                <input type="hidden" name="items[${index}][id_producto]" value="${item.id_producto}">
                <input type="hidden" name="items[${index}][cantidad]" value="${item.cantidad}">
                <input type="hidden" name="items[${index}][precio]" value="${item.precio}">
            `);
        });

        updateTotals(subtotal);
        checkSubmitButton();
    }

    function updateTotals(subtotal) {
        const servicio = subtotal * 0.10;
        const iva = subtotal * 0.13;
        const total = subtotal + servicio + iva;

        $('#subtotalText').text(`₡${subtotal.toLocaleString('es-CR')}`);
        $('#servicioText').text(`₡${servicio.toLocaleString('es-CR')}`);
        $('#ivaText').text(`₡${iva.toLocaleString('es-CR')}`);
        $('#totalText').text(`₡${total.toLocaleString('es-CR')}`);
    }

    function checkSubmitButton() {
        $('#btnSubmitOrder').prop('disabled', !(selectedMesaId && orderItems.length > 0));
    }

    function clearOrder() {
        if (orderItems.length > 0 && confirm('¿Desea vaciar la comanda actual?')) {
            orderItems = [];
            renderOrder();
        }
    }

    $('#searchProduct').on('keyup', function () {
        const value = $(this).val().toLowerCase();

        $('#productsGrid .product-item').filter(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
</script>
@endpush