@extends('app')

@section('titulo', 'Gestión de Comandas')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/comandas.css') }}">
@endpush

@section('contenido')

{{-- CONTENEDOR: MESAS A LA IZQUIERDA, TICKET A LA DERECHA --}}
<div style="display:grid;
            grid-template-columns:minmax(0, 1fr) 360px;
            gap:24px;
            align-items:start;
            width:100%;">

    {{-- =========================================
         IZQUIERDA: TARJETAS DE MESAS
    ========================================== --}}
    <section style="min-width:0;">

        <div class="mb-5">
            <h2 class="text-xl font-semibold">Mesas del restaurante</h2>
            <p class="text-sm text-gray-500">
                Selecciona una mesa para agregar productos.
            </p>
        </div>

        <div style="display:grid;
                    grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
                    gap:16px;">

            @forelse ($mesas as $mesa)

                @php
                    $estado = strtoupper($mesa->estado ?? '');

                    $colores = [
                        'DISPONIBLE' => 'bg-green-100 text-green-700',
                        'OCUPADA' => 'bg-red-100 text-red-700',
                        'RESERVADA' => 'bg-yellow-100 text-yellow-700',
                        'MANTENIMIENTO' => 'bg-gray-100 text-gray-700',
                    ];

                    $color = $colores[$estado] ?? 'bg-gray-100 text-gray-600';
                @endphp

                <button type="button"
                    class="mesa-card"
                    data-mesa-id="{{ $mesa->id_mesa }}"
                    data-mesa-numero="{{ $mesa->numero_mesa }}"
                    aria-label="Seleccionar mesa {{ $mesa->numero_mesa }}">

                    <div class="flex justify-between items-center mb-4">
                        <i class="ti ti-armchair text-3xl text-gray-700"></i>

                        <span class="text-xs font-semibold rounded-full px-2 py-1 {{ $color }}">
                            {{ $estado }}
                        </span>
                    </div>

                    <h3 class="text-lg font-bold">
                        Mesa {{ $mesa->numero_mesa }}
                    </h3>

                    <p class="text-sm text-gray-500 mt-1">
                        {{ $mesa->zona ?: 'Sin zona' }}
                    </p>

                    <p class="text-sm text-gray-500 mt-2">
                        <i class="ti ti-users"></i>
                        {{ $mesa->capacidad }}
                        {{ $mesa->capacidad == 1 ? 'persona' : 'personas' }}
                    </p>

                </button>

            @empty

                <div class="border rounded-xl p-8 text-center">
                    <i class="ti ti-armchair text-4xl text-gray-400"></i>
                    <p class="font-semibold mt-2">No hay mesas registradas</p>
                </div>

            @endforelse

        </div>
    </section>

    {{-- =========================================
         DERECHA: TICKET DE COMANDA
    ========================================== --}}
    <aside style="width:100%;min-width:0;position:sticky;top:20px;">

        <div class="bg-white border rounded-xl shadow-sm overflow-hidden">

            <div class="bg-gray-900 text-white p-5">
                <div class="flex items-center gap-3">
                    <i class="ti ti-receipt text-3xl"></i>

                    <div>
                        <h2 class="text-lg font-bold">Comanda actual</h2>
                        <p class="text-sm text-gray-300">
                            Detalle del pedido
                        </p>
                    </div>
                </div>

                <div class="border-t border-gray-700 mt-4 pt-3 flex justify-between text-sm">
                    <span>Fecha</span>
                    <span>{{ now()->format('d/m/Y H:i') }}</span>
                </div>
            </div>

            <div class="p-5">

                <div class="mb-4">
                    <span class="text-sm text-gray-500">Mesa seleccionada</span>
                    <p id="mesa-seleccionada" class="font-semibold">
                        Ninguna
                    </p>
                </div>

                <h3 class="font-semibold mb-3">Productos</h3>

                <div id="ticket-productos">
                    <p class="text-center text-gray-500 py-8">
                        <i class="ti ti-shopping-cart text-4xl"></i>
                        <span class="block mt-2">
                            Selecciona una mesa y agrega productos.
                        </span>
                    </p>
                </div>

                <div class="space-y-3 mt-5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Subtotal</span>
                        <span id="ticket-subtotal">₡0,00</span>
                    </div>

                    <div class="flex justify-between">
                        <span class="text-gray-500">Impuestos</span>
                        <span id="ticket-impuestos">₡0,00</span>
                    </div>

                    <div class="border-t pt-3 flex justify-between text-lg font-bold">
                        <span>Total</span>
                        <span id="ticket-total">₡0,00</span>
                    </div>
                </div>

                <button type="button"
                    id="registrar-comanda"
                    disabled
                    class="w-full mt-6 rounded-lg bg-gray-300 text-gray-600 py-3 font-semibold">
                    Registrar comanda
                </button>

                <p class="text-xs text-gray-400 text-center mt-3">
                    El pedido todavía no se guarda en la base de datos.
                </p>

            </div>
        </div>
    </aside>

</div>

