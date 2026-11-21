@php
    $conIntro = ! $errors->any() && ! session('error') && ! old('correo');
    $hora = (int) now()->format('G');
    $servicio = $hora < 11 ? 'Desayunos' : ($hora < 16 ? 'Almuerzos' : 'Cenas');
    $platos = [
        ['archivo' => 'casado-con-pollo', 'nombre' => 'Casado con pollo', 'detalle' => 'Plato fuerte'],
        ['archivo' => 'gallo-pinto-con-huevo', 'nombre' => 'Gallo pinto', 'detalle' => 'Desayuno típico'],
        ['archivo' => 'cafe-chorreado', 'nombre' => 'Café chorreado', 'detalle' => 'Bebida caliente'],
    ];
    $areas = [
        ['icono' => 'ti-armchair', 'nombre' => 'Salón', 'detalle' => 'Mesas y comandas'],
        ['icono' => 'ti-chef-hat', 'nombre' => 'Cocina', 'detalle' => 'Pantalla KDS'],
        ['icono' => 'ti-cash', 'nombre' => 'Caja', 'detalle' => 'Cobro y tickets'],
        ['icono' => 'ti-chart-pie', 'nombre' => 'Administración', 'detalle' => 'Carta, inventario y ventas'],
    ];
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    @section('titulo', 'Iniciar sesión')
    @include('layouts.partials.head')
    <link rel="stylesheet" href="{{ asset('css/modulos/login.css') }}">
</head>

