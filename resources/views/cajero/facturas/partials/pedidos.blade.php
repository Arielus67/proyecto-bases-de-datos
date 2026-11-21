@forelse ($pedidos as $p)
    <button type="button" class="pedido-card" data-id="{{ $p->id_pedido }}">
        <div class="pedido-top">
            <i class="ti ti-receipt-2"></i>
            <span class="pedido-estado">{{ str_replace('_', ' ', $p->estado) }}</span>
        </div>
        <h3 class="pedido-mesa">Mesa {{ $p->numero_mesa }}</h3>
        <p class="pedido-info">{{ $p->zona }} · Pedido #{{ $p->id_pedido }}</p>
        <p class="pedido-total num">₡{{ number_format((float) $p->subtotal, 2, ',', ' ') }}</p>
    </button>
@empty
    <div class="caja-vacio">
        <i class="ti ti-cash-off"></i>
        <p class="caja-vacio-titulo">No hay pedidos pendientes de cobro</p>
        <p>Los pedidos aparecen aquí cuando cocina los marca como listos.</p>
    </div>
@endforelse

<script type="application/json" id="caja-datos">@json((object) $pedidosJs)</script>
