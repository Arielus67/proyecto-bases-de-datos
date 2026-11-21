(function () {
    const LLAVE_CORREO = 'acceso-correo';

    try {
        sessionStorage.removeItem('menu-presentado');
        sessionStorage.removeItem('bienvenida-vista');
    } catch (error) {
    }

    const formulario = document.getElementById('formulario-acceso');
    const boton = document.getElementById('boton-acceso');
    const ver = document.getElementById('ver-contrasena');
    const correo = document.getElementById('correo');
    const contrasena = document.getElementById('contrasena');
    const recordarme = document.getElementById('recordarme');

    // ---------- Mostrar / ocultar contraseña ----------
    if (ver && contrasena) {
        ver.addEventListener('click', () => {
            const visible = contrasena.type === 'text';
            contrasena.type = visible ? 'password' : 'text';
            ver.setAttribute('aria-pressed', visible ? 'false' : 'true');
            ver.setAttribute('aria-label', visible ? 'Mostrar contraseña' : 'Ocultar contraseña');
            ver.innerHTML = visible ? '<i class="ti ti-eye" aria-hidden="true"></i>' : '<i class="ti ti-eye-off" aria-hidden="true"></i>';
            contrasena.focus();
        });
    }

    // ---------- Recordarme: guarda solo el correo en este dispositivo ----------
    if (correo && recordarme) {
        let guardado = null;
        try {
            guardado = localStorage.getItem(LLAVE_CORREO);
        } catch (error) {
        }

        if (guardado) {
            recordarme.checked = true;
            if (!correo.value) {
                correo.value = guardado;
                if (contrasena) contrasena.focus();
            }
        }
    }

    // ---------- Aviso: las contraseñas las restablece el administrador ----------
    const aviso = document.getElementById('aviso-acceso');
    const avisoTexto = document.getElementById('aviso-texto');
    const avisoCerrar = document.getElementById('aviso-cerrar');
    const mensajes = {
        olvido: 'Por seguridad, las contraseñas solo las restablece el administrador del restaurante. Pídele que te asigne una nueva.',
    };

    document.querySelectorAll('[data-aviso]').forEach(disparador => {
        disparador.addEventListener('click', () => {
            if (!aviso || !avisoTexto) return;
            avisoTexto.textContent = mensajes[disparador.dataset.aviso] || '';
            aviso.hidden = false;
        });
    });

    if (avisoCerrar && aviso) {
        avisoCerrar.addEventListener('click', () => { aviso.hidden = true; });
    }

    if (!formulario) return;

    // ---------- Envío ----------
    formulario.addEventListener('submit', evento => {
        const invalido = Array.from(formulario.elements).find(campo => campo.willValidate && !campo.checkValidity());

        if (invalido) {
            evento.preventDefault();
            const contenedor = invalido.closest('.dato');
            if (contenedor) {
                contenedor.classList.remove('dato--error');
                void contenedor.offsetWidth;
                contenedor.classList.add('dato--error');
            }
            invalido.focus();
            return;
        }

        try {
            if (recordarme && recordarme.checked) {
                localStorage.setItem(LLAVE_CORREO, correo.value.trim());
            } else {
                localStorage.removeItem(LLAVE_CORREO);
            }
        } catch (error) {
        }

        Restaurante.cargando(boton, true);
        boton.disabled = true;
    });

    formulario.addEventListener('input', evento => {
        const contenedor = evento.target.closest('.dato');
        if (contenedor) contenedor.classList.remove('dato--error');
    });

    window.addEventListener('pageshow', () => {
        Restaurante.cargando(boton, false);
        boton.disabled = false;
    });
})();
