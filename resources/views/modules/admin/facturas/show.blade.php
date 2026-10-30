@extends('layouts.app')

@section('titulo', 'Factura ' . $item->numero_factura)

@section('botones')
    <a href="{{ route('admin.facturas.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Volver</a>
@endsection

@section('contenido')
    {{-- El controlador envía $item = factura con cliente, cajero, turno y pagos.metodo cargados --}}

    {{-- ========== DATOS DE LA FACTURA (tabla FACTURA) ========== --}}
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 text-sm">
            <div><p class="text-gray-500">Pedido</p><p class="font-medium"><a href="{{ route('admin.pedidos.show', $item->id_pedido) }}" class="text-primary hover:underline">#{{ $item->id_pedido }}</a></p></div>
            <div><p class="text-gray-500">Cliente</p><p class="font-medium">{{ $item->cliente ? $item->cliente->nombre . ' ' . $item->cliente->apellido : 'Consumidor final' }}</p></div>
            <div><p class="text-gray-500">Cajero</p><p class="font-medium">{{ $item->cajero->nombre ?? '—' }} {{ $item->cajero->apellido ?? '' }}</p></div>
            <div><p class="text-gray-500">Turno de caja</p><p class="font-medium">#{{ $item->id_turno }}</p></div>
            <div><p class="text-gray-500">Fecha</p><p class="font-medium">{{ \Carbon\Carbon::parse($item->fecha_hora)->format('d/m/Y H:i') }}</p></div>
            <div><p class="text-gray-500">Estado</p><p class="font-medium"><x-admin.badge :color="$item->estado === 'COBRADA' ? 'green' : 'red'">{{ $item->estado }}</x-admin.badge></p></div>
        </div>

        {{-- Totales --}}
        <div class="mt-6 ml-auto max-w-xs text-sm space-y-1">
            <div class="flex justify-between"><span class="text-gray-500">Subtotal</span><span>₡{{ number_format($item->subtotal, 2) }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Descuento</span><span>₡{{ number_format($item->descuento, 2) }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Impuesto de ventas</span><span>₡{{ number_format($item->impuesto_ventas, 2) }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Servicio</span><span>₡{{ number_format($item->servicio, 2) }}</span></div>
            <div class="flex justify-between border-t pt-2 font-semibold"><span>Total</span><span>₡{{ number_format($item->total, 2) }}</span></div>
            <div class="flex justify-between"><span class="text-gray-500">Vuelto</span><span>₡{{ number_format($item->vuelto, 2) }}</span></div>
        </div>
    </div>

    {{-- ========== PAGOS DE LA FACTURA (tabla PAGO) ========== --}}
    <h3 class="text-lg font-semibold mb-3">Pagos</h3>
    <x-admin.table :headers="['#', 'Método', 'Monto', 'Referencia', 'Fecha']">
        @forelse ($item->pagos as $pago)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $pago->id_pago }}</td>
                <td class="px-4 py-3">{{ $pago->metodo->nombre_metodo ?? '—' }}</td>
                <td class="px-4 py-3">₡{{ number_format($pago->monto, 2) }}</td>
                <td class="px-4 py-3">{{ $pago->referencia ?? '—' }}</td>
                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($pago->fecha_hora)->format('d/m/Y H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Sin pagos registrados</td></tr>
        @endforelse
    </x-admin.table>

    {{-- ========== ANULAR FACTURA ========== --}}
    @if ($item->estado === 'COBRADA')
        <form method="POST" action="{{ route('admin.facturas.anular', $item->id_factura) }}" data-confirm="¿Anular esta factura?" class="mt-6">
            @csrf
            @method('PATCH')
            <button class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:opacity-90">Anular factura</button>
        </form>
    @endif
@endsection
