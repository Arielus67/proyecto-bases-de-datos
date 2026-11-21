@php
    $hora = (int) now()->format('G');
    $servicio = $hora < 11 ? 'Desayunos' : ($hora < 16 ? 'Almuerzos' : 'Cenas');
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    @section('titulo', 'Iniciar sesión')
    @include('layouts.partials.head')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&display=swap">
    <link rel="stylesheet" href="{{ asset('css/modulos/login.css') }}">
</head>

<body class="acceso">
    <main class="acceso__lienzo">
        <div class="acceso__tarjeta">
            <section class="acceso__panel" aria-labelledby="titulo-acceso">
                <header class="marca" aria-label="{{ config('app.name') }}">
                    <img class="marca__sello" src="{{ asset('img/marca/emblema.svg') }}" alt="" width="54" height="54">
                    <span class="marca__texto" aria-hidden="true">
                        <span class="marca__previo">La Cuchara de</span>
                        <strong>Luisón</strong>
                        <small>Restaurante</small>
                    </span>
                </header>

                <div class="acceso__contenido">
                    <h1 class="acceso__titulo" id="titulo-acceso">¡Bienvenido de nuevo!</h1>
                    <p class="acceso__subtitulo">Inicia sesión para disfrutar de una experiencia gastronómica única.</p>

                    @if (session('error'))
                        <div class="mensaje mensaje--error" role="alert"><i class="ti ti-alert-circle"></i><span>{{ session('error') }}</span></div>
                    @endif

                    @if ($errors->any())
                        <div class="mensaje mensaje--error" role="alert"><i class="ti ti-alert-circle"></i><span>{{ $errors->first() }}</span></div>
                    @endif

                    <form method="POST" action="{{ route('login.store') }}" class="ingreso" id="formulario-acceso" novalidate>
                        @csrf

                        <div class="dato">
                            <label for="correo">Correo electrónico</label>
                            <div class="dato__control">
                                <i class="ti ti-mail" aria-hidden="true"></i>
                                <input id="correo" name="correo" type="email" value="{{ old('correo') }}" required autofocus
                                    autocomplete="username" maxlength="100" placeholder="nombre@restaurante.cr">
                            </div>
                        </div>

                        <div class="dato">
                            <label for="contrasena">Contraseña</label>
                            <div class="dato__control">
                                <i class="ti ti-lock" aria-hidden="true"></i>
                                <input id="contrasena" name="contrasena" type="password" required autocomplete="current-password"
                                    maxlength="100" placeholder="Tu contraseña">
                                <button type="button" class="dato__ver" id="ver-contrasena" aria-label="Mostrar contraseña" aria-pressed="false">
                                    <i class="ti ti-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>

                        <div class="ingreso__fila">
                            <label class="recordar" title="Guarda tu correo en este dispositivo">
                                <input type="checkbox" id="recordarme">
                                <span class="recordar__marca" aria-hidden="true"><i class="ti ti-check"></i></span>
                                Recordarme
                            </label>
                            <button type="button" class="enlace" data-aviso="olvido">¿Olvidaste tu contraseña?</button>
                        </div>

                        <button type="submit" class="boton boton--primario" id="boton-acceso">
                            <span>Iniciar sesión</span>
                            <i class="ti ti-arrow-right" aria-hidden="true"></i>
                        </button>
                    </form>

                    <div class="aviso" id="aviso-acceso" role="status" aria-live="polite" hidden>
                        <i class="ti ti-info-circle" aria-hidden="true"></i>
                        <p id="aviso-texto"></p>
                        <button type="button" class="aviso__cerrar" id="aviso-cerrar" aria-label="Cerrar aviso"><i class="ti ti-x"></i></button>
                    </div>
                </div>

                <p class="acceso__legal">
                    Al iniciar sesión aceptas los <strong>Términos de uso</strong> y la <strong>Política de privacidad</strong> de {{ config('app.name') }}.
                    Por seguridad, tras 5 intentos fallidos el acceso se bloquea un minuto.
                </p>
            </section>

            <aside class="acceso__foto" aria-label="Platillo de la casa">
                <img src="{{ asset('img/acceso/platillo.jpg') }}" alt="Pasta artesanal con pesto, tomates cherry y hojas verdes frescas en un plato de cerámica blanca" width="1400" height="934">
                <span class="foto__estado"><span class="foto__punto"></span> Abierto · {{ $servicio }}</span>
                <div class="foto__leyenda">
                    <span class="foto__antetitulo">Hecho en casa</span>
                    <p class="foto__frase">Ingredientes frescos, recetas con calma y cada plato servido <em>en su punto.</em></p>
                </div>
            </aside>
        </div>
    </main>

    <script src="{{ asset('js/sistema/app.js') }}"></script>
    <script src="{{ asset('js/modulos/login.js') }}"></script>
</body>

</html>
