document.addEventListener('DOMContentLoaded', () => {
    const overlay = document.getElementById('comanda-overlay');
    const contenidoMenu = document.getElementById('contenido-menu');
    const ticket = document.getElementById('ticket-productos');
    const mesaLabel = document.getElementById('mesa-seleccionada');
    const btnRegistrar = document.getElementById('registrar-comanda');
    const config = Restaurante.datos('datos-mesero');
    const csrf = config.csrf;

    const carritos = {};
    const tokens = {};

    let mesaActual = null;
    let tokenPedido = null;
    let pedido = {};
    let enviando = false;
    let entregando = 0;
    let sondeo = null;

    function generarTokenIdempotencia() {
        if (window.crypto && crypto.randomUUID) {
            return 'token-' + crypto.randomUUID();
        }

        return 'token-' + Date.now() + '-' + Math.random().toString(36).substring(2, 11);
    }

    const textoBoton = () => mesaActual && mesaActual.pedido
        ? 'Agregar al pedido #' + mesaActual.pedido
        : 'Registrar comanda';

    const formatoCRC = valor =>
        '₡' + Number(valor).toLocaleString('es-CR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });

    const normalizar = texto => String(texto || '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .trim();

    function abrirModal() {
        overlay.classList.add('activa');
        overlay.setAttribute('aria-hidden', 'false');
    }

    function cerrarModal() {
        overlay.classList.remove('activa');
        overlay.setAttribute('aria-hidden', 'true');
    }

    function etiquetaMesa() {
        mesaLabel.textContent = mesaActual.numero +
            (mesaActual.pedido ? ' (agregar al pedido #' + mesaActual.pedido + ')' : '');
        btnRegistrar.textContent = textoBoton();
    }

    function marcarMesaSeleccionada() {
        document.querySelectorAll('.mesa-card').forEach(card => {
            card.classList.toggle('seleccionada', Boolean(mesaActual) && card.dataset.mesaId === mesaActual.id);
        });
    }

    function actualizarContador() {
        const contador = document.getElementById('menu-contador');
        if (!contador) return;

        const unidades = Object.values(pedido).reduce((total, p) => total + p.cantidad, 0);
        contador.textContent = unidades + (unidades === 1 ? ' producto en el pedido' : ' productos en el pedido');
    }

    function filtrarMenu() {
        const buscador = document.getElementById('menu-buscar');
        const activa = contenidoMenu.querySelector('.menu-chip.activa');
        const texto = normalizar(buscador ? buscador.value : '');
        const categoria = activa ? activa.dataset.categoria : 'todas';
        let visibles = 0;

        contenidoMenu.querySelectorAll('.menu-seccion').forEach(seccion => {
            const enCategoria = categoria === 'todas' || seccion.dataset.categoria === categoria;
            let visiblesSeccion = 0;

            seccion.querySelectorAll('.menu-producto').forEach(producto => {
                const coincide = enCategoria && (texto === '' || normalizar(producto.dataset.nombre).includes(texto));
                producto.hidden = !coincide;
                if (coincide) visiblesSeccion++;
            });

            seccion.hidden = visiblesSeccion === 0;
            visibles += visiblesSeccion;
        });

        const vacio = document.getElementById('menu-sin-resultados');
        if (vacio) vacio.hidden = visibles > 0 || !contenidoMenu.querySelector('.menu-seccion');
    }

    document.addEventListener('click', async event => {
        const mesa = event.target.closest('.mesa-card');

        if (mesa && !mesa.disabled) {
            const idMesa = mesa.dataset.mesaId;

            carritos[idMesa] = carritos[idMesa] || {};
            tokens[idMesa] = tokens[idMesa] || generarTokenIdempotencia();

            pedido = carritos[idMesa];
            tokenPedido = tokens[idMesa];

            mesaActual = {
                id: idMesa,
                numero: mesa.dataset.mesaNumero,
                pedido: mesa.dataset.pedidoId || null
            };

            marcarMesaSeleccionada();
            etiquetaMesa();
            renderTicket();
            abrirModal();

            contenidoMenu.innerHTML = '<div class="menu-cargando"><span class="cargador"></span> Cargando menú…</div>';

            try {
                const url = config.urlMesas + '/' +
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
                actualizarContador();

                const buscador = document.getElementById('menu-buscar');
                if (buscador) buscador.focus();

            } catch (error) {
                contenidoMenu.innerHTML = '<div class="menu-cargando menu-cargando--error"><i class="ti ti-wifi-off"></i> No se pudo cargar el menú. Inténtalo nuevamente.</div>';
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

    overlay.addEventListener('input', event => {
        if (event.target.id === 'menu-buscar') {
            filtrarMenu();
        }
    });

    overlay.addEventListener('click', event => {
        const chip = event.target.closest('.menu-chip');
        if (!chip) return;

        contenidoMenu.querySelectorAll('.menu-chip').forEach(c => c.classList.toggle('activa', c === chip));
        filtrarMenu();
    });

    overlay.addEventListener('click', event => {
        const boton = event.target.closest('.comanda-agregar');

        if (!boton || !mesaActual) return;

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
        pedido[id].recien = true;

        renderTicket();

        boton.classList.add('agregado');
        boton.innerHTML = '<i class="ti ti-check"></i> Agregado (' + pedido[id].cantidad + ')';

        clearTimeout(boton._restaurar);
        boton._restaurar = setTimeout(() => {
            boton.classList.remove('agregado');
            boton.innerHTML = '<i class="ti ti-plus"></i> Agregar';
        }, 900);
    });

    function renderTicket() {
        ticket.replaceChildren();

        const productos = Object.values(pedido);
        let subtotal = 0;

        if (productos.length === 0) {
            const vacio = document.createElement('div');
            vacio.className = 'ticket-vacio';
            vacio.innerHTML = '<i class="ti ti-shopping-cart"></i><span>Agrega productos a la comanda.</span>';
            ticket.appendChild(vacio);

            btnRegistrar.disabled = true;
            btnRegistrar.className = 'btn-registrar';
        } else {
            btnRegistrar.disabled = enviando;
            btnRegistrar.className = 'btn-registrar activo';
        }

        productos.forEach(producto => {
            subtotal += producto.precio * producto.cantidad;

            const fila = document.createElement('div');
            fila.className = 'ticket-item' + (producto.recien ? ' ticket-item--nuevo' : '');
            producto.recien = false;

            const encabezado = document.createElement('div');
            encabezado.className = 'ticket-item__cabecera';

            const nombre = document.createElement('span');
            nombre.className = 'ticket-item__nombre';
            nombre.textContent = producto.nombre;

            const importe = document.createElement('span');
            importe.className = 'ticket-item__importe num';
            importe.textContent = formatoCRC(producto.precio * producto.cantidad);

            encabezado.append(nombre, importe);

            const inputNota = document.createElement('input');
            inputNota.type = 'text';
            inputNota.maxLength = 200;
            inputNota.placeholder = 'Notas libres (ej. sin cebolla, término medio)';
            inputNota.className = 'ticket-item__nota';
            inputNota.value = producto.notas || '';
            inputNota.addEventListener('input', e => {
                producto.notas = e.target.value;
            });

            const controles = document.createElement('div');
            controles.className = 'ticket-cantidad';


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

        actualizarContador();
    }

    function vaciarMesaActual() {
        delete carritos[mesaActual.id];
        delete tokens[mesaActual.id];

        carritos[mesaActual.id] = {};
        tokens[mesaActual.id] = generarTokenIdempotencia();

        pedido = carritos[mesaActual.id];
        tokenPedido = tokens[mesaActual.id];
    }

    btnRegistrar.addEventListener('click', async () => {
        if (enviando) return;

        if (!mesaActual || Object.keys(pedido).length === 0) {
            Restaurante.notificar('Selecciona una mesa y agrega productos a la comanda.', 'aviso');
            return;
        }

        enviando = true;
        btnRegistrar.disabled = true;
        btnRegistrar.textContent = 'Procesando y guardando...';

        const detalles = Object.values(pedido).map((p, idx) => ({
            num_linea: idx + 1,
            id_producto: p.id,
            cantidad: p.cantidad,
            notas: p.notas || ''
        }));

        const agregando = Boolean(mesaActual.pedido);

        const url = agregando ?
            config.urlPedidos + '/' + mesaActual.pedido + '/agregar' :
            config.urlGuardar;

        const payload = agregando ? {
            detalles: detalles
        } : {
            token_idempotencia: tokenPedido,
            id_mesa: mesaActual.id,
            observaciones: 'Comanda tomada desde interfaz de Mesero',
            detalles: detalles
        };

        try {
            const respuesta = await fetch(url, {
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
                Restaurante.notificar(agregando ?
                    'Productos agregados al pedido #' + resultado.id_pedido :
                    'Comanda #' + resultado.id_pedido + ' registrada y enviada a cocina.', 'exito');

                mesaActual.pedido = String(resultado.id_pedido);
                vaciarMesaActual();
            } else {
                if (resultado.id_pedido_abierto) {
                    mesaActual.pedido = String(resultado.id_pedido_abierto);
                }

                Restaurante.notificar('No se pudo registrar la comanda: ' + (resultado.error || resultado.message), 'error', 6000);
            }
        } catch (error) {
            console.error('Error:', error);
            Restaurante.notificar('Ocurrió un error de red al comunicarse con el servidor.', 'error', 6000);
        }

        enviando = false;
        etiquetaMesa();
        renderTicket();

        if (sondeo) sondeo.ahora();
    });

    document.addEventListener('click', async event => {
        const boton = event.target.closest('.btn-entregar');
        if (!boton || boton.disabled) return;

        boton.disabled = true;
        entregando++;

        try {
            const respuesta = await fetch(config.urlPedidos + '/' + boton.dataset.id + '/entregar', {
                    method: 'PATCH',
                    headers: {
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json'
                    }
                });

            const resultado = await respuesta.json();

            if (!(respuesta.ok && resultado.success)) {
                Restaurante.notificar('No se pudo marcar como entregado: ' + (resultado.error || 'Error desconocido'), 'error');
                boton.disabled = false;
            } else {
                Restaurante.notificar('Pedido #' + boton.dataset.id + ' marcado como entregado.', 'exito');
            }
        } catch (error) {
            console.error(error);
            Restaurante.notificar('Ocurrió un error de red al comunicarse con el servidor.', 'error', 6000);
            boton.disabled = false;
        }

        // Antes el sondeo se pausaba mientras hubiera un botón deshabilitado, y como solo el
        // sondeo reemplaza ese botón, la pantalla dejaba de actualizarse para siempre.
        entregando--;
        if (sondeo) sondeo.ahora();
    });

    const contenedorEstado = document.getElementById('mesas-estado');
    const fechaTicket = document.getElementById('ticket-fecha');

    function actualizarFecha() {
        const ahora = new Date();
        fechaTicket.textContent = ahora.toLocaleDateString('es-CR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        }) + ' ' + ahora.toLocaleTimeString('es-CR', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });
    }

    function avisoConexion(texto) {
        const subtitulo = contenedorEstado.querySelector('.mesas-subtitulo');
        if (!subtitulo) return;

        subtitulo.dataset.original = subtitulo.dataset.original || subtitulo.textContent;
        subtitulo.textContent = texto || subtitulo.dataset.original;
    }

    let ultimoEstado = null;

    function claveNodo(nodo) {
        return nodo.classList.contains('btn-entregar') ?
            'entrega:' + nodo.dataset.id :
            'mesa:' + nodo.dataset.mesaId + ':' + nodo.className.replace(/\s*(seleccionada|cambio-reciente)\b/g, '') + ':' + (nodo.dataset.pedidoId || '');
    }

    function firmaEstado() {
        return new Set(Array.from(contenedorEstado.querySelectorAll('.mesa-card, .btn-entregar')).map(claveNodo));
    }

    sondeo = iniciarSondeo({
        url: config.urlEstado,
        intervalo: config.intervalo,
        pausar: () => enviando || entregando > 0,
        alRecibir: html => {
            const recibido = html.trim();
            actualizarFecha();

            if (recibido === ultimoEstado) return;

            const primera = ultimoEstado === null;
            const anteriores = firmaEstado();
            ultimoEstado = recibido;

            contenedorEstado.classList.add('sin-entrada');
            contenedorEstado.innerHTML = html;

            if (!primera) {
                contenedorEstado.querySelectorAll('.mesa-card, .btn-entregar').forEach(nodo => {
                    const clave = claveNodo(nodo);
                    if (anteriores.has(clave)) return;

                    const tarjeta = nodo.closest('.entrega-card');
                    (tarjeta || nodo).classList.add('cambio-reciente');

                    // Aviso visible aunque el menú de una mesa tape el panel de entregas
                    if (tarjeta) {
                        const mesa = tarjeta.querySelector('.entrega-top strong');
                        Restaurante.notificar(
                            'Pedido #' + nodo.dataset.id + (mesa ? ' (' + mesa.textContent.trim() + ')' : '') + ' listo para entregar.',
                            'exito',
                            6000
                        );
                    }
                });
                contenedorEstado.classList.add('refrescado');
                setTimeout(() => contenedorEstado.classList.remove('refrescado'), 700);
            }

            if (!mesaActual) return;

            const card = contenedorEstado.querySelector('.mesa-card[data-mesa-id="' + mesaActual.id + '"]');

            if (card) {
                mesaActual.pedido = card.dataset.pedidoId || null;
                etiquetaMesa();
            }

            marcarMesaSeleccionada();
        },
        alFallar: () => avisoConexion('Sin conexión con el servidor, reintentando...'),
        alRecuperar: () => avisoConexion(null)
    });
});
