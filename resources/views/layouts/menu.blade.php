<aside style="width:290px;flex-shrink:0;background:#0b1020;color:#fff;min-height:100vh;padding:24px 20px;">
    <div class="flex items-center gap-2 mb-1">
        <i class="ti ti-tools-kitchen-2 text-2xl"></i>
        <span class="text-xl font-bold">Restaurante</span>
    </div>
    <p class="text-xs text-gray-300 mb-4">Panel administrativo</p>

    <nav class="flex flex-col gap-1 text-sm font-medium">
        <a href="{{ url('/mesero') }}" class="flex items-center gap-3 py-2 text-white hover:text-blue-300">
            <i class="ti ti-receipt-2"></i> Gestión de comandas
        </a>
        <a href="{{ url('/productos') }}" class="flex items-center gap-3 py-2 text-white hover:text-blue-300">
            <i class="ti ti-soup"></i> Productos
        </a>
    </nav>
</aside>
