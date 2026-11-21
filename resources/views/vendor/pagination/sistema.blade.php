@if ($paginator->hasPages())
    <nav class="paginacion" role="navigation" aria-label="Paginación">
        <p>
            Mostrando <span class="num">{{ $paginator->firstItem() }}</span>–<span class="num">{{ $paginator->lastItem() }}</span>
            de <span class="num" data-odometro>{{ $paginator->total() }}</span>
        </p>

        <div class="paginacion__enlaces">
            @if ($paginator->onFirstPage())
                <span class="paginacion__pagina paginacion__pagina--inactiva" aria-disabled="true" aria-label="Anterior"><i class="ti ti-chevron-left"></i></span>
            @else
                <a class="paginacion__pagina" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Anterior"><i class="ti ti-chevron-left"></i></a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="paginacion__pagina paginacion__pagina--inactiva">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="paginacion__pagina paginacion__pagina--actual" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="paginacion__pagina" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="paginacion__pagina" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Siguiente"><i class="ti ti-chevron-right"></i></a>
            @else
                <span class="paginacion__pagina paginacion__pagina--inactiva" aria-disabled="true" aria-label="Siguiente"><i class="ti ti-chevron-right"></i></span>
            @endif
        </div>
    </nav>
@endif
