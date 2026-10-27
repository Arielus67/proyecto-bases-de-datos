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
    const btnRegistrar = document.getElementById('registrar-comanda');

    let mesaActual = null;
    let pedido = {};

    // Token único por cada intento de comanda
    function generarTokenIdempotencia() {
        return 'token-' + Date.now() + '-' + Math.random().toString(36).substring(2, 9);
    }

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

            mesaLabel.textContent = 'Mesa ' + mesaActual.numero;
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

        if (event.target.closest('#cerrar-menu')) {
            cerrarModal();
        }
    });

    overlay.addEventListener('click', event => {
        if (event.target === overlay) {
            cerrarModal();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            cerrarModal();
        }
    });

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
                cantidad: 0,
                notas: '' // Notas y modificadores libres para mongoDB
            };
        }

        pedido[id].cantidad++;

        renderTicket();
        cerrarModal();
    });

    function renderTicket() {
        ticket.replaceChildren();

        const productos = Object.values(pedido);
        let subtotal = 0;

        if (productos.length === 0) {
            const vacio = document.createElement('p');
            vacio.className = 'text-center text-gray-500 py-8';
            vacio.innerHTML = '<i class="ti ti-shopping-cart text-4xl block mb-2"></i>Agrega productos a la comanda.';
            ticket.appendChild(vacio);

            btnRegistrar.disabled = true;
            btnRegistrar.className = 'w-full mt-6 rounded-lg bg-gray-300 text-gray-600 py-3 font-semibold cursor-not-allowed';
        } else {
            btnRegistrar.disabled = false;
            btnRegistrar.className = 'w-full mt-6 rounded-lg bg-green-600 hover:bg-green-700 text-white py-3 font-semibold transition';
        }

        productos.forEach((producto, index) => {
            subtotal += producto.precio * producto.cantidad;

            const fila = document.createElement('div');
            fila.className = 'ticket-item mb-3 p-3 bg-gray-50 rounded-lg border border-gray-200';

            const encabezado = document.createElement('div');
            encabezado.className = 'flex justify-between gap-2';

            const nombre = document.createElement('span');
            nombre.className = 'font-medium text-sm text-gray-800';
            nombre.textContent = producto.nombre;

            const importe = document.createElement('span');
            importe.className = 'font-semibold text-sm text-gray-900 whitespace-nowrap';
            importe.textContent = formatoCRC(producto.precio * producto.cantidad);

            encabezado.append(nombre, importe);

            const inputNota = document.createElement('input');
            inputNota.type = 'text';
            inputNota.placeholder = 'Notas libres (ej. sin cebolla, término medio)';
            inputNota.className = 'w-full mt-2 text-xs p-1.5 border border-gray-300 rounded focus:ring-1 focus:ring-gray-900';
            inputNota.value = producto.notas || '';
            inputNota.addEventListener('input', (e) => {
                producto.notas = e.target.value;
            });

            const controles = document.createElement('div');
            controles.className = 'ticket-cantidad flex items-center justify-between mt-2 text-xs';

            const menos = document.createElement('button');
            menos.type = 'button';
            menos.className = 'px-2 py-1 bg-gray-200 rounded text-gray-700 font-bold';
            menos.textContent = '−';
            menos.addEventListener('click', () => {
                producto.cantidad--;
                if (producto.cantidad <= 0) {
                    delete pedido[producto.id];
                }
                renderTicket();
            });

            const cantidad = document.createElement('span');
            cantidad.className = 'font-semibold text-gray-700';
            cantidad.textContent = 'Cantidad: ' + producto.cantidad;

            const mas = document.createElement('button');
            mas.type = 'button';
            mas.className = 'px-2 py-1 bg-gray-200 rounded text-gray-700 font-bold';
            mas.textContent = '+';
            mas.addEventListener('click', () => {
                producto.cantidad++;
                renderTicket();
            });

            controles.append(menos, cantidad, mas);
            fila.append(encabezado, inputNota, controles);
            ticket.appendChild(fila);
        });

        const impuestos = 0;
        const total = subtotal + impuestos;

        document.getElementById('ticket-subtotal').textContent = formatoCRC(subtotal);
        document.getElementById('ticket-impuestos').textContent = formatoCRC(impuestos);
        document.getElementById('ticket-total').textContent = formatoCRC(total);
    }

    // =======================================================
    // ENVIAR COMANDA A LARAVEL (SQL SERVER + MONGODB)
    // =======================================================
    btnRegistrar.addEventListener('click', async () => {
        if (!mesaActual || Object.keys(pedido).length === 0) {
            alert('Por favor selecciona una mesa y agrega productos a la comanda.');
            return;
        }

        btnRegistrar.disabled = true;
        btnRegistrar.textContent = 'Procesando y guardando...';

        // Mapear los detalles para enviar a SQL Server y MongoDB
        const detalles = Object.values(pedido).map((p, idx) => ({
            num_linea: idx + 1,
            id_producto: p.id,
            cantidad: p.cantidad,
            notas: p.notas || ''
        }));

        const payload = {
            token_idempotencia: generarTokenIdempotencia(),
            id_mesa: mesaActual.id,
            id_empleado: 1, // ID del empleado actual
            observaciones: 'Comanda tomada desde interfaz de Mesero',
            detalles: detalles
        };

        try {
            const respuesta = await fetch("{{ route('mesero.guardar') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', // <--- ¡AQUÍ ESTABA EL ERROR! Debe ser application/json
                    'X-CSRF-TOKEN': "{{ csrf_token() }}",
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const resultado = await respuesta.json();

            if (respuesta.ok && resultado.success) {
                alert('¡Comanda #' + resultado.id_pedido + ' registrada con éxito en SQL Server y MongoDB!');

                // Limpiar ticket y refrescar la vista
                pedido = {};
                renderTicket();
                window.location.reload();
            } else {
                alert('Error al registrar la comanda: ' + (resultado.error || resultado.message));
                btnRegistrar.disabled = false;
                btnRegistrar.textContent = 'Registrar comanda';
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Ocurrió un error de red al comunicarse con el servidor.');
            btnRegistrar.disabled = false;
            btnRegistrar.textContent = 'Registrar comanda';
        }
    });
});
</script>
@endpush
