<!DOCTYPE html>
<html lang="es" dir="ltr" data-color-theme="Blue_Theme" data-layout="vertical" data-card="shadow" class="light">

{{-- ============================================================
     CABECERA (HEAD): metadatos, fuentes, iconos y estilos
     ============================================================ --}}
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sistema Restaurante - @yield('titulo', 'Inicio')</title>

    <link rel="shortcut icon" href="{{ asset('src/assets/images/logos/favicon.png') }}">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
    <link rel="stylesheet" href="{{ asset('src/assets/css/theme.css') }}">

    {{-- Estilos adicionales por vista --}}
    @stack('css')
</head>

<body class="DEFAULT_THEME bg-white min-h-screen">

    <main class="min-h-screen">
        <div id="main-wrapper" class="flex min-h-screen w-full">

            {{-- ============================================================
                 MENÚ LATERAL (SIDEBAR)
                 ============================================================ --}}
            @include('layouts.menuAdmin')

            {{-- ============================================================
                 CONTENIDO PRINCIPAL
                 ============================================================ --}}
            <div class="page-wrapper flex-1 min-w-0 flex flex-col justify-between" style="margin-left: 0 !important; padding-left: 0 !important;">

                <div>
                    {{-- ------------------------------------------------
                         BARRA SUPERIOR (HEADER)
                         ------------------------------------------------ --}}
                    <header class="sticky top-0 z-10 bg-white">
                        <div class="flex items-center justify-between p-4 px-6">
                            <div>
                                <h2 class="text-lg font-semibold">Sistema Restaurante</h2>
                                <p class="text-sm text-gray-500">Administración del restaurante</p>
                            </div>
                            <span class="text-sm font-medium">Panel administrativo</span>
                        </div>
                    </header>

                    {{-- ------------------------------------------------
                         CONTENEDOR GENERAL
                         ------------------------------------------------ --}}
                    <div class="w-full pt-6 px-6">
                        <div class="w-full">

                            {{-- Título de la página + botones de acción --}}
                            <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
                                <div>
                                    <h1 class="text-2xl font-semibold">
                                        @yield('titulo', 'Título general')
                                    </h1>
                                </div>

                                <div>
                                    @yield('botones')
                                </div>
                            </div>

                            {{-- Mensajes flash (éxito / error / validación) --}}
                            <x-admin.flash />

                            {{-- Contenido de cada vista --}}
                            <div class="w-full max-w-none">
                                @yield('contenido')
                            </div>

                        </div>
                    </div>
                </div>

                {{-- ------------------------------------------------
                     PIE DE PÁGINA (FOOTER)
                     ------------------------------------------------ --}}
                <footer class="p-5 px-6 text-sm text-gray-500 border-t border-gray-100">
                    © {{ date('Y') }} Sistema Restaurante
                </footer>

            </div>
        </div>
    </main>

    {{-- ============================================================
         SCRIPTS DE LA PLANTILLA
         ============================================================ --}}
    <script src="{{ asset('src/assets/js/theme/app.init.js') }}"></script>
    <script src="{{ asset('src/assets/js/theme/app.min.js') }}"></script>
    <script src="{{ asset('src/assets/js/theme.js') }}"></script>

    <script src="https://cdn.jsdelivr.net/npm/preline@3.0.1/dist/preline.js"></script>

    {{-- Scripts globales (confirmación de borrado, etc.) --}}
    @include('layouts.js')

    {{-- Scripts adicionales por vista --}}
    @stack('scripts')

</body>

</html>
