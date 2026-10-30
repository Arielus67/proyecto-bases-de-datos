@extends('layouts.app')

@section('titulo', 'Pedido #' . $item->id_pedido)

@section('botones')
    <a href="{{ route('admin.pedidos.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Volver</a>
@endsection

@section('contenido')
    {{-- El controlador envía $item = pedido con mesa, empleado, anuladoPor y detalles.producto cargados --}}
    @php
        $colores = ['PENDIENTE' => 'gray', 'PREPARANDO' => 'yellow', 'LISTO' => 'green', 'ENTREGADO' => 'blue', 'CANCELADO' => 'red'];
    @endphp

    {{-- ========== DATOS GENERALES DEL PEDIDO (tabla PEDIDO) ========== --}}
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 text-sm">
            <div><p class="text-gray-500">Mesa</p><p class="font-medium">Mesa {{ $item->mesa->numero_mesa ?? '—' }}</p></div>
            <div><p class="text-gray-500">Mesero</p><p class="font-medium">{{ $item->empleado->nombre ?? '—' }} {{ $item->empleado->apellido ?? '' }}</p></div>
            <div><p class="text-gray-500">Fecha</p><p class="font-medium">{{ \Carbon\Carbon::parse($item->fecha_hora)->format('d/m/Y H:i') }}</p></div>
            <div><p class="text-gray-500">Estado</p><p class="font-medium"><x-admin.badge :color="$item->estado === 'ANULADO' ? 'red' : 'blue'">{{ $item->estado }}</x-admin.badge></p></div>
            <div><p class="text-gray-500">Subtotal</p><p class="font-medium">₡{{ number_format($item->subtotal, 2) }}</p></div>
            <div><p class="text-gray-500">Observaciones</p><p class="font-medium">{{ $item->observaciones ?? '—' }}</p></div>
        </div>

        {{-- Datos de anulación (si aplica) --}}
        @if ($item->estado === 'ANULADO')
            <div class="mt-5 rounded-md bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                Anulado por {{ $item->anuladoPor->nombre ?? '—' }} {{ $item->anuladoPor->apellido ?? '' }} — Motivo: {{ $item->motivo_anulacion ?? '—' }}
            </div>
        @endif
    </div>

    {{-- ========== DETALLE DEL PEDIDO (tabla DETALLE_PEDIDO) ========== --}}
    <h3 class="text-lg font-semibold mb-3">Detalle</h3>
    <x-admin.table :headers="['Línea', 'Producto', 'Cantidad', 'Precio unitario', 'Total', 'Estado cocina', 'Notas']">
        @forelse ($item->detalles as $detalle)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $detalle->num_linea }}</td>
                <td class="px-4 py-3">{{ $detalle->producto->nombre_producto ?? '—' }}</td>
                <td class="px-4 py-3">{{ $detalle->cantidad }}</td>
                <td class="px-4 py-3">₡{{ number_format($detalle->precio_unitario, 2) }}</td>
                <td class="px-4 py-3">₡{{ number_format($detalle->cantidad * $detalle->precio_unitario, 2) }}</td>
                <td class="px-4 py-3"><x-admin.badge :color="$colores[$detalle->estado_cocina] ?? 'gray'">{{ $detalle->estado_cocina }}</x-admin.badge></td>
                <td class="px-4 py-3 text-gray-500">{{ $detalle->notas ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">El pedido no tiene líneas</td></tr>
        @endforelse
    </x-admin.table>

    {{-- ========== ANULAR PEDIDO (solo si no está facturado ni anulado) ========== --}}
    @unless (in_array($item->estado, ['FACTURADO', 'ANULADO']))
        <form method="POST" action="{{ route('admin.pedidos.anular', $item->id_pedido) }}" data-confirm="¿Anular este pedido?"
              class="bg-white border border-red-200 rounded-lg p-6 mt-6">
            @csrf
            @method('PATCH')
            <h3 class="text-lg font-semibold text-red-700 mb-3">Anular pedido</h3>
            <x-admin.input name="motivo_anulacion" label="Motivo de anulación" required maxlength="255" />
            <button class="mt-4 rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:opacity-90">Anular pedido</button>
        </form>
    @endunless
@endsection