{{-- =========================================
     MODAL: MENÚ DE PRODUCTOS
========================================== --}}
<div id="comanda-overlay"
    class="comanda-overlay"
    aria-hidden="true">

    <div class="comanda-modal"
        role="dialog"
        aria-modal="true"
        aria-label="Menú de productos">

        <div id="contenido-menu" class="p-6 text-center text-gray-500">
            Selecciona una mesa para cargar el menú.
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.getElementById('comanda-overlay');
    const contenidoMenu = document.getElementById('contenido-menu');
    const ticket = document.getElementById('ticket-productos');
    const mesaLabel = document.getElementById('mesa-seleccionada');

    let mesaActual = null;
    let pedido = {};

    const formatoCRC = valor =>
        '₡' + Number(valor).toLocaleString('es-CR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

    function abrirModal() {
        overlay.classList.add('activa');
        overlay.setAttribute('aria-hidden', 'false');
    }

    function cerrarModal() {
        overlay.classList.remove('activa');
        overlay.setAttribute('aria-hidden', 'true');
    }

    // Seleccionar mesa y cargar el menú con fetch/AJAX
    document.addEventListener('click', async event => {
        const mesa = event.target.closest('.mesa-card');

        if (mesa) {
            mesaActual = {
                id: mesa.dataset.mesaId,
                numero: mesa.dataset.mesaNumero
            };

            document.querySelectorAll('.mesa-card').forEach(card => {
                card.classList.toggle('seleccionada', card === mesa);
            });

            mesaLabel.textContent = mesaActual.numero;
            abrirModal();

            contenidoMenu.textContent = 'Cargando menú...';

            try {
                const url = @json(url('/mesero/mesas')) + '/' +
                    encodeURIComponent(mesaActual.id) + '/productos';

                const respuesta = await fetch(url, {
                    headers: {
                        'Accept': 'text/html',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!respuesta.ok) {
                    throw new Error('Error HTTP ' + respuesta.status);
                }

                contenidoMenu.innerHTML = await respuesta.text();

            } catch (error) {
                contenidoMenu.textContent =
                    'No se pudo cargar el menú. Inténtalo nuevamente.';
                console.error(error);
            }

            return;
        }

        // Cerrar desde el botón de la ventana
        if (event.target.closest('#cerrar-menu')) {
            cerrarModal();
        }
    });

    // Cerrar al pulsar fuera del contenido
    overlay.addEventListener('click', event => {
        if (event.target === overlay) {
            cerrarModal();
        }
    });

    // Cerrar con Escape
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            cerrarModal();
        }
    });

    // Agregar un producto al ticket
    overlay.addEventListener('click', event => {
        const boton = event.target.closest('.comanda-agregar');

        if (!boton) return;

        const id = boton.dataset.id;
        const nombre = boton.dataset.nombre;
        const precio = Number(boton.dataset.precio);

        if (!id || !nombre || !Number.isFinite(precio) || precio < 0) {
            return;
        }

        if (!pedido[id]) {
            pedido[id] = {
                id: id,
                nombre: nombre,
                precio: precio,
                cantidad: 0
            };
        }

        pedido[id].cantidad++;

        renderTicket();
        cerrarModal();
    });

    // Dibujar el ticket y calcular los importes
    function renderTicket() {
        ticket.replaceChildren();

        const productos = Object.values(pedido);
        let subtotal = 0;

        if (productos.length === 0) {
            const vacio = document.createElement('p');
            vacio.className = 'text-center text-gray-500 py-8';
            vacio.textContent = 'Agrega productos a la comanda.';
            ticket.appendChild(vacio);
        }

        productos.forEach(producto => {
            subtotal += producto.precio * producto.cantidad;

            const fila = document.createElement('div');
            fila.className = 'ticket-item';

            const encabezado = document.createElement('div');
            encabezado.className = 'flex justify-between gap-2';

            const nombre = document.createElement('span');
            nombre.className = 'font-medium';
            nombre.textContent = producto.nombre;

            const importe = document.createElement('span');
            importe.className = 'font-semibold whitespace-nowrap';
            importe.textContent = formatoCRC(
                producto.precio * producto.cantidad
            );

            encabezado.append(nombre, importe);

            const controles = document.createElement('div');
            controles.className = 'ticket-cantidad mt-2';

            const menos = document.createElement('button');
            menos.type = 'button';
            menos.textContent = '−';
            menos.setAttribute('aria-label', 'Disminuir cantidad');
            menos.addEventListener('click', () => {
                producto.cantidad--;

                if (producto.cantidad <= 0) {
                    delete pedido[producto.id];
                }

                renderTicket();
            });

            const cantidad = document.createElement('span');
            cantidad.textContent = 'Cantidad: ' + producto.cantidad;

            const mas = document.createElement('button');
            mas.type = 'button';
            mas.textContent = '+';
            mas.setAttribute('aria-label', 'Aumentar cantidad');
            mas.addEventListener('click', () => {
                producto.cantidad++;
                renderTicket();
            });

            controles.append(menos, cantidad, mas);
            fila.append(encabezado, controles);
            ticket.appendChild(fila);
        });

        // No se aplica impuesto hasta definir la tasa del restaurante
        const impuestos = 0;
        const total = subtotal + impuestos;

        document.getElementById('ticket-subtotal').textContent =
            formatoCRC(subtotal);

        document.getElementById('ticket-impuestos').textContent =
            formatoCRC(impuestos);

        document.getElementById('ticket-total').textContent =
            formatoCRC(total);
    }
});
</script>
@endpush