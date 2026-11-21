(function () {
    const config = Restaurante.datos('datos-caja');
    const metodos = config.metodos || [];
    const hayCajero = Boolean(config.hayCajero);

    const el = id => document.getElementById(id);
    const leerPedidos = () => Restaurante.datos('caja-datos') || {};
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
    }[c]));

    let pedidos = leerPedidos();
    let actual = null;
    let pagos = [];
    let total = 0;
    let procesando = false;

    const botonCobrar = el('btn-cobrar');
    const textoCobrar = botonCobrar.querySelector('span');

    function animarValor(nodo, texto) {
        if (nodo.textContent === texto) return;
        nodo.textContent = texto;
        nodo.classList.remove('valor-cambio');
        void nodo.offsetWidth;
        nodo.classList.add('valor-cambio');
    }

    el('caja-grid').addEventListener('click', evento => {
        const card = evento.target.closest('.pedido-card');
        if (!card || !pedidos[card.dataset.id]) return;

        actual = pedidos[card.dataset.id];
        document.querySelectorAll('.pedido-card').forEach(c => c.classList.toggle('seleccionado', c === card));

        el('cobro-titulo').textContent = 'Pedido #' + actual.id + ' · Mesa ' + actual.mesa;
        el('cobro-vacio').hidden = true;
        el('cobro-form').hidden = false;
        el('cobro-form').classList.remove('entrada');
        void el('cobro-form').offsetWidth;
        el('cobro-form').classList.add('entrada');
        el('cobro-descuento').value = 0;

        mostrarItems();

        pagos = [];
        calcular();
        agregarPago();
    });

    function mostrarItems() {
        el('cobro-items').innerHTML = actual.items.map(i => `
            <div class="cobro-item">
                <div class="cobro-item-fila">
                    <span>${i.cantidad} × ${esc(i.nombre)}</span>
                    <span class="num">${crc(i.cantidad * i.precio)}</span>
                </div>
                ${i.notas ? `<small class="cobro-nota">Nota: ${esc(i.notas)}</small>` : ''}
            </div>`).join('');
    }

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
        animarValor(el('r-total'), crc(total));

        actualizarPagado();
    }

    function actualizarPagado() {
        const pagado = r2(pagos.reduce((s, p) => s + (Number(p.monto) || 0), 0));
        const dif = r2(pagado - total);
        const fila = el('r-dif').closest('.cobro-fila');

        el('r-pagado').textContent = crc(pagado);
        el('r-dif-label').textContent = dif >= 0 ? 'Vuelto' : 'Falta';
        el('r-dif').textContent = crc(Math.abs(dif));
        fila.classList.toggle('cobro-fila--falta', dif < 0);
        fila.classList.toggle('cobro-fila--vuelto', dif >= 0 && pagado > 0);

        botonCobrar.disabled = procesando || !(hayCajero && pagos.length > 0 && dif >= 0 && pagado > 0);
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
                <select class="cobro-input pago-metodo" aria-label="Método de pago">
                    ${metodos.map(m => `<option value="${m.id_metodo_pago}" ${m.id_metodo_pago == p.id_metodo_pago ? 'selected' : ''}>${esc(m.nombre_metodo)}</option>`).join('')}
                </select>
                <input type="number" class="cobro-input pago-monto num" min="0" step="0.01" value="${p.monto}" aria-label="Monto">
                <input type="text" class="cobro-input pago-ref" maxlength="100" placeholder="Referencia" value="${esc(p.referencia)}" aria-label="Referencia">
                <button type="button" class="pago-quitar" title="Quitar" aria-label="Quitar pago"><i class="ti ti-x"></i></button>`;

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

    botonCobrar.addEventListener('click', async () => {
        if (!actual) return;

        procesando = true;
        botonCobrar.disabled = true;
        textoCobrar.textContent = 'Procesando cobro...';
        Restaurante.cargando(botonCobrar, true);

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
            const respuesta = await fetch(config.urlCobrar + '/' + actual.id, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const resultado = await respuesta.json();

            if (respuesta.ok && resultado.success) {
                textoCobrar.textContent = 'Factura emitida';
                const destino = new URL(resultado.ticket_url, window.location.href);
                destino.searchParams.set('nuevo', '1');
                window.location.href = destino.toString();
                return;
            }

            Restaurante.notificar('No se pudo cobrar: ' + (resultado.error || resultado.message || 'Error desconocido'), 'error', 6000);
        } catch (error) {
            console.error(error);
            Restaurante.notificar('Ocurrió un error de red al comunicarse con el servidor.', 'error', 6000);
        }

        procesando = false;
        Restaurante.cargando(botonCobrar, false);
        textoCobrar.textContent = 'Cobrar y emitir factura';
        actualizarPagado();
    });

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
        if (e.key === 'Escape' && !document.querySelector('.modal-fondo')) cerrarTurnoModal();
    });

    if (hayCajero) {
        el('turno-cancelar').addEventListener('click', cerrarTurnoModal);
        el('turno-select').addEventListener('change', cargarResumen);
        el('turno-contado').addEventListener('input', actualizarDiferencia);
        el('turno-confirmar').addEventListener('click', confirmarCierre);
    }

    async function cargarResumen() {
        const id = el('turno-select').value;
        el('turno-detalle').innerHTML = '<span class="cargador"></span> Cargando...';
        el('turno-confirmar').disabled = true;
        turno = null;

        try {
            const respuesta = await fetch(config.urlTurnos + '/' + id + '/resumen', {
                headers: { 'Accept': 'application/json' }
            });
            const d = await respuesta.json();
            if (!respuesta.ok || !d.success) throw new Error(d.error || 'Error');

            turno = d;

            el('turno-detalle').innerHTML = `
                <div class="cobro-fila"><span class="gris">Apertura</span><span>${esc(d.apertura)}</span></div>
                <div class="cobro-fila"><span class="gris">Monto inicial</span><span class="num">${crc(d.monto_inicial)}</span></div>
                <div class="cobro-fila"><span class="gris">Facturas cobradas</span><span class="num">${d.facturas}</span></div>
                <div class="cobro-fila"><span class="gris">Ventas totales</span><span class="num">${crc(d.ventas)}</span></div>
                ${d.metodos.map(m => `<div class="cobro-fila turno-sub"><span>· ${esc(m.nombre)}</span><span class="num">${crc(m.total)}</span></div>`).join('')}
                <div class="cobro-fila"><span class="gris">Efectivo cobrado</span><span class="num">${crc(d.efectivo)}</span></div>
                <div class="cobro-fila"><span class="gris">Vuelto entregado</span><span class="num">-${crc(d.vuelto)}</span></div>
                <div class="cobro-total"><span>Efectivo esperado</span><span class="num">${crc(d.esperado)}</span></div>`;

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
        const nodo = el('turno-dif');

        el('turno-dif-label').textContent = dif === 0 ? 'Diferencia (cuadrado)' : (dif > 0 ? 'Sobrante' : 'Faltante');
        nodo.textContent = crc(Math.abs(dif));
        nodo.classList.toggle('turno-dif--sobrante', dif > 0);
        nodo.classList.toggle('turno-dif--faltante', dif < 0);

        el('turno-confirmar').disabled = !valido;
    }

    async function confirmarCierre() {
        if (!turno) return;

        const contado = parseFloat(el('turno-contado').value);
        const dif = r2(contado - turno.esperado);

        const aviso = dif === 0 ?
            'La caja cuadra. ¿Cerrar el turno?' :
            'Hay una diferencia de ' + crc(Math.abs(dif)) + (dif > 0 ? ' (sobrante)' : ' (faltante)') + '. ¿Cerrar el turno de todos modos?';

        const aceptado = await Restaurante.confirmar(aviso, {
            titulo: 'Cierre de turno',
            aceptar: 'Cerrar turno',
            icono: 'ti-lock'
        });

        if (!aceptado) return;

        const boton = el('turno-confirmar');
        boton.disabled = true;
        boton.textContent = 'Cerrando...';
        Restaurante.cargando(boton, true);

        try {
            const respuesta = await fetch(config.urlTurnos + '/' + turno.id_turno + '/cerrar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ monto_final: contado })
            });

            const resultado = await respuesta.json();

            if (respuesta.ok && resultado.success) {
                cerrarTurnoModal();
                Restaurante.notificar('Turno cerrado. Esperado: ' + crc(resultado.esperado) +
                    ' · Contado: ' + crc(resultado.contado) +
                    ' · Diferencia: ' + crc(resultado.diferencia), 'exito', 2600);
                setTimeout(() => window.location.reload(), 2600);
                return;
            }

            Restaurante.notificar('No se pudo cerrar el turno: ' + (resultado.error || 'Error desconocido'), 'error', 6000);
        } catch (error) {
            console.error(error);
            Restaurante.notificar('Ocurrió un error de red al comunicarse con el servidor.', 'error', 6000);
        }

        Restaurante.cargando(boton, false);
        boton.textContent = 'Cerrar turno';
        boton.disabled = false;
    }

    const textoEstado = el('caja-estado').textContent;

    function limpiarCobro() {
        actual = null;
        pagos = [];
        el('cobro-titulo').textContent = 'Ninguno seleccionado';
        el('cobro-form').hidden = true;
        el('cobro-vacio').hidden = false;
    }

    iniciarSondeo({
        url: config.urlEstado,
        intervalo: config.intervalo,
        pausar: () => procesando,
        alRecibir: html => {
            const anteriores = new Set(Object.keys(pedidos));
            el('caja-grid').innerHTML = html;
            pedidos = leerPedidos();

            document.querySelectorAll('.pedido-card').forEach(c => {
                if (!anteriores.has(c.dataset.id)) c.classList.add('pedido-card--nuevo');
                else c.classList.add('pedido-card--estable');
            });

            el('caja-estado').classList.remove('caja-subtitulo--error');

            if (!actual) return;

            const nuevo = pedidos[actual.id];

            if (!nuevo) {
                limpiarCobro();
                return;
            }

            document.querySelectorAll('.pedido-card').forEach(c => c.classList.toggle('seleccionado', c.dataset.id == actual.id));

            if (nuevo.subtotal !== actual.subtotal) {
                actual = nuevo;
                mostrarItems();
                calcular();
            }
        },
        alFallar: () => {
            el('caja-estado').textContent = 'Sin conexión con el servidor, reintentando...';
            el('caja-estado').classList.add('caja-subtitulo--error');
        },
        alRecuperar: () => {
            el('caja-estado').textContent = textoEstado;
            el('caja-estado').classList.remove('caja-subtitulo--error');
        }
    });
})();
