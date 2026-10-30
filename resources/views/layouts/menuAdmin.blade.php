{{-- ============================================================
     MENÚ LATERAL (SIDEBAR)
     Cada grupo corresponde a un bloque de módulos de la base de datos.
     El patrón de ruta ('admin.roles.*') marca el ítem activo.
     ============================================================ --}}
<aside class="w-64 shrink-0 min-h-screen bg-white border-r border-gray-100 flex flex-col">

    {{-- ---------------- LOGO / NOMBRE ---------------- --}}
    <div class="px-6 py-5 border-b border-gray-100">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 text-lg font-bold">
            <i class="ti ti-tools-kitchen-2 text-2xl text-primary"></i>
            <span>Restaurante</span>
        </a>
    </div>

    <nav class="flex-1 overflow-y-auto px-4 py-4 space-y-5 text-sm">

        {{-- ---------------- PRINCIPAL ---------------- --}}
        <div>
            <p class="px-3 mb-2 text-xs font-semibold uppercase text-gray-400">Principal</p>
            <x-admin.menu-item :href="route('admin.dashboard')" icon="ti-layout-dashboard" label="Dashboard" :active="request()->routeIs('admin.dashboard')" />
        </div>

        {{-- ---------------- PERSONAL (ROL, EMPLEADO) ---------------- --}}
        <div>
            <p class="px-3 mb-2 text-xs font-semibold uppercase text-gray-400">Personal</p>
            <x-admin.menu-item :href="route('admin.roles.index')" icon="ti-shield" label="Roles" :active="request()->routeIs('admin.roles.*')" />
            <x-admin.menu-item :href="route('admin.empleados.index')" icon="ti-users" label="Empleados" :active="request()->routeIs('admin.empleados.*')" />
        </div>

        {{-- ---------------- CARTA (CATEGORIA, ESTACION, PRODUCTO, INSUMO, RECETA) ---------------- --}}
        <div>
            <p class="px-3 mb-2 text-xs font-semibold uppercase text-gray-400">Carta e inventario</p>
            <x-admin.menu-item :href="route('admin.categorias.index')" icon="ti-category" label="Categorías" :active="request()->routeIs('admin.categorias.*')" />
            <x-admin.menu-item :href="route('admin.estaciones.index')" icon="ti-flame" label="Estaciones" :active="request()->routeIs('admin.estaciones.*')" />
            <x-admin.menu-item :href="route('admin.productos.index')" icon="ti-soup" label="Productos" :active="request()->routeIs('admin.productos.*')" />
            <x-admin.menu-item :href="route('admin.insumos.index')" icon="ti-packages" label="Insumos" :active="request()->routeIs('admin.insumos.*')" />
            <x-admin.menu-item :href="route('admin.recetas.index')" icon="ti-notebook" label="Recetas" :active="request()->routeIs('admin.recetas.*')" />
        </div>

        {{-- ---------------- SALÓN (MESA, CLIENTE) ---------------- --}}
        <div>
            <p class="px-3 mb-2 text-xs font-semibold uppercase text-gray-400">Salón</p>
            <x-admin.menu-item :href="route('admin.mesas.index')" icon="ti-armchair" label="Mesas" :active="request()->routeIs('admin.mesas.*')" />
            <x-admin.menu-item :href="route('admin.clientes.index')" icon="ti-user-circle" label="Clientes" :active="request()->routeIs('admin.clientes.*')" />
        </div>

        {{-- ---------------- VENTAS (PEDIDO, FACTURA, PAGO, METODO_PAGO, TURNO_CAJA) ---------------- --}}
        <div>
            <p class="px-3 mb-2 text-xs font-semibold uppercase text-gray-400">Ventas</p>
            <x-admin.menu-item :href="route('admin.pedidos.index')" icon="ti-receipt" label="Pedidos" :active="request()->routeIs('admin.pedidos.*')" />
            <x-admin.menu-item :href="route('admin.facturas.index')" icon="ti-file-invoice" label="Facturas" :active="request()->routeIs('admin.facturas.*')" />
            <x-admin.menu-item :href="route('admin.pagos.index')" icon="ti-cash" label="Pagos" :active="request()->routeIs('admin.pagos.*')" />
            <x-admin.menu-item :href="route('admin.metodos-pago.index')" icon="ti-credit-card" label="Métodos de pago" :active="request()->routeIs('admin.metodos-pago.*')" />
            <x-admin.menu-item :href="route('admin.turnos.index')" icon="ti-clock-hour-4" label="Turnos de caja" :active="request()->routeIs('admin.turnos.*')" />
        </div>

        {{-- ---------------- CONTROL (AUDITORIA_PEDIDO, ALERTA_STOCK) ---------------- --}}
        <div>
            <p class="px-3 mb-2 text-xs font-semibold uppercase text-gray-400">Control</p>
            <x-admin.menu-item :href="route('admin.auditoria.index')" icon="ti-history" label="Auditoría de pedidos" :active="request()->routeIs('admin.auditoria.*')" />
            <x-admin.menu-item :href="route('admin.alertas.index')" icon="ti-alert-triangle" label="Alertas de stock" :active="request()->routeIs('admin.alertas.*')" />
        </div>

    </nav>
</aside>
