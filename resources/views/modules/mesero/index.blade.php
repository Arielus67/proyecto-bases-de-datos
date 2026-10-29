@extends('app')

@section('titulo', 'Gestión de Comandas')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/comandas.css') }}?v={{ time() }}">
@endpush

@section('contenido')

<div class="comandas-layout">

    {{-- MESAS --}}
    <section class="comandas-mesas">
        <h2 class="mesas-titulo">Mesas del restaurante</h2>
        <p class="mesas-subtitulo">Selecciona una mesa para agregar productos.</p>

        <div class="mesas-grid">
            @forelse ($mesas as $mesa)
                <button type="button"
                    class="mesa-card"
                    data-mesa-id="{{ $mesa->id_mesa }}"
                    data-mesa-numero="{{ $mesa->numero_mesa }}"
                    aria-label="Seleccionar mesa {{ $mesa->numero_mesa }}">

                    <div class="mesa-top">
                        <i class="ti ti-armchair"></i>
                        <span class="mesa-estado">{{ strtoupper($mesa->estado ?? '') }}</span>
                    </div>

                    <h3 class="mesa-nombre">Mesa {{ $mesa->numero_mesa }}</h3>
                    <p class="mesa-zona">{{ $mesa->zona ?: 'Sin zona' }}</p>
                    <p class="mesa-cap">
                        <i class="ti ti-users"></i>
                        {{ $mesa->capacidad }} {{ $mesa->capacidad == 1 ? 'persona' : 'personas' }}
                    </p>
                </button>
            @empty
                <div style="border:1px solid #e5e7eb;border-radius:12px;padding:32px;text-align:center;">
                    <i class="ti ti-armchair" style="font-size:36px;color:#9ca3af;"></i>
                    <p style="font-weight:600;margin-top:8px;">No hay mesas registradas</p>
                </div>
            @endforelse
        </div>
    </section>

    {{-- TICKET --}}
    <aside class="ticket-panel">
        <div class="ticket-header">
            <div class="ticket-header-top">
                <i class="ti ti-receipt"></i>
                <h2>Detalle del pedido</h2>
            </div>
            <div class="ticket-fecha">
                <span>Fecha</span>
                <span>{{ now()->format('d/m/Y H:i') }}</span>
            </div>
        </div>

        <div class="ticket-body">
            <span class="ticket-label">Mesa seleccionada</span>
            <p id="mesa-seleccionada" class="ticket-mesa">Ninguna</p>

            <h3 class="ticket-subtitulo">Productos</h3>

            <div id="ticket-productos">
                <p style="text-align:center;color:#6b7280;padding:32px 0;">
                    <i class="ti ti-shopping-cart" style="font-size:36px;"></i>
                    <span style="display:block;margin-top:8px;">Selecciona una mesa y agrega productos.</span>
                </p>
            </div>

            <div class="ticket-fila">
                <span class="gris">Subtotal</span>
                <span id="ticket-subtotal">₡0,00</span>
            </div>
            <div class="ticket-fila">
                <span class="gris">Impuestos</span>
                <span id="ticket-impuestos">₡0,00</span>
            </div>
            <div class="ticket-total">
                <span>Total</span>
                <span id="ticket-total">₡0,00</span>
            </div>

            <button type="button" id="registrar-comanda" class="btn-registrar" disabled>
                Registrar comanda
            </button>
        </div>
    </aside>

</div>

{{-- MODAL: MENÚ DE PRODUCTOS --}}
<div id="comanda-overlay" class="comanda-overlay" aria-hidden="true">
    <div class="comanda-modal" role="dialog" aria-modal="true" aria-label="Menú de productos">
        <div id="contenido-menu" style="padding:24px;text-align:center;color:#6b7280;">
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
                contenidoMenu.textContent = 'No se pudo cargar el menú. Inténtalo nuevamente.';
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
                notas: ''
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
            vacio.style.cssText = 'text-align:center;color:#6b7280;padding:32px 0;';
            vacio.innerHTML = '<i class="ti ti-shopping-cart" style="font-size:36px;display:block;margin-bottom:8px;"></i>Agrega productos a la comanda.';
            ticket.appendChild(vacio);

            btnRegistrar.disabled = true;
            btnRegistrar.className = 'btn-registrar';
        } else {
            btnRegistrar.disabled = false;
            btnRegistrar.className = 'btn-registrar activo';
        }

        productos.forEach(producto => {
            subtotal += producto.precio * producto.cantidad;

            const fila = document.createElement('div');
            fila.className = 'ticket-item';

            const encabezado = document.createElement('div');
            encabezado.style.cssText = 'display:flex;justify-content:space-between;gap:8px;';

            const nombre = document.createElement('span');
            nombre.style.cssText = 'font-weight:500;font-size:14px;';
            nombre.textContent = producto.nombre;

            const importe = document.createElement('span');
            importe.style.cssText = 'font-weight:600;font-size:14px;white-space:nowrap;';
            importe.textContent = formatoCRC(producto.precio * producto.cantidad);

            encabezado.append(nombre, importe);

            const inputNota = document.createElement('input');
            inputNota.type = 'text';
            inputNota.placeholder = 'Notas libres (ej. sin cebolla, término medio)';
            inputNota.style.cssText = 'width:100%;margin-top:8px;font-size:12px;padding:6px;border:1px solid #d1d5db;border-radius:6px;';
            inputNota.value = producto.notas || '';
            inputNota.addEventListener('input', e => {
                producto.notas = e.target.value;
            });

            const controles = document.createElement('div');
            controles.className = 'ticket-cantidad';
            controles.style.cssText = 'justify-content:space-between;margin-top:8px;font-size:12px;';

            const menos = document.createElement('button');
            menos.type = 'button';
            menos.textContent = '−';
            menos.addEventListener('click', () => {
                producto.cantidad--;
                if (producto.cantidad <= 0) {
                    delete pedido[producto.id];
                }
                renderTicket();
            });

            const cantidad = document.createElement('span');
            cantidad.style.fontWeight = '600';
            cantidad.textContent = 'Cantidad: ' + producto.cantidad;

            const mas = document.createElement('button');
            mas.type = 'button';
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

    // ENVIAR COMANDA A LARAVEL (SQL SERVER + MONGODB)
    btnRegistrar.addEventListener('click', async () => {
        if (!mesaActual || Object.keys(pedido).length === 0) {
            alert('Por favor selecciona una mesa y agrega productos a la comanda.');
            return;
        }

        btnRegistrar.disabled = true;
        btnRegistrar.textContent = 'Procesando y guardando...';

        const detalles = Object.values(pedido).map((p, idx) => ({
            num_linea: idx + 1,
            id_producto: p.id,
            cantidad: p.cantidad,
            notas: p.notas || ''
        }));

        const payload = {
            token_idempotencia: generarTokenIdempotencia(),
            id_mesa: mesaActual.id,
            id_empleado: 1,
            observaciones: 'Comanda tomada desde interfaz de Mesero',
            detalles: detalles
        };

        try {
            const respuesta = await fetch("{{ route('mesero.guardar') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': "{{ csrf_token() }}",
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const resultado = await respuesta.json();

            if (respuesta.ok && resultado.success) {
                alert('¡Comanda #' + resultado.id_pedido + ' registrada con éxito en SQL Server y MongoDB!');
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
