@if ($listos->isNotEmpty())
    <div class="entregas">
        <h3 class="entregas-titulo"><i class="ti ti-bell-ringing"></i> Listos para entregar</h3>

        <div class="entregas-grid">
            @foreach ($listos as $l)
                <div class="entrega-card">
                    <div class="entrega-top">
                        <strong>Mesa {{ $l->numero_mesa }}</strong>
                        <span>Pedido #{{ $l->id_pedido }}</span>
                    </div>

                    <ul class="entrega-lista">
                        @foreach ($detallesListos[$l->id_pedido] ?? collect() as $d)
                            <li>{{ $d->cantidad }} × {{ $d->nombre_producto }}</li>
                        @endforeach
                    </ul>

                    <button type="button" class="btn-entregar" data-id="{{ $l->id_pedido }}">
                        <i class="ti ti-check"></i> Marcar entregado
                    </button>
                </div>
            @endforeach
        </div>
    </div>
@endif

<div class="mesas-cabecera">
    <div>
        <h2 class="mesas-titulo">Mesas del restaurante</h2>
        <p class="mesas-subtitulo">Selecciona una mesa disponible para abrir un pedido, o una ocupada para agregarle productos.</p>
    </div>
    <ul class="mesas-leyenda" aria-label="Estados de las mesas">
        <li><span class="punto punto--disponible"></span>Disponible</li>
        <li><span class="punto punto--ocupada"></span>Ocupada</li>
        <li><span class="punto punto--reservada"></span>Reservada</li>
    </ul>
</div>

<div class="mesas-grid">
    @forelse ($mesas as $mesa)
        @php
            $disponible = strtoupper($mesa->estado ?? '') === 'DISPONIBLE';
            $idPedidoAbierto = $disponible ? null : ($pedidosActivos[$mesa->id_mesa] ?? null);
            $bloqueada = !$disponible && !$idPedidoAbierto;
        @endphp

        <button type="button" class="mesa-card mesa-card--{{ strtolower($mesa->estado ?? 'disponible') }} {{ $idPedidoAbierto ? 'mesa-card--con-pedido' : '' }} {{ $bloqueada ? 'mesa-bloqueada' : '' }}"
            data-mesa-id="{{ $mesa->id_mesa }}" data-mesa-numero="{{ $mesa->numero_mesa }}"
            data-pedido-id="{{ $idPedidoAbierto }}"
            @disabled($bloqueada)
            title="{{ $bloqueada ? 'Mesa no disponible' : ($idPedidoAbierto ? 'Agregar productos al pedido #' . $idPedidoAbierto : '') }}"
            aria-label="Seleccionar mesa {{ $mesa->numero_mesa }}">

            <div class="mesa-top">
                <i class="ti ti-armchair"></i>
                <span class="mesa-estado">{{ strtoupper($mesa->estado ?? '') }}</span>
            </div>

            <h3 class="mesa-nombre">Mesa {{ $mesa->numero_mesa }}</h3>
            <p class="mesa-zona">{{ $mesa->zona ?: 'Sin zona' }}</p>
            <p class="mesa-cap">
                <i class="ti ti-users"></i>
                {{ $mesa->capacidad }} {{ $mesa->capacidad == 1 ? 'persona' : 'personas' }}
            </p>
            @if ($idPedidoAbierto)
                <p class="mesa-agregar"><i class="ti ti-plus"></i> Agregar al pedido #{{ $idPedidoAbierto }}</p>
            @endif
        </button>
    @empty
        <div class="mesas-vacio">
            <i class="ti ti-armchair"></i>
            <p>No hay mesas registradas</p>
        </div>
    @endforelse
</div>
