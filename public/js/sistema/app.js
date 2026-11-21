(function () {
    const Restaurante = window.Restaurante = window.Restaurante || {};

    const ICONOS = {
        exito: 'ti-circle-check',
        error: 'ti-alert-circle',
        aviso: 'ti-alert-triangle',
        info: 'ti-info-circle'
    };

    Restaurante.datos = function (id) {
        const nodo = document.getElementById(id);
        if (!nodo) return {};

        try {
            return JSON.parse(nodo.textContent);
        } catch (error) {
            console.error('No se pudieron leer los datos de la página', id, error);
            return {};
        }
    };

    Restaurante.notificar = function (mensaje, tipo = 'info', duracion = 4200) {
        let contenedor = document.getElementById('notificaciones');

        if (!contenedor) {
            contenedor = document.createElement('div');
            contenedor.id = 'notificaciones';
            contenedor.className = 'notificaciones';
            contenedor.setAttribute('aria-live', 'polite');
            document.body.appendChild(contenedor);
        }

        const aviso = document.createElement('div');
        aviso.className = 'notificacion notificacion--' + tipo;
        aviso.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
        aviso.style.setProperty('--duracion-notificacion', duracion + 'ms');

        const icono = document.createElement('i');
        icono.className = 'ti ' + (ICONOS[tipo] || ICONOS.info);

        const texto = document.createElement('div');
        texto.className = 'notificacion__texto';
        texto.textContent = mensaje;

        const cerrar = document.createElement('button');
        cerrar.type = 'button';
        cerrar.className = 'notificacion__cerrar';
        cerrar.setAttribute('aria-label', 'Cerrar aviso');
        cerrar.innerHTML = '<i class="ti ti-x"></i>';

        const barra = document.createElement('span');
        barra.className = 'notificacion__barra';

        aviso.append(icono, texto, cerrar, barra);
        contenedor.appendChild(aviso);

        let temporizador = setTimeout(quitar, duracion);

        function quitar() {
            clearTimeout(temporizador);
            aviso.classList.add('saliendo');
            aviso.addEventListener('animationend', () => aviso.remove(), { once: true });
            setTimeout(() => aviso.remove(), 400);
        }

        cerrar.addEventListener('click', quitar);
        aviso.addEventListener('mouseenter', () => clearTimeout(temporizador));
        aviso.addEventListener('mouseleave', () => { temporizador = setTimeout(quitar, 1800); });

        return aviso;
    };

    Restaurante.confirmar = function (mensaje, opciones = {}) {
        return new Promise(resolver => {
            const anterior = document.activeElement;
            const fondo = document.createElement('div');
            fondo.className = 'modal-fondo';
            fondo.innerHTML =
                '<div class="modal" role="alertdialog" aria-modal="true" aria-labelledby="modal-titulo" aria-describedby="modal-texto">' +
                '<div class="modal__icono"><i class="ti ' + (opciones.icono || 'ti-alert-triangle') + '"></i></div>' +
                '<h2 class="modal__titulo" id="modal-titulo"></h2>' +
                '<p class="modal__texto" id="modal-texto"></p>' +
                '<div class="modal__acciones">' +
                '<button type="button" class="btn btn--secundario" data-respuesta="no">Cancelar</button>' +
                '<button type="button" class="btn btn--peligro" data-respuesta="si"></button>' +
                '</div></div>';

            fondo.querySelector('#modal-titulo').textContent = opciones.titulo || 'Confirmar acción';
            fondo.querySelector('#modal-texto').textContent = mensaje;
            fondo.querySelector('[data-respuesta="si"]').textContent = opciones.aceptar || 'Sí, continuar';

            function responder(valor) {
                document.removeEventListener('keydown', teclas);
                fondo.classList.add('cerrando');
                fondo.addEventListener('animationend', () => fondo.remove(), { once: true });
                setTimeout(() => fondo.remove(), 400);
                if (anterior && anterior.focus) anterior.focus();
                resolver(valor);
            }

            function teclas(evento) {
                if (evento.key === 'Escape') responder(false);
            }

            fondo.addEventListener('click', evento => {
                const boton = evento.target.closest('[data-respuesta]');
                if (boton) responder(boton.dataset.respuesta === 'si');
                else if (evento.target === fondo) responder(false);
            });

            document.addEventListener('keydown', teclas);
            document.body.appendChild(fondo);
            fondo.querySelector('[data-respuesta="no"]').focus();
        });
    };

    Restaurante.cargando = function (boton, activo) {
        if (!boton) return;
        boton.classList.toggle('cargando', activo);
        boton.setAttribute('aria-busy', activo ? 'true' : 'false');
    };

    document.addEventListener('submit', async evento => {
        const formulario = evento.target;
        const mensaje = formulario.getAttribute('data-confirm');

        if (mensaje) {
            evento.stopImmediatePropagation();

            if (formulario.dataset.confirmado !== '1') {
                evento.preventDefault();

                const aceptado = await Restaurante.confirmar(mensaje, {
                    titulo: formulario.dataset.confirmTitulo || 'Confirmar acción',
                    aceptar: formulario.dataset.confirmAceptar || 'Sí, continuar'
                });

                if (aceptado) {
                    formulario.dataset.confirmado = '1';
                    formulario.requestSubmit ? formulario.requestSubmit(evento.submitter || undefined) : formulario.submit();
                }

                return;
            }
        }

        delete formulario.dataset.confirmado;

        if (formulario.hasAttribute('data-formulario')) {
            Restaurante.cargando(formulario.querySelector('button[type="submit"]'), true);
        }
    }, true);

    document.addEventListener('click', evento => {
        if (window.bootstrap) return;

        const disparador = evento.target.closest('[data-bs-toggle="collapse"]');
        if (!disparador) return;

        const destino = document.querySelector(disparador.getAttribute('data-bs-target') || disparador.getAttribute('href'));
        if (!destino) return;

        evento.preventDefault();
        const abierto = destino.classList.toggle('show');
        disparador.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        disparador.classList.toggle('collapsed', !abierto);
    });

    window.addEventListener('pageshow', () => {
        document.querySelectorAll('.btn.cargando').forEach(boton => Restaurante.cargando(boton, false));
    });

    Restaurante.odometro = function (elemento, pausaInicial) {
        const texto = (elemento.dataset.valor || elemento.textContent).trim();
        const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (sinMovimiento || elemento.dataset.odometroListo || !/\d/.test(texto)) {
            elemento.textContent = texto;
            return;
        }

        elemento.dataset.odometroListo = '1';
        elemento.setAttribute('aria-label', texto);
        elemento.textContent = '';
        elemento.classList.add('odometro');

        const columnas = [];

        Array.from(texto).forEach(caracter => {
            const envoltura = document.createElement('span');
            envoltura.setAttribute('aria-hidden', 'true');

            if (!/\d/.test(caracter)) {
                envoltura.className = 'digito digito--fijo';
                envoltura.textContent = caracter;
                elemento.appendChild(envoltura);
                return;
            }

            envoltura.className = 'digito';
            const columna = document.createElement('span');
            columna.className = 'digito__columna';

            for (let n = 0; n < 20; n++) {
                const numero = document.createElement('span');
                numero.textContent = n % 10;
                columna.appendChild(numero);
            }

            envoltura.appendChild(columna);
            elemento.appendChild(envoltura);
            columnas.push({ columna, destino: 10 + Number(caracter) });
        });

        columnas.forEach((item, indice) => {
            item.columna.style.setProperty('--duracion-giro', (1100 + indice * 120) + 'ms');
            setTimeout(() => {
                item.columna.style.transform = 'translateY(' + (-item.destino * 5) + '%)';
            }, (pausaInicial || 0) + (columnas.length - indice) * 55);
        });

        const fin = (pausaInicial || 0) + columnas.length * 55 + 1100 + columnas.length * 120 + 150;

        setTimeout(() => {
            elemento.classList.remove('odometro');
            elemento.removeAttribute('aria-label');
            elemento.textContent = texto;
        }, fin);
    };

    const pendientesEntrada = [];
    const bienvenida = document.getElementById('bienvenida');
    const movimientoReducido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let entradaLiberada = !bienvenida;

    Restaurante.alEntrar = function (funcion) {
        if (entradaLiberada) funcion();
        else pendientesEntrada.push(funcion);
    };

    function liberarEntrada() {
        if (entradaLiberada) return;
        entradaLiberada = true;
        document.documentElement.classList.add('bienvenida-lista');
        pendientesEntrada.splice(0).forEach(funcion => funcion());
    }

    if (bienvenida && !movimientoReducido) {
        const quitar = () => bienvenida.remove();
        let saltada = false;
        const saltar = () => {
            if (saltada) return;
            saltada = true;
            bienvenida.classList.add('bienvenida--saltar');
            liberarEntrada();
            setTimeout(quitar, 300);
        };

        bienvenida.addEventListener('click', saltar);
        document.addEventListener('keydown', saltar, { once: true });
        setTimeout(liberarEntrada, 1450);
        setTimeout(quitar, 2700);
    } else if (bienvenida) {
        bienvenida.remove();
        liberarEntrada();
    }

    Restaurante.alEntrar(() => {
        document.querySelectorAll('[data-odometro]').forEach((elemento, indice) => {
            Restaurante.odometro(elemento, 200 + indice * 110);
        });
    });

    const encabezado = document.querySelector('.page-wrapper > div > header');

    if (encabezado) {
        const marcar = () => encabezado.classList.toggle('encabezado--desplazado', window.scrollY > 4);
        marcar();
        window.addEventListener('scroll', marcar, { passive: true });
    }

    const lateral = document.getElementById('menu-lateral');
    const navegacion = lateral ? lateral.querySelector('.lateral__nav') : null;

    if (navegacion) {
        const resaltado = document.createElement('span');
        resaltado.className = 'lateral__resaltado';
        resaltado.setAttribute('aria-hidden', 'true');
        navegacion.prepend(resaltado);

        const mover = enlace => {
            if (!enlace) return;
            resaltado.style.setProperty('--y-resaltado', enlace.offsetTop + 'px');
            resaltado.style.setProperty('--alto-resaltado', enlace.offsetHeight + 'px');
            navegacion.classList.add('lateral__nav--resaltando');
        };

        navegacion.addEventListener('pointerover', evento => mover(evento.target.closest('.lateral__enlace')));
        navegacion.addEventListener('focusin', evento => mover(evento.target.closest('.lateral__enlace')));
        navegacion.addEventListener('pointerleave', () => navegacion.classList.remove('lateral__nav--resaltando'));
        navegacion.addEventListener('focusout', evento => {
            if (!navegacion.contains(evento.relatedTarget)) navegacion.classList.remove('lateral__nav--resaltando');
        });

        const escritorio = window.matchMedia('(min-width: 1024px)');
        const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const botonContraer = lateral.querySelector('[data-contraer-menu]');
        const etiqueta = document.createElement('div');
        etiqueta.className = 'etiqueta-menu';
        etiqueta.setAttribute('aria-hidden', 'true');
        document.body.appendChild(etiqueta);

        const contraido = () => !escritorio.matches || lateral.classList.contains('lateral--contraida');

        lateral.querySelectorAll('.marca-texto, .lateral__titulo, .lateral__texto').forEach((elemento, indice) => {
            elemento.style.setProperty('--orden', Math.min(indice, 30));
        });

        const actualizarBoton = () => {
            if (!botonContraer) return;
            const cerrado = lateral.classList.contains('lateral--contraida');
            botonContraer.setAttribute('aria-expanded', cerrado ? 'false' : 'true');
            botonContraer.setAttribute('aria-label', cerrado ? 'Expandir el menú' : 'Contraer el menú');
        };

        const ocultarEtiqueta = () => etiqueta.classList.remove('etiqueta-menu--visible');

        const mostrarEtiqueta = enlace => {
            if (!enlace || !contraido()) {
                ocultarEtiqueta();
                return;
            }
            const caja = enlace.getBoundingClientRect();
            etiqueta.textContent = enlace.dataset.etiqueta || '';
            etiqueta.style.left = (lateral.getBoundingClientRect().right + 10) + 'px';
            etiqueta.style.top = (caja.top + caja.height / 2) + 'px';
            etiqueta.classList.add('etiqueta-menu--visible');
        };

        if (botonContraer) {
            actualizarBoton();

            botonContraer.addEventListener('click', () => {
                const cerrar = !lateral.classList.contains('lateral--contraida');
                ocultarEtiqueta();
                lateral.classList.toggle('lateral--contraida', cerrar);
                actualizarBoton();

                try {
                    document.cookie = 'menu_contraido=' + (cerrar ? '1' : '0') + '; path=/; max-age=31536000; SameSite=Lax';
                } catch (error) {
                }

                if (!cerrar && !sinMovimiento) {
                    lateral.classList.remove('lateral--expandiendo');
                    void lateral.offsetWidth;
                    lateral.classList.add('lateral--expandiendo');
                    setTimeout(() => lateral.classList.remove('lateral--expandiendo'), 1200);
                }
            });
        }

        navegacion.addEventListener('pointerover', evento => mostrarEtiqueta(evento.target.closest('.lateral__enlace')));
        navegacion.addEventListener('focusin', evento => mostrarEtiqueta(evento.target.closest('.lateral__enlace')));
        navegacion.addEventListener('pointerleave', ocultarEtiqueta);
        navegacion.addEventListener('focusout', evento => {
            if (!navegacion.contains(evento.relatedTarget)) ocultarEtiqueta();
        });
        navegacion.addEventListener('scroll', ocultarEtiqueta, { passive: true });

        let primeraVez = false;

        try {
            primeraVez = !sessionStorage.getItem('menu-presentado');
            sessionStorage.setItem('menu-presentado', '1');
        } catch (error) {
            primeraVez = false;
        }

        if (primeraVez && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            lateral.querySelectorAll('.lateral__marca, .lateral__titulo, .lateral__enlace').forEach((elemento, indice) => {
                elemento.style.setProperty('--orden', Math.min(indice, 24));
            });
            lateral.classList.add('lateral--presentacion');
            Restaurante.alEntrar(() => setTimeout(() => lateral.classList.remove('lateral--presentacion'), 2600));
        }
    }

    const contenedorSistema = document.getElementById('main-wrapper');

    if (contenedorSistema) {
        const origenScript = document.currentScript ? document.currentScript.src : '';
        const urlLatido = origenScript.includes('/js/sistema/app.js')
            ? origenScript.replace(/\/js\/sistema\/app\.js.*$/, '/sesion/latido')
            : new URL('/sesion/latido', window.location.origin).href;

        setInterval(async () => {
            try {
                const respuesta = await fetch(urlLatido, {
                    cache: 'no-store',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });

                if (respuesta.status === 401 || respuesta.status === 419 || respuesta.redirected) {
                    window.location.reload();
                }
            } catch (error) {
            }
        }, 60000);
    }

    if (contenedorSistema && navigator.locks) {
        const nombreBloqueo = 'restaurante-pestana-activa';
        const retener = () => new Promise(() => {});
        let aviso = null;

        const bloquear = () => {
            if (aviso) return;

            aviso = document.createElement('div');
            aviso.className = 'pestana-duplicada';
            aviso.setAttribute('role', 'alertdialog');
            aviso.setAttribute('aria-modal', 'true');
            aviso.setAttribute('aria-labelledby', 'pestana-duplicada-titulo');
            aviso.innerHTML =
                '<div class="pestana-duplicada__tarjeta">' +
                    '<span class="pestana-duplicada__icono"><i class="ti ti-lock"></i></span>' +
                    '<h2 id="pestana-duplicada-titulo">La cuenta ya está abierta en otra pestaña</h2>' +
                    '<p>Solo se puede usar una pestaña a la vez para evitar pedidos o cobros duplicados. ' +
                    'Vuelve a la otra pestaña o ciérrala; esta se activará sola cuando la otra se cierre.</p>' +
                    '<span class="pestana-duplicada__espera"><span></span> Esperando a que la otra pestaña se cierre</span>' +
                '</div>';

            contenedorSistema.setAttribute('inert', '');
            contenedorSistema.setAttribute('aria-hidden', 'true');
            document.documentElement.classList.add('pestana-bloqueada');
            document.body.appendChild(aviso);
        };

        const claveCambio = 'restaurante-pestana-cambiando';
        const margenCambio = 5000;
        let tengoTurno = false;

        const ultimoCambio = () => {
            try {
                return Number(localStorage.getItem(claveCambio)) || 0;
            } catch (error) {
                return 0;
            }
        };

        const esperarTurno = () => {
            navigator.locks.request(nombreBloqueo, () => {
                const transcurrido = Date.now() - ultimoCambio();

                if (transcurrido < margenCambio) {
                    setTimeout(esperarTurno, margenCambio - transcurrido + 300);
                    return null;
                }

                if (aviso) {
                    window.location.reload();
                }

                return retener();
            });
        };

        window.addEventListener('pagehide', () => {
            if (!tengoTurno) return;

            try {
                localStorage.setItem(claveCambio, String(Date.now()));
            } catch (error) {
            }
        });

        const controlador = new AbortController();
        const limite = setTimeout(() => controlador.abort(), 1500);

        navigator.locks.request(nombreBloqueo, { signal: controlador.signal }, () => {
            clearTimeout(limite);
            tengoTurno = true;

            try {
                localStorage.removeItem(claveCambio);
            } catch (error) {
            }

            return retener();
        }).catch(() => {
            bloquear();
            esperarTurno();
        });
    }

})();
