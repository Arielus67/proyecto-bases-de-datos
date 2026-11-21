@php
    $grupos = [
        'Principal' => [
            ['admin.dashboard', 'ti-layout-dashboard', 'Dashboard', 'admin.dashboard'],
        ],
        'Operación' => [
            ['mesero.index', 'ti-receipt-2', 'Gestión de comandas', 'mesero.*'],
            ['cocina.index', 'ti-flame', 'Cocina', 'cocina.*'],
            ['caja.index', 'ti-cash', 'Caja', 'caja.index'],
            ['caja.facturas', 'ti-file-invoice', 'Facturas emitidas', 'caja.facturas'],
        ],
        'Personal' => [
            ['admin.roles.index', 'ti-shield', 'Roles y permisos', 'admin.roles.*'],
            ['admin.empleados.index', 'ti-users', 'Empleados', 'admin.empleados.*'],
        ],
        'Carta e inventario' => [
            ['admin.categorias.index', 'ti-category', 'Categorías', 'admin.categorias.*'],
            ['admin.estaciones.index', 'ti-flame', 'Estaciones', 'admin.estaciones.*'],
            ['admin.productos.index', 'ti-soup', 'Productos', 'admin.productos.*'],
            ['admin.insumos.index', 'ti-packages', 'Insumos', 'admin.insumos.*'],
            ['admin.recetas.index', 'ti-notebook', 'Recetas', 'admin.recetas.*'],
        ],
        'Salón' => [
            ['admin.mesas.index', 'ti-armchair', 'Mesas', 'admin.mesas.*'],
            ['admin.clientes.index', 'ti-user-circle', 'Clientes', 'admin.clientes.*'],
        ],
        'Ventas' => [
            ['admin.pedidos.index', 'ti-receipt', 'Pedidos', 'admin.pedidos.*'],
            ['admin.facturas.index', 'ti-file-invoice', 'Facturas', 'admin.facturas.*'],
            ['admin.pagos.index', 'ti-cash', 'Pagos', 'admin.pagos.*'],
            ['admin.metodos-pago.index', 'ti-credit-card', 'Métodos de pago', 'admin.metodos-pago.*'],
            ['admin.turnos.index', 'ti-clock-hour-4', 'Turnos de caja', 'admin.turnos.*'],
            ['admin.reportes.ventas', 'ti-chart-bar', 'Reporte de ventas', 'admin.reportes.*'],
        ],
        'Control' => [
            ['admin.auditoria.index', 'ti-history', 'Auditoría de pedidos', 'admin.auditoria.*'],
            ['admin.alertas.index', 'ti-alert-triangle', 'Alertas de stock', 'admin.alertas.*'],
            ['admin.respaldos.index', 'ti-database-export', 'Respaldos', 'admin.respaldos.*'],
        ],
    ];
@endphp

<aside class="lateral {{ request()->cookie('menu_contraido') === '1' ? 'lateral--contraida' : '' }}" id="menu-lateral" aria-label="Menú principal">
    <button type="button" class="lateral__contraer" data-contraer-menu aria-controls="menu-lateral" aria-expanded="true" aria-label="Contraer el menú">
        <i class="ti ti-chevron-left"></i>
    </button>

    <a href="{{ route('inicio') }}" class="lateral__marca">
        <span class="marca-logo" aria-hidden="true"></span>
        <span class="marca-texto">
            <strong>{{ config('app.name') }}</strong>
            <small>Restaurante</small>
        </span>
    </a>

    <nav class="lateral__nav">
        @foreach ($grupos as $titulo => $enlaces)
            @php($visibles = array_filter($enlaces, fn ($enlace) => \App\Support\Acceso::permite(route($enlace[0]))))
            @if ($visibles)
                <div class="lateral__grupo">
                    <p class="lateral__titulo">{{ $titulo }}</p>
                    @foreach ($visibles as [$ruta, $icono, $etiqueta, $activa])
                        <x-lateral.enlace :href="route($ruta)" :icon="$icono" :label="$etiqueta" :active="request()->routeIs($activa)" />
                    @endforeach
                </div>
            @endif
        @endforeach
    </nav>

    <div class="lateral__pie">{{ config('app.name') }} · v2</div>
</aside>
