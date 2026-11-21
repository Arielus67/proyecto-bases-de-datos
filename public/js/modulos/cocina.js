(function () {
    const config = Restaurante.datos('datos-cocina');
    const grid = document.getElementById('kds-grid');
    const hora = document.getElementById('kds-hora-act');
    const enVivo = document.getElementById('kds-en-vivo');
    const actualizar = document.getElementById('kds-actualizar');
    let sondeo = null;
    let firmaAnterior = firmar();

    function firmar() {
        return Array.from(grid.querySelectorAll('[data-comanda]'))
            .map(tarjeta => tarjeta.dataset.comanda + ':' + tarjeta.dataset.estado);
    }

    function marcarCambios(anteriores) {
        const previas = new Set(anteriores);
        grid.querySelectorAll('[data-comanda]').forEach(tarjeta => {
            const clave = tarjeta.dataset.comanda + ':' + tarjeta.dataset.estado;
            if (previas.has(clave)) {
                tarjeta.classList.add('kds-card--estable');
                return;
            }
            const existia = anteriores.some(valor => valor.startsWith(tarjeta.dataset.comanda + ':'));
            tarjeta.classList.add(existia ? 'kds-card--cambio' : 'kds-card--nueva');
        });
    }

    function refrescar() {
        if (sondeo) sondeo.ahora();
    }

    async function cambiarEstado(boton, idComanda, nuevoEstado) {
        boton.disabled = true;
        Restaurante.cargando(boton, true);

        try {
            const respuesta = await fetch(config.urlActualizar + '/' + encodeURIComponent(idComanda), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': config.csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ estado: nuevoEstado })
            });

            const resultado = await respuesta.json();

            if (respuesta.ok && resultado.success) {
                Restaurante.notificar(nuevoEstado === 'LISTO' ? 'Comanda lista para entregar' : 'Preparación iniciada', 'exito');
            } else {
                Restaurante.notificar('Error al actualizar el estado: ' + (resultado.message || 'Error desconocido'), 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            Restaurante.notificar('Ocurrió un error de red al comunicarse con el servidor.', 'error');
        }

        Restaurante.cargando(boton, false);
        boton.disabled = false;
        refrescar();
    }

    window.refrescar = refrescar;
    window.cambiarEstado = cambiarEstado;

    grid.addEventListener('click', evento => {
        const boton = evento.target.closest('[data-cambiar-estado]');
        if (boton && !boton.disabled) cambiarEstado(boton, boton.dataset.id, boton.dataset.cambiarEstado);
    });

    if (actualizar) actualizar.addEventListener('click', refrescar);

    sondeo = iniciarSondeo({
        url: config.urlEstado,
        intervalo: config.intervalo,
        pausar: () => Boolean(grid.querySelector('.kds-btn:disabled')),
        alRecibir: html => {
            grid.innerHTML = html;
            marcarCambios(firmaAnterior);
            firmaAnterior = firmar();
            hora.textContent = new Date().toLocaleTimeString('es-CR', { hour12: false });
            enVivo.classList.remove('kds-en-vivo--caido');
        },
        alFallar: () => {
            hora.textContent = 'sin conexión, reintentando...';
            enVivo.classList.add('kds-en-vivo--caido');
        }
    });
})();
