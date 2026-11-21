(function () {
    const contenedor = document.getElementById('lineas');
    const agregar = document.getElementById('agregar-linea');

    if (!contenedor || !agregar) return;

    agregar.addEventListener('click', () => {
        const nueva = contenedor.querySelector('.linea').cloneNode(true);
        nueva.querySelector('select').value = '';
        nueva.querySelector('input').value = '';
        nueva.classList.add('entrada');
        contenedor.appendChild(nueva);
        nueva.querySelector('select').focus();
    });

    contenedor.addEventListener('click', evento => {
        const boton = evento.target.closest('.quitar');

        if (boton && contenedor.querySelectorAll('.linea').length > 1) {
            boton.closest('.linea').remove();
        }
    });
})();
