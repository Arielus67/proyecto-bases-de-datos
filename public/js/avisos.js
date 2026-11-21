/*
 * Avisos flotantes (reemplazan a alert()).
 *
 *   const aviso = avisar.cargando('Guardando comanda...');   // muestra un indicador de espera
 *   aviso.exito('Comanda #12 registrada');                    // se pone verde con check y se oculta sola
 *   aviso.error('No hay conexión con el servidor');           // se pone roja con X y se queda hasta cerrarla
 *
 *   avisar.exito('Estado actualizado');                       // atajos sin estado de espera
 *   avisar.error('Selecciona una mesa');
 */
(function () {
    const DURACION_EXITO_MS = 3500;

    let contenedor = null;

    const ESTILOS = `
        .avisos-flotante { position: fixed; right: 20px; bottom: 20px; z-index: 9999;
            display: flex; flex-direction: column; gap: 10px; align-items: flex-end;
            max-width: min(380px, calc(100vw - 40px)); pointer-events: none; }
        .aviso { pointer-events: auto; display: flex; align-items: flex-start; gap: 12px;
            width: 100%; padding: 12px 14px; border-radius: 12px; color: #fff;
            font: 500 14px/1.4 "DM Sans", system-ui, sans-serif;
            box-shadow: 0 10px 25px rgba(0,0,0,.25); background: #4b5563;
            animation: aviso-entrar .2s ease-out; transition: background-color .25s ease; }
        .aviso .aviso-icono { flex: 0 0 26px; height: 26px; border-radius: 999px;
            display: flex; align-items: center; justify-content: center; }
        .aviso .aviso-icono svg { width: 16px; height: 16px; }
        .aviso .aviso-texto { flex: 1; white-space: pre-line; padding-top: 2px; }
        .aviso .aviso-cerrar { flex: 0 0 auto; background: transparent; border: 0; color: #fff;
            opacity: .8; cursor: pointer; font-size: 18px; line-height: 1; padding: 0 2px; }
        .aviso .aviso-cerrar:hover { opacity: 1; }
        .aviso.cargando { background: #4b5563; }
        .aviso.cargando .aviso-icono { border: 3px solid rgba(255,255,255,.35); border-top-color: #fff;
            animation: aviso-girar .8s linear infinite; }
        .aviso.exito { background: #16a34a; }
        .aviso.exito .aviso-icono { background: rgba(255,255,255,.2); }
        .aviso.error { background: #dc2626; }
        .aviso.error .aviso-icono { background: rgba(255,255,255,.2); }
        @keyframes aviso-girar { to { transform: rotate(360deg); } }
        @keyframes aviso-entrar { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .aviso, .aviso .aviso-icono { animation: none !important; } }
    `;

    const ICONO_EXITO = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>';
    const ICONO_ERROR = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>';

    function asegurarContenedor() {
        if (contenedor) return contenedor;

        const estilo = document.createElement('style');
        estilo.textContent = ESTILOS;
        document.head.appendChild(estilo);

        contenedor = document.createElement('div');
        contenedor.className = 'avisos-flotante';
        contenedor.setAttribute('role', 'status');
        contenedor.setAttribute('aria-live', 'polite');
        document.body.appendChild(contenedor);

        return contenedor;
    }

    function crear(mensaje, estado) {
        const raiz = asegurarContenedor();

        const elemento = document.createElement('div');
        elemento.className = 'aviso ' + estado;

        const icono = document.createElement('span');
        icono.className = 'aviso-icono';
        if (estado === 'exito') icono.innerHTML = ICONO_EXITO;
        else if (estado === 'error') icono.innerHTML = ICONO_ERROR;

        const texto = document.createElement('span');
        texto.className = 'aviso-texto';
        texto.textContent = mensaje;

        const cerrar = document.createElement('button');
        cerrar.type = 'button';
        cerrar.className = 'aviso-cerrar';
        cerrar.setAttribute('aria-label', 'Cerrar aviso');
        cerrar.textContent = '×';
        cerrar.addEventListener('click', () => quitar(elemento));

        elemento.append(icono, texto, cerrar);
        raiz.appendChild(elemento);

        return { elemento, icono, texto };
    }

    function quitar(elemento) {
        if (!elemento || !elemento.parentNode) return;
        elemento.remove();
    }

    function pintar(partes, estado, mensaje) {
        const { elemento, icono, texto } = partes;
        elemento.classList.remove('cargando', 'exito', 'error');
        elemento.classList.add(estado);

        if (estado === 'exito') icono.innerHTML = ICONO_EXITO;
        else if (estado === 'error') icono.innerHTML = ICONO_ERROR;
        else icono.innerHTML = '';

        if (mensaje !== undefined) texto.textContent = mensaje;
    }

    function cargando(mensaje) {
        const partes = crear(mensaje || 'Procesando...', 'cargando');
        const { elemento } = partes;

        return {
            exito(msg, duracion) {
                pintar(partes, 'exito', msg);
                setTimeout(() => quitar(elemento), duracion ?? DURACION_EXITO_MS);
            },
            error(msg) {
                pintar(partes, 'error', msg);
            },
            cerrar() {
                quitar(elemento);
            }
        };
    }

    window.avisar = {
        cargando,
        exito(mensaje, duracion) {
            cargando().exito(mensaje, duracion);
        },
        error(mensaje) {
            const partes = crear(mensaje, 'error');
            // Los errores se quedan visibles hasta que el usuario los cierra
            return { cerrar: () => quitar(partes.elemento) };
        }
    };
})();
