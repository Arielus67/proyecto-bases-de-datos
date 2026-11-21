(function () {
    const contenedor = document.getElementById('permisos-rol');
    if (!contenedor) return;

    const todos = contenedor.querySelector('[data-marcar-todo]');
    const modulos = Array.from(contenedor.querySelectorAll('[data-modulo]'));
    const casillas = modulo => Array.from(modulo.querySelectorAll('input[name="permisos[]"]'));

    function reflejar(casilla, lista) {
        const marcadas = lista.filter(c => c.checked).length;
        casilla.checked = marcadas === lista.length && lista.length > 0;
        casilla.indeterminate = marcadas > 0 && marcadas < lista.length;
    }

    function actualizar() {
        modulos.forEach(modulo => reflejar(modulo.querySelector('[data-marcar-modulo]'), casillas(modulo)));
        reflejar(todos, Array.from(contenedor.querySelectorAll('input[name="permisos[]"]')));
    }

    modulos.forEach(modulo => {
        modulo.querySelector('[data-marcar-modulo]').addEventListener('change', evento => {
            casillas(modulo).forEach(c => { c.checked = evento.target.checked; });
            actualizar();
        });
    });

    todos.addEventListener('change', () => {
        contenedor.querySelectorAll('input[name="permisos[]"]').forEach(c => { c.checked = todos.checked; });
        actualizar();
    });

    contenedor.addEventListener('change', evento => {
        if (evento.target.name === 'permisos[]') actualizar();
    });

    actualizar();
})();