<body class="acceso {{ $conIntro ? 'acceso--intro' : '' }}">
    @if ($conIntro)
        <div class="intro" id="intro">
            <div class="intro__marca" aria-hidden="true">
                <span class="intro__logo"><i class="ti ti-tools-kitchen-2"></i></span>
                <span class="intro__nombre">{{ mb_strtoupper(config('app.name')) }}</span>
                <span class="intro__carga"></span>
            </div>

            <div class="intro__escena" aria-hidden="true">
                <svg class="monograma" viewBox="0 0 100 140">
                    <defs>
                        <linearGradient id="monograma-poste" x1="0" y1="0" x2="1" y2="0">
                            <stop offset="0" stop-color="#d9d9d9"/>
                            <stop offset="0.5" stop-color="#ffffff"/>
                            <stop offset="1" stop-color="#cfcfcf"/>
                        </linearGradient>
                        <linearGradient id="monograma-pierna" x1="0" y1="0" x2="0.35" y2="1">
                            <stop offset="0" stop-color="#5c5c5c"/>
                            <stop offset="0.38" stop-color="#e6e6e6"/>
                            <stop offset="1" stop-color="#ffffff"/>
                        </linearGradient>
                        <linearGradient id="monograma-arco" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#ffffff"/>
                            <stop offset="1" stop-color="#bdbdbd"/>
                        </linearGradient>
                    </defs>
                    <polygon class="monograma__pierna" points="38,66 64,66 98,130 72,130" fill="url(#monograma-pierna)"/>
                    <path class="monograma__arco" d="M24 21 H56 A25 25 0 0 1 56 71 H24" fill="none" stroke="url(#monograma-arco)" stroke-width="22" pathLength="100"/>
                    <rect class="monograma__poste" x="8" y="10" width="26" height="120" fill="url(#monograma-poste)"/>
                </svg>

                <div class="cintas"></div>
            </div>

            <button type="button" class="intro__saltar" id="intro-saltar">Saltar intro <i class="ti ti-player-skip-forward"></i></button>
        </div>
    @endif

    <main class="acceso__contenedor">
        <section class="acceso__marca" aria-label="{{ config('app.name') }}">
            <header class="casa">
                <span class="casa__logo"><i class="ti ti-tools-kitchen-2"></i></span>
                <span class="casa__texto">
                    <strong>{{ config('app.name') }}</strong>
                    <small>Sistema de gestión del servicio</small>
                </span>
                <span class="casa__estado"><span class="casa__punto"></span> Abierto · {{ $servicio }}</span>
            </header>

            <div class="portada">
                <p class="portada__antetitulo">Del salón a la cocina y a la caja</p>
                <h2 class="portada__titulo">Cada mesa, cada comanda y cada cobro, <em>en su punto.</em></h2>
                <p class="portada__texto">El equipo trabaja sobre la misma información: los pedidos llegan a cocina al instante, la caja cobra sin errores y el inventario se descuenta solo.</p>
            </div>

            <div class="carta" aria-hidden="true">
                @foreach ($platos as $plato)
                    <figure class="carta__plato">
                        <img src="{{ asset('img/productos/predeterminadas/' . $plato['archivo'] . '.svg') }}" alt="" width="480" height="320">
                        <figcaption>
                            <strong>{{ $plato['nombre'] }}</strong>
                            <span>{{ $plato['detalle'] }}</span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>

            <ul class="areas">
                @foreach ($areas as $area)
                    <li class="areas__item">
                        <span class="areas__icono"><i class="ti {{ $area['icono'] }}"></i></span>
                        <span class="areas__texto">
                            <strong>{{ $area['nombre'] }}</strong>
                            <small>{{ $area['detalle'] }}</small>
                        </span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="acceso__panel">
            <div class="acceso__tarjeta">
                <div class="acceso__logo-movil">
                    <span class="casa__logo"><i class="ti ti-tools-kitchen-2"></i></span>
                    <span class="casa__texto"><strong>{{ config('app.name') }}</strong><small>Sistema de gestión del servicio</small></span>
                </div>

                <p class="acceso__antetitulo">{{ ucfirst(now()->locale('es')->translatedFormat('l j \d\e F')) }}</p>
                <h1 class="acceso__titulo">Inicia tu turno</h1>
                <p class="acceso__subtitulo">Entra con tu correo y contraseña de empleado. El sistema te lleva directo a tu área de trabajo.</p>

                @if (session('error'))
                    <div class="alerta alerta--error" role="alert"><i class="ti ti-alert-circle"></i><div>{{ session('error') }}</div></div>
                @endif

                @if ($errors->any())
                    <div class="alerta alerta--error" role="alert"><i class="ti ti-alert-circle"></i><div>{{ $errors->first() }}</div></div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="acceso__formulario" id="formulario-acceso" novalidate>
                    @csrf

                    <div class="campo-acceso">
                        <label for="correo">Correo de empleado</label>
                        <div class="campo-acceso__control">
                            <i class="ti ti-mail"></i>
                            <input id="correo" name="correo" type="email" value="{{ old('correo') }}" required autofocus
                                autocomplete="username" maxlength="100" placeholder="nombre@restaurante.cr">
                        </div>
                    </div>

                    <div class="campo-acceso">
                        <label for="contrasena">Contraseña</label>
                        <div class="campo-acceso__control">
                            <i class="ti ti-lock"></i>
                            <input id="contrasena" name="contrasena" type="password" required autocomplete="current-password"
                                maxlength="100" placeholder="••••••••">
                            <button type="button" class="campo-acceso__ver" id="ver-contrasena" aria-label="Mostrar contraseña" aria-pressed="false">
                                <i class="ti ti-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn--primario btn--grande btn--bloque" id="boton-acceso">
                        <span>Entrar al sistema</span>
                        <i class="ti ti-arrow-right"></i>
                    </button>
                </form>

                <div class="destinos">
                    <p class="destinos__titulo">Según tu rol entrarás a</p>
                    <ul class="destinos__lista">
                        <li><i class="ti ti-armchair"></i> Mesero <span>Comandas</span></li>
                        <li><i class="ti ti-chef-hat"></i> Cocinero <span>Cocina</span></li>
                        <li><i class="ti ti-cash"></i> Cajero <span>Caja</span></li>
                        <li><i class="ti ti-chart-pie"></i> Administrador <span>Panel</span></li>
                    </ul>
                </div>

                <p class="acceso__ayuda"><i class="ti ti-shield-lock"></i> Acceso solo para personal. Tras 5 intentos fallidos el acceso se bloquea por un minuto. Si olvidaste tu contraseña, pídele al administrador que la restablezca.</p>
            </div>
        </section>
    </main>

    <script src="{{ asset('js/sistema/app.js') }}"></script>
    <script src="{{ asset('js/modulos/login.js') }}"></script>
</body>

</html>
