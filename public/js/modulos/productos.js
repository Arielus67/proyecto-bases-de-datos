(function () {
    const entrada = document.getElementById('imagen');
    const previa = document.getElementById('imagen-previa');
    const vista = document.getElementById('imagen-vista');
    const nombre = document.getElementById('imagen-nombre');
    const quitar = document.getElementById('quitar-imagen');

    if (!entrada || !previa) return;

    const marcador = vista.querySelector('.campo-imagen__marcador');
    const original = previa.getAttribute('src');
    const tiposValidos = ['image/jpeg', 'image/png', 'image/webp'];
    const pesoMaximo = 2 * 1024 * 1024;
    let urlTemporal = null;

    function mostrar(url) {
        previa.hidden = !url;
        marcador.hidden = Boolean(url);
        vista.classList.toggle('campo-imagen__vista--vacia', !url);
        if (url) previa.src = url;
    }

    entrada.addEventListener('change', () => {
        const archivo = entrada.files[0];
        if (urlTemporal) URL.revokeObjectURL(urlTemporal);
        urlTemporal = null;

        if (!archivo) {
            nombre.hidden = true;
            mostrar(original);
            return;
        }

        if (!tiposValidos.includes(archivo.type)) {
            Restaurante.notificar('La imagen debe ser JPG, PNG o WEBP.', 'error');
            entrada.value = '';
            return;
        }

        if (archivo.size > pesoMaximo) {
            Restaurante.notificar('La imagen no puede pesar más de 2 MB.', 'error');
            entrada.value = '';
            return;
        }

        urlTemporal = URL.createObjectURL(archivo);
        mostrar(urlTemporal);
        nombre.textContent = archivo.name;
        nombre.hidden = false;
        if (quitar) quitar.checked = false;
    });

    if (quitar) {
        quitar.addEventListener('change', () => {
            vista.classList.toggle('campo-imagen__vista--quitar', quitar.checked);
        });
    }
})();
