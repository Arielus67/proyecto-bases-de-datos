<!DOCTYPE html>
<html lang="es" dir="ltr"
      data-color-theme="Blue_Theme"
      data-layout="vertical"
      data-card="shadow"
      class="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sistema Restaurante - @yield('titulo', 'Inicio')</title>

    <link rel="shortcut icon"
          href="{{ asset('src/assets/images/logos/favicon.png') }}">

    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap">

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">

    <link rel="stylesheet"
          href="{{ asset('src/assets/css/theme.css') }}">

    @stack('css')
</head>

<body class="DEFAULT_THEME bg-white">

    <main>
        <div id="main-wrapper" class="flex">

            {{-- MENÚ LATERAL --}}
            @include('layouts.menu')

            {{-- CONTENIDO PRINCIPAL --}}
            <div class="page-wrapper w-full">

                {{-- BARRA SUPERIOR --}}
                <header class="sticky top-0 z-10 bg-white border-b border-border">
                    <div class="flex items-center justify-between p-4">
                        <div>
                            <h2 class="text-lg font-semibold">
                                Sistema Restaurante
                            </h2>
                            <p class="text-sm text-gray-500">
                                Administración del restaurante
                            </p>
                        </div>

                        <span class="text-sm font-medium">
                            Panel administrativo
                        </span>
                    </div>
                </header>

                {{-- TÍTULO Y BOTONES --}}
                <div class="max-w-full pt-6">
                    <div class="container full-container py-5">

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

                        {{-- CONTENIDO DE CADA VISTA --}}
                        @yield('contenido')

                    </div>
                </div>

                {{-- FOOTER --}}
                <footer class="p-5 text-sm text-gray-500">
                    © {{ date('Y') }} Sistema Restaurante
                </footer>

            </div>
        </div>
    </main>

    <script src="{{ asset('src/assets/js/theme/app.init.js') }}"></script>
    <script src="{{ asset('src/assets/js/theme/app.min.js') }}"></script>
    <script src="{{ asset('src/assets/js/theme.js') }}"></script>

    <script src="https://cdn.jsdelivr.net/npm/preline@3.0.1/dist/preline.js"></script>

    @include('layouts.js')
    @stack('scripts')

</body>
</html>