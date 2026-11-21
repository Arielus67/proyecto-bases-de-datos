(function () {
    const impresora = document.getElementById('impresora');
    const ticket = document.getElementById('ticket');
    const estado = document.getElementById('impresora-estado');
    const botonImprimir = document.getElementById('imprimir-ticket');
    const autoImprimir = document.body.dataset.imprimir === '1';
    const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!impresora || !ticket) return;

    if (botonImprimir) botonImprimir.addEventListener('click', () => window.print());

    function cambiarFase(fase) {
        if (impresora.dataset.fase === fase) return;
        impresora.dataset.fase = fase;
        estado.textContent = estado.dataset[fase] || estado.textContent;
        estado.style.animation = 'none';
        void estado.offsetWidth;
        estado.style.animation = '';
    }

    if (impresora.dataset.fase !== 'preparando') {
        if (autoImprimir) window.addEventListener('load', () => window.print());
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.delete('nuevo');
    window.history.replaceState(null, '', url.toString());

    let terminado = false;

    function terminar() {
        if (terminado) return;
        terminado = true;
        cambiarFase('completo');
        if (autoImprimir) setTimeout(() => window.print(), 450);
    }

    window.addEventListener('beforeprint', terminar);

    if (sinMovimiento) {
        terminar();
        return;
    }

    const duracion = Math.round(Math.min(2800, Math.max(1400, ticket.offsetHeight * 2.6)));
    ticket.style.setProperty('--duracion-impresion', duracion + 'ms');

    ticket.addEventListener('animationend', evento => {
        if (evento.target === ticket && evento.animationName === 'salida-papel') setTimeout(terminar, 120);
    });

    setTimeout(() => cambiarFase('imprimiendo'), 750);
    setTimeout(terminar, duracion + 2200);
})();
