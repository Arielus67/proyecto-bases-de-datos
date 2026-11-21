@php
    $bienvenida = session()->pull('bienvenida', false);
    $hora = (int) now()->format('G');
    $saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    @include('layouts.partials.head')
    <link rel="stylesheet" href="{{ asset('css/sistema/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('css/sistema/entrada.css') }}">
    @stack('css')
</head>

<body class="{{ $bienvenida ? 'con-bienvenida' : '' }}">
    @if ($bienvenida)
        <div class="bienvenida" id="bienvenida">
            <div class="bienvenida__marca" aria-hidden="true">
                <span class="bienvenida__logo"><i class="ti ti-tools-kitchen-2"></i></span>
                <span class="bienvenida__nombre">RESTAURANTE</span>
                <span class="bienvenida__carga"></span>
            </div>
            <p class="bienvenida__saludo">{{ $saludo }}, {{ auth()->user()->nombre }}</p>
        </div>
    @endif

    <div class="app @yield('clase-app') {{ request()->cookie('menu_contraido') === '1' ? 'app--contraido' : '' }}" id="app">
        @include('layouts.menu')

        <div class="velo" data-cerrar-menu></div>

        <div class="principal">
            <header class="barra">
                <button type="button" class="boton-icono barra__menu" data-abrir-menu aria-label="Abrir menú">
                    <i class="ti ti-menu-2"></i>
                </button>

                <div class="barra__contexto">
                    <p class="barra__ruta">@yield('seccion', 'Sistema Restaurante')</p>
                    <p class="barra__lugar">@yield('titulo', 'Inicio')</p>
                </div>

                @include('layouts.sesion')
            </header>

            <main class="pagina {{ $bienvenida ? '' : 'pagina--revelar' }}">
                <div class="pagina__cabecera">
                    <h1 class="pagina__titulo">@yield('titulo', 'Inicio')</h1>

                    <div class="pagina__acciones">
                        @yield('botones')
                    </div>
                </div>

                <x-admin.flash />

                @yield('contenido')
            </main>

            <footer class="pie">© {{ date('Y') }} Sistema Restaurante</footer>
        </div>
    </div>

    <div class="notificaciones" id="notificaciones" aria-live="polite"></div>

    <script src="{{ asset('js/sistema/app.js') }}"></script>
    <script src="{{ asset('js/sistema/sondeo.js') }}"></script>
    @stack('scripts')
</body>

</html>
