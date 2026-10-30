@extends('app')

@section('titulo', 'Caja')

@push('css')
    <link rel="stylesheet" href="{{ asset('css/caja.css') }}?v={{ time() }}">
@endpush

@section('contenido')

    <div class="caja-barra">
        <button type="button" id="abrir-cierre" class="btn-turno">
            <i class="ti ti-lock"></i> Cerrar turno
        </button>
    </div>

    <div class="caja-layout">

        {{-- LISTA DE PEDIDOS --}}
        <section class="caja-lista">
            <h2 class="caja-titulo">Pedidos pendientes de cobro</h2>
            <p class="caja-subtitulo">Selecciona un pedido para cobrar.</p>

            <div class="caja-grid">
                @forelse ($pedidos as $p)
                    <button type="button" class="pedido-card" data-id="{{ $p->id_pedido }}">
                        <div class="pedido-top">
                            <i class="ti ti-receipt-2"></i>
                            <span class="pedido-estado">{{ str_replace('_', ' ', $p->estado) }}</span>
                        </div>
                        <h3 class="pedido-mesa">Mesa {{ $p->numero_mesa }}</h3>
                        <p class="pedido-info">{{ $p->zona }} · Pedido #{{ $p->id_pedido }}</p>
                        <p class="pedido-total">₡{{ number_format((float) $p->subtotal, 2, ',', ' ') }}</p>
                    </button>
                @empty
                    <div class="caja-vacio">
                        <i class="ti ti-cash-off"></i>
                        <p class="caja-vacio-titulo">No hay pedidos pendientes de cobro</p>
                        <p>Los pedidos aparecen aquí cuando cocina los marca como listos.</p>
                    </div>
                @endforelse
            </div>
        </section>

        {{-- PANEL DE COBRO --}}
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

                <div id="cobro-form" style="display:none;">

                    <h3 class="cobro-sub">Productos</h3>
                    <div id="cobro-items"></div>

                    @if ($cajeros->isEmpty())
                        <div class="cobro-alerta">
                            No hay ningún cajero con turno abierto.
                            <a href="{{ route('admin.turnos.create') }}">Abrir un turno</a>
                        </div>
                    @endif

                    <label class="cobro-label">Cajero</label>
                    <select id="cobro-cajero" class="cobro-input">
                        @foreach ($cajeros as $c)
                            <option value="{{ $c->id_empleado }}">{{ $c->nombre }}</option>
                        @endforeach
                    </select>

                    <label class="cobro-label">Cliente (opcional)</label>
                    <select id="cobro-cliente" class="cobro-input">
                        <option value="">Sin cliente</option>
                        @foreach ($clientes as $cl)
                            <option value="{{ $cl->id_cliente }}">{{ $cl->nombre }}</option>
                        @endforeach
                    </select>

                    <label class="cobro-label">Descuento (%)</label>
                    <input type="number" id="cobro-descuento" class="cobro-input" min="0" max="100"
                        step="0.01" value="0">

                    <div class="cobro-fila"><span class="gris">Subtotal</span><span id="r-subtotal">₡0,00</span></div>
                    <div class="cobro-fila"><span class="gris">Descuento</span><span id="r-descuento">₡0,00</span></div>
                    <div class="cobro-fila"><span class="gris">IVA (13%)</span><span id="r-iva">₡0,00</span></div>
                    <div class="cobro-fila"><span class="gris">Servicio (10%)</span><span id="r-servicio">₡0,00</span>
                    </div>
                    <div class="cobro-total"><span>Total</span><span id="r-total">₡0,00</span></div>

                    <h3 class="cobro-sub" style="margin-top:20px;">Pagos</h3>
                    <div id="cobro-pagos"></div>
                    <button type="button" id="agregar-pago" class="btn-agregar-pago">
                        <i class="ti ti-plus"></i> Agregar pago
                    </button>

                    <div class="cobro-fila"><span class="gris">Pagado</span><span id="r-pagado">₡0,00</span></div>
                    <div class="cobro-fila"><span class="gris" id="r-dif-label">Falta</span><span
                            id="r-dif">₡0,00</span></div>

                    <button type="button" id="btn-cobrar" class="btn-cobrar" disabled>Cobrar y emitir factura</button>
                </div>
            </div>
        </aside>

    </div>

    {{-- MODAL: CIERRE DE TURNO --}}
    <div id="turno-overlay" class="turno-overlay" aria-hidden="true">
        <div class="turno-modal" role="dialog" aria-modal="true" aria-label="Cierre de turno">
            <div class="turno-header">
                <h2>Cierre de turno</h2>
                <button type="button" id="turno-x" class="turno-x" aria-label="Cerrar">×</button>
            </div>

            <div class="turno-body">
                @if ($cajeros->isEmpty())
                    <p class="turno-aviso">No hay ningún turno abierto para cerrar.</p>
                @else
                    <label class="cobro-label" style="margin-top:0;">Turno / cajero</label>
                    <select id="turno-select" class="cobro-input">
                        @foreach ($cajeros as $c)
                            <option value="{{ $c->id_turno }}">{{ $c->nombre }} (turno #{{ $c->id_turno }})
                            </option>
                        @endforeach
                    </select>

                    <div id="turno-detalle" class="turno-detalle">Cargando...</div>

                    <label class="cobro-label">Efectivo contado en caja (₡)</label>
                    <input type="number" id="turno-contado" class="cobro-input" min="0" step="0.01"
                        value="0">

                    <div class="cobro-fila">
                        <span class="gris" id="turno-dif-label">Diferencia</span>
                        <span id="turno-dif">₡0,00</span>
                    </div>

                    <div class="turno-acciones">
                        <button type="button" id="turno-cancelar" class="btn-ticket-sec">Cancelar</button>
                        <button type="button" id="turno-confirmar" class="btn-cerrar-turno" disabled>Cerrar
                            turno</button>
                    </div>
                @endif
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const pedidos = @json($pedidosJs);
            const metodos = @json($metodos);
            const hayCajero = {{ $cajeros->isEmpty() ? 'false' : 'true' }};
            const urlCobrar = @json(url('/caja/cobrar'));
            const urlTurnos = @json(url('/caja/turnos'));
            const csrf = @json(csrf_token());

            const el = id => document.getElementById(id);
            const r2 = n => Math.round((n + Number.EPSILON) * 100) / 100;
            const crc = v => '₡' + Number(v).toLocaleString('es-CR', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;'
            } [c]));

            let actual = null;
            let pagos = [];
            let total = 0;

            // ============================================================
            // COBRO DE PEDIDOS
            // ============================================================
            document.querySelectorAll('.pedido-card').forEach(card => {
                card.addEventListener('click', () => {
                    actual = pedidos[card.dataset.id];
                    document.querySelectorAll('.pedido-card').forEach(c => c.classList.toggle(
                        'seleccionado', c === card));

                    el('cobro-titulo').textContent = 'Pedido #' + actual.id + ' · Mesa ' + actual
                        .mesa;
                    el('cobro-vacio').style.display = 'none';
                    el('cobro-form').style.display = 'block';
                    el('cobro-descuento').value = 0;

                    el('cobro-items').innerHTML = actual.items.map(i => `
                <div class="cobro-item">
                    <div class="cobro-item-fila">
                        <span>${i.cantidad} × ${esc(i.nombre)}</span>
                        <span>${crc(i.cantidad * i.precio)}</span>
                    </div>
                    ${i.notas ? `<small class="cobro-nota">Nota: ${esc(i.notas)}</small>` : ''}
                </div>`).join('');

                    pagos = [];
                    calcular();
                    agregarPago();
                });
            });

            // Mismo orden y redondeo que sp_cobrar_cuenta
            function calcular() {
                const sub = actual.subtotal;
                let desc = parseFloat(el('cobro-descuento').value);
                if (!Number.isFinite(desc)) desc = 0;
                desc = Math.min(100, Math.max(0, desc));

                const montoDesc = r2(sub * (desc / 100));
                const base = r2(sub - montoDesc);
                const iva = r2(base * 0.13);
                const serv = r2(base * 0.10);
                total = r2(base + iva + serv);

                el('r-subtotal').textContent = crc(sub);
                el('r-descuento').textContent = '-' + crc(montoDesc);
                el('r-iva').textContent = crc(iva);
                el('r-servicio').textContent = crc(serv);
                el('r-total').textContent = crc(total);

                actualizarPagado();
            }

            function actualizarPagado() {
                const pagado = r2(pagos.reduce((s, p) => s + (Number(p.monto) || 0), 0));
                const dif = r2(pagado - total);

                el('r-pagado').textContent = crc(pagado);
                el('r-dif-label').textContent = dif >= 0 ? 'Vuelto' : 'Falta';
                el('r-dif').textContent = crc(Math.abs(dif));

                el('btn-cobrar').disabled = !(hayCajero && pagos.length > 0 && dif >= 0 && pagado > 0);
            }

            function agregarPago() {
                const pagado = pagos.reduce((s, p) => s + (Number(p.monto) || 0), 0);
                const falta = Math.max(0, r2(total - pagado));
                pagos.push({
                    id_metodo_pago: metodos.length ? metodos[0].id_metodo_pago : '',
                    monto: falta,
                    referencia: ''
                });
                renderPagos();
            }

            function renderPagos() {
                const cont = el('cobro-pagos');
                cont.innerHTML = '';

                pagos.forEach((p, idx) => {
                    const fila = document.createElement('div');
                    fila.className = 'pago-fila';
                    fila.innerHTML = `
                <select class="cobro-input pago-metodo">
                    ${metodos.map(m => `<option value="${m.id_metodo_pago}" ${m.id_metodo_pago == p.id_metodo_pago ? 'selected' : ''}>${esc(m.nombre_metodo)}</option>`).join('')}
                </select>
                <input type="number" class="cobro-input pago-monto" min="0" step="0.01" value="${p.monto}">
                <input type="text" class="cobro-input pago-ref" maxlength="100" placeholder="Referencia" value="${esc(p.referencia)}">
                <button type="button" class="pago-quitar" title="Quitar">×</button>`;

                    fila.querySelector('.pago-metodo').addEventListener('change', e => {
                        p.id_metodo_pago = e.target.value;
                    });
                    fila.querySelector('.pago-monto').addEventListener('input', e => {
                        p.monto = parseFloat(e.target.value) || 0;
                        actualizarPagado();
                    });
                    fila.querySelector('.pago-ref').addEventListener('input', e => {
                        p.referencia = e.target.value;
                    });
                    fila.querySelector('.pago-quitar').addEventListener('click', () => {
                        pagos.splice(idx, 1);
                        renderPagos();
                        actualizarPagado();
                    });

                    cont.appendChild(fila);
                });

                actualizarPagado();
            }

            el('agregar-pago').addEventListener('click', agregarPago);
            el('cobro-descuento').addEventListener('input', calcular);

            el('btn-cobrar').addEventListener('click', async () => {
                if (!actual) return;

                const boton = el('btn-cobrar');
                boton.disabled = true;
                boton.textContent = 'Procesando cobro...';

                const payload = {
                    id_cajero: el('cobro-cajero').value,
                    id_cliente: el('cobro-cliente').value || null,
                    descuento: parseFloat(el('cobro-descuento').value) || 0,
                    pagos: pagos.map(p => ({
                        id_metodo_pago: p.id_metodo_pago,
                        monto: p.monto,
                        referencia: p.referencia || null
                    }))
                };

                try {
                    const respuesta = await fetch(urlCobrar + '/' + actual.id, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify(payload)
                    });

                    const resultado = await respuesta.json();

                    if (respuesta.ok && resultado.success) {
                        window.location.href = resultado.ticket_url;
                        return;
                    }

                    alert('No se pudo cobrar: ' + (resultado.error || resultado.message ||
                        'Error desconocido'));
                } catch (error) {
                    console.error(error);
                    alert('Ocurrió un error de red al comunicarse con el servidor.');
                }

                boton.textContent = 'Cobrar y emitir factura';
                actualizarPagado();
            });

            // ============================================================
            // CIERRE DE TURNO
            // ============================================================
            const turnoOverlay = el('turno-overlay');
            let turno = null;

            const abrirTurno = () => {
                turnoOverlay.classList.add('activa');
                turnoOverlay.setAttribute('aria-hidden', 'false');
                if (el('turno-select')) cargarResumen();
            };
            const cerrarTurnoModal = () => {
                turnoOverlay.classList.remove('activa');
                turnoOverlay.setAttribute('aria-hidden', 'true');
            };

            el('abrir-cierre').addEventListener('click', abrirTurno);
            el('turno-x').addEventListener('click', cerrarTurnoModal);
            turnoOverlay.addEventListener('click', e => {
                if (e.target === turnoOverlay) cerrarTurnoModal();
            });
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape') cerrarTurnoModal();
            });

            if (hayCajero) {
                el('turno-cancelar').addEventListener('click', cerrarTurnoModal);
                el('turno-select').addEventListener('change', cargarResumen);
                el('turno-contado').addEventListener('input', actualizarDiferencia);
                el('turno-confirmar').addEventListener('click', confirmarCierre);
            }

            async function cargarResumen() {
                const id = el('turno-select').value;
                el('turno-detalle').textContent = 'Cargando...';
                el('turno-confirmar').disabled = true;
                turno = null;

                try {
                    const respuesta = await fetch(urlTurnos + '/' + id + '/resumen', {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    const d = await respuesta.json();
                    if (!respuesta.ok || !d.success) throw new Error(d.error || 'Error');

                    turno = d;

                    el('turno-detalle').innerHTML = `
                <div class="cobro-fila"><span class="gris">Apertura</span><span>${esc(d.apertura)}</span></div>
                <div class="cobro-fila"><span class="gris">Monto inicial</span><span>${crc(d.monto_inicial)}</span></div>
                <div class="cobro-fila"><span class="gris">Facturas cobradas</span><span>${d.facturas}</span></div>
                <div class="cobro-fila"><span class="gris">Ventas totales</span><span>${crc(d.ventas)}</span></div>
                ${d.metodos.map(m => `<div class="cobro-fila turno-sub"><span>· ${esc(m.nombre)}</span><span>${crc(m.total)}</span></div>`).join('')}
                <div class="cobro-fila"><span class="gris">Efectivo cobrado</span><span>${crc(d.efectivo)}</span></div>
                <div class="cobro-fila"><span class="gris">Vuelto entregado</span><span>-${crc(d.vuelto)}</span></div>
                <div class="cobro-total"><span>Efectivo esperado</span><span>${crc(d.esperado)}</span></div>`;

                    el('turno-contado').value = d.esperado;
                    actualizarDiferencia();
                } catch (error) {
                    console.error(error);
                    el('turno-detalle').textContent = 'No se pudo cargar el resumen del turno.';
                }
            }

            function actualizarDiferencia() {
                if (!turno) return;

                const contado = parseFloat(el('turno-contado').value);
                const valido = Number.isFinite(contado) && contado >= 0;
                const dif = valido ? r2(contado - turno.esperado) : 0;

                el('turno-dif-label').textContent = dif === 0 ? 'Diferencia (cuadrado)' : (dif > 0 ? 'Sobrante' :
                    'Faltante');
                el('turno-dif').textContent = crc(Math.abs(dif));
                el('turno-dif').style.color = dif === 0 ? '' : (dif > 0 ? '#166534' : '#b91c1c');

                el('turno-confirmar').disabled = !valido;
            }

            async function confirmarCierre() {
                if (!turno) return;

                const contado = parseFloat(el('turno-contado').value);
                const dif = r2(contado - turno.esperado);

                const aviso = dif === 0 ?
                    'La caja cuadra. ¿Cerrar el turno?' :
                    'Hay una diferencia de ' + crc(Math.abs(dif)) + (dif > 0 ? ' (sobrante)' : ' (faltante)') +
                    '. ¿Cerrar el turno de todos modos?';

                if (!confirm(aviso)) return;

                const boton = el('turno-confirmar');
                boton.disabled = true;
                boton.textContent = 'Cerrando...';

                try {
                    const respuesta = await fetch(urlTurnos + '/' + turno.id_turno + '/cerrar', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            monto_final: contado
                        })
                    });

                    const resultado = await respuesta.json();

                    if (respuesta.ok && resultado.success) {
                        alert('Turno cerrado.\nEsperado: ' + crc(resultado.esperado) +
                            '\nContado: ' + crc(resultado.contado) +
                            '\nDiferencia: ' + crc(resultado.diferencia));
                        window.location.reload();
                        return;
                    }

                    alert('No se pudo cerrar el turno: ' + (resultado.error || 'Error desconocido'));
                } catch (error) {
                    console.error(error);
                    alert('Ocurrió un error de red al comunicarse con el servidor.');
                }

                boton.textContent = 'Cerrar turno';
                boton.disabled = false;
            }
        });
    </script>
@endpush
