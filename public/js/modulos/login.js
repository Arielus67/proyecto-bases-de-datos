(function () {
    const cuerpo = document.body;
    const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const intro = document.getElementById('intro');
    const finIntro = 3500;

    try {
        sessionStorage.removeItem('menu-presentado');
        sessionStorage.removeItem('bienvenida-vista');
    } catch (error) {
    }

    function liberarPagina() {
        cuerpo.classList.remove('acceso--intro');
    }

    function crearCintas(contenedor) {
        const tonos = ['--cinta-1', '--cinta-1', '--cinta-2', '--cinta-2', '--cinta-3', '--cinta-4'];
        const fragmento = document.createDocumentFragment();

        for (let i = 0; i < 110; i++) {
            const cinta = document.createElement('span');
            const hueco = Math.random() < 0.38;
            cinta.className = 'cinta';
            cinta.style.setProperty('--ancho-cinta', (hueco ? 0.6 + Math.random() * 2.4 : 0.15 + Math.random() * 1.1).toFixed(2));
            cinta.style.setProperty('--color-cinta', hueco ? 'transparent' : 'var(' + tonos[Math.floor(Math.random() * tonos.length)] + ')');
            cinta.style.setProperty('--brillo-cinta', (0.35 + Math.random() * 0.65).toFixed(2));
            cinta.style.setProperty('--origen-cinta', Math.round(Math.random() * 100) + '%');
            cinta.style.setProperty('--retraso-cinta', (2780 + Math.random() * 240).toFixed(0) + 'ms');
            fragmento.appendChild(cinta);
        }

        contenedor.appendChild(fragmento);
    }

    if (intro && !sinMovimiento) {
        const cintas = intro.querySelector('.cintas');
        if (cintas) crearCintas(cintas);

        const quitarIntro = () => intro.remove();
        let saltada = false;

        const saltarIntro = () => {
            if (saltada) return;
            saltada = true;
            intro.classList.add('intro--saltar');
            liberarPagina();
            setTimeout(quitarIntro, 320);
        };

        intro.addEventListener('animationend', evento => {
            if (evento.target === intro && evento.animationName === 'intro-salir') quitarIntro();
        });

        intro.addEventListener('click', saltarIntro);
        document.addEventListener('keydown', saltarIntro, { once: true });
        setTimeout(liberarPagina, finIntro);
        setTimeout(quitarIntro, finIntro + 900);
    } else {
        if (intro) intro.remove();
        liberarPagina();
    }

    const formulario = document.getElementById('formulario-acceso');
    const boton = document.getElementById('boton-acceso');
    const ver = document.getElementById('ver-contrasena');
    const contrasena = document.getElementById('contrasena');

    if (ver && contrasena) {
        ver.addEventListener('click', () => {
            const visible = contrasena.type === 'text';
            contrasena.type = visible ? 'password' : 'text';
            ver.setAttribute('aria-pressed', visible ? 'false' : 'true');
            ver.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
            ver.innerHTML = visible ? '<i class="ti ti-eye"></i>' : '<i class="ti ti-eye-off"></i>';
            contrasena.focus();
        });
    }

    if (!formulario) return;

    formulario.addEventListener('submit', evento => {
        const invalido = Array.from(formulario.elements).find(campo => campo.willValidate && !campo.checkValidity());

        if (invalido) {
            evento.preventDefault();
            const contenedor = invalido.closest('.campo-acceso');
            contenedor.classList.remove('campo-acceso--error');
            void contenedor.offsetWidth;
            contenedor.classList.add('campo-acceso--error');
            invalido.focus();
            return;
        }

        Restaurante.cargando(boton, true);
        boton.disabled = true;
    });

    formulario.addEventListener('input', evento => {
        const contenedor = evento.target.closest('.campo-acceso');
        if (contenedor) contenedor.classList.remove('campo-acceso--error');
    });

    window.addEventListener('pageshow', () => {
        Restaurante.cargando(boton, false);
        boton.disabled = false;
    });
})();
