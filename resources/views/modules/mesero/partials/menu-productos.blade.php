
<div>
    <!-- Encabezado -->
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Menú del restaurante</h2>
            <p class="text-sm text-gray-500 mt-1">
                Mesa {{ $mesa->numero_mesa }} · Selecciona los productos
            </p>
        </div>

        <button type="button" id="cerrar-menu"
            class="rounded-lg bg-gray-100 hover:bg-gray-200 px-4 py-2 text-gray-700">
            Cerrar
        </button>
    </div>

    <!-- Productos -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        @forelse ($productos as $producto)
            <article class="border border-gray-200 rounded-xl p-4 hover:shadow-md transition">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold text-gray-900">
                            {{ $producto->nombre_producto }}
                        </h3>

                        <p class="text-sm text-gray-500 mt-2">
                            {{ $producto->descripcion ?: 'Sin descripción disponible' }}
                        </p>
                    </div>

                    <i class="ti ti-tools-kitchen-2 text-2xl text-gray-500"></i>
                </div>

                <div class="flex justify-between items-center mt-5 gap-3">
                    <span class="font-bold text-gray-900">
                        ₡{{ number_format($producto->precio, 2, ',', '.') }}
                    </span>

                    <button type="button"
                        class="comanda-agregar bg-gray-900 hover:bg-gray-700 text-white rounded-lg px-3 py-2 text-sm"
                        data-id="{{ $producto->id_producto }}"
                        data-nombre="{{ $producto->nombre_producto }}"
                        data-precio="{{ $producto->precio }}">
                        <i class="ti ti-plus"></i>
                        Agregar
                    </button>
                </div>
            </article>
        @empty
            <div class="col-span-full text-center py-10">
                <i class="ti ti-package text-4xl text-gray-400"></i>
                <p class="font-semibold text-gray-700 mt-2">
                    No hay productos disponibles
                </p>
                <p class="text-sm text-gray-500">
                    Verifica que existan productos con activo = 1 en la base de datos.
                </p>
            </div>
        @endforelse

    </div>
</div>
