(function () {
    const puestos = Restaurante.datos('datos-empleado').puestos || {};
    const select = document.getElementById('id_rol');
    const bloques = {
        mesero: document.getElementById('bloque-mesero'),
        cocinero: document.getElementById('bloque-cocinero'),
        cajero: document.getElementById('bloque-cajero')
    };

    if (!select) return;

    function actualizar() {
        const puesto = puestos[select.value] || '';

        Object.keys(bloques).forEach(clave => {
            if (bloques[clave]) bloques[clave].classList.toggle('hidden', puesto !== clave);
        });
    }

    select.addEventListener('change', actualizar);
    actualizar();
})();
