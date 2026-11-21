window.iniciarSondeo = function (opciones) {
    const intervalo = opciones.intervalo || 5000;
    const tiempoMaximo = opciones.tiempoMaximo || 4500;
    const debePausar = opciones.pausar || (() => false);

    let temporizador = null;
    let controlador = null;
    let enCurso = false;
    let detenido = false;
    let fallos = 0;

    async function consultar() {
        if (detenido || enCurso || document.hidden || debePausar()) {
            programar();
            return;
        }

        enCurso = true;
        controlador = new AbortController();
        const corte = setTimeout(() => controlador.abort(), tiempoMaximo);

        try {
            const respuesta = await fetch(opciones.url, {
                cache: 'no-store',
                credentials: 'same-origin',
                signal: controlador.signal,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                }
            });

            if (respuesta.status === 401 || respuesta.status === 419 || respuesta.redirected) {
                detener();
                window.location.reload();
                return;
            }

            if (!respuesta.ok) {
                throw new Error('HTTP ' + respuesta.status);
            }

            const html = await respuesta.text();

            if (!detenido && !debePausar()) {
                opciones.alRecibir(html);
            }

            if (fallos > 0 && opciones.alRecuperar) {
                opciones.alRecuperar();
            }

            fallos = 0;
        } catch (error) {
            fallos++;

            if (opciones.alFallar) {
                opciones.alFallar(error, fallos);
            }
        } finally {
            clearTimeout(corte);
            controlador = null;
            enCurso = false;
            programar();
        }
    }

    function programar() {
        if (detenido) return;
        clearTimeout(temporizador);
        temporizador = setTimeout(consultar, intervalo);
    }

    function detener() {
        detenido = true;
        clearTimeout(temporizador);
        if (controlador) controlador.abort();
    }

    function ahora() {
        if (detenido) return;
        clearTimeout(temporizador);
        consultar();
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) ahora();
    });

    window.addEventListener('pagehide', detener);

    programar();

    return { ahora, detener };
};
