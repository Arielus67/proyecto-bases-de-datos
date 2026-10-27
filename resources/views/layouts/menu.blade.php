
<aside id="sidebar-menu"
    class="w-[270px] min-h-screen bg-gray-900 text-white p-5">

    {{-- ENCABEZADO --}}
    <div class="mb-10 pb-6 border-b border-gray-700">
        <div class="flex items-center gap-3">
            <div class="w-11 h-11 rounded-xl bg-gray-700 flex items-center justify-center">
                <i class="ti ti-tools-kitchen-2 text-2xl text-white"></i>
            </div>

            <div>
                <h2 class="text-lg font-bold">Restaurante</h2>
                <p class="text-xs text-gray-400">Panel de comandas</p>
            </div>
        </div>
    </div>

    {{-- SECCIÓN: COMANDAS --}}
    <div class="mb-8">

        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3 px-3">
            Gestión de pedidos
        </p>

        <nav class="space-y-2">

            <a href="{{ url('/mesero') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-lg
                       bg-gray-700 text-white hover:bg-gray-600 transition">
                <i class="ti ti-receipt text-xl"></i>
                <span class="font-medium">Crear comanda</span>
            </a>

        </nav>
    </div>

    {{-- SECCIÓN: MENÚ --}}
    <div class="mb-8">

        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 mb-3 px-3">
            Restaurante
        </p>

        <nav class="space-y-2">

            <a href="{{ url('/mesero#menu-restaurante') }}"
                class="flex items-center gap-3 px-4 py-3 rounded-lg
                       text-gray-300 hover:bg-gray-700 hover:text-white transition">
                <i class="ti ti-book-2 text-xl"></i>
                <span class="font-medium">Ver menú</span>
            </a>

        </nav>
    </div>

</aside>