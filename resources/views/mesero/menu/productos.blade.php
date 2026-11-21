<div class="menu">
    <header class="menu__cabecera">
        <div>
            <p class="menu__mesa">Mesa {{ $mesa->numero_mesa }}</p>
            <h2 class="menu__titulo">Menú del restaurante</h2>
            <p class="menu__ayuda">Selecciona los productos · <span id="menu-contador">0 productos en el pedido</span></p>
        </div>

        <button type="button" id="cerrar-menu" class="btn btn--secundario">
            <i class="ti ti-x"></i> Cerrar
        </button>
    </header>

    @if ($categorias->isNotEmpty())
        <div class="menu-herramientas">
            <div class="menu-busqueda">
                <i class="ti ti-search"></i>
                <input type="search" id="menu-buscar" placeholder="Buscar producto por nombre" autocomplete="off"
                    aria-label="Buscar producto por nombre">
            </div>

            <div class="menu-categorias" role="tablist" aria-label="Categorías">
                <button type="button" class="menu-chip activa" data-categoria="todas">Todos</button>
                @foreach ($categorias as $categoria)
                    <button type="button" class="menu-chip" data-categoria="{{ $categoria['id'] }}">
                        {{ $categoria['nombre'] }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div class="menu__cuerpo">
        @forelse ($categorias as $categoria)
            <section class="menu-seccion" data-categoria="{{ $categoria['id'] }}">
                <h3 class="menu-seccion-titulo">{{ $categoria['nombre'] }} <span class="num">{{ $categoria['productos']->count() }}</span></h3>

                <div class="menu-grid">
                    @foreach ($categoria['productos'] as $producto)
                        <article class="menu-producto" data-nombre="{{ $producto->nombre_producto }}">
                            @if ($producto->url_imagen)
                                <div class="menu-producto__imagen">
                                    <img src="{{ $producto->url_imagen }}" alt="{{ $producto->nombre_producto }}" loading="lazy" decoding="async">
                                </div>
                            @else
                                <div class="menu-producto__imagen menu-producto__imagen--vacia">
                                    <span class="menu-producto__icono"><i class="ti ti-tools-kitchen-2"></i></span>
                                </div>
                            @endif

                            <div class="menu-producto__contenido">
                                <h4 class="menu-producto__nombre">{{ $producto->nombre_producto }}</h4>

                                <p class="menu-producto__descripcion">{{ $producto->descripcion ?: 'Sin descripción disponible' }}</p>

                                <div class="menu-producto__pie">
                                    <span class="menu-producto__precio num">₡{{ number_format($producto->precio, 2, ',', '.') }}</span>

                                    <button type="button" class="comanda-agregar"
                                        data-id="{{ $producto->id_producto }}"
                                        data-nombre="{{ $producto->nombre_producto }}"
                                        data-precio="{{ $producto->precio }}">
                                        <i class="ti ti-plus"></i>
                                        Agregar
                                    </button>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="menu-sin-resultados">
                <i class="ti ti-package"></i>
                <p><strong>No hay productos disponibles</strong></p>
                <p>Verifica que existan productos con activo = 1 en la base de datos.</p>
            </div>
        @endforelse

        <div id="menu-sin-resultados" class="menu-sin-resultados" hidden>
            <i class="ti ti-search-off"></i>
            <p>No hay productos que coincidan con la búsqueda.</p>
        </div>
    </div>
</div>
