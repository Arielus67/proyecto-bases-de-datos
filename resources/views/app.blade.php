<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Hello - @yield('titulo', 'Título general')</title>
    @include('layouts.css')
    @stack('css')
</head>

<body>

    @include('layouts.menu')

    <!-- INICIO CONTENIDO -->
    <main>

        <div class="container-fluid bg-secondary text-white">
            <div class="container py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="display-6 mb-0">@yield('titulo', 'Título general')</h1>
                    <div>
                        @yield('botones')
                    </div>
                </div>
            </div>
        </div>

        <div class="container py-4">

            @yield('contenido')

        </div>

    </main>
    <!-- FIN CONTENIDO -->

    <!-- INICIO FOOTER -->
    <footer class="container-fluid bg-dark text-white py-3 mt-auto">
        <div class="container">
            <p class="mb-0">Reservados todos los derechos &copy; {{ date('Y') }}</p>
        </div>
    </footer>
    <!-- FIN FOOTER -->

    @include('layouts.js')
    @stack('scripts')

</body>

</html>