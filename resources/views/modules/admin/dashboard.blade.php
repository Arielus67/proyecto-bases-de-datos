@extends('layouts.app')

@section('titulo', 'Dashboard')

@section('contenido')
    {{-- El controlador envía $stats = [pedidos_abiertos, ventas_hoy, mesas_ocupadas, alertas_pendientes]
         y $ultimosPedidos (con ->mesa y ->empleado cargados). --}}

    {{-- ========== TARJETAS DE RESUMEN ========== --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5 mb-8">
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5">
            <p class="text-sm text-gray-500">Pedidos abiertos</p>
            <p class="text-3xl font-semibold mt-1">{{ $stats['pedidos_abiertos'] ?? 0 }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5">
            <p class="text-sm text-gray-500">Ventas de hoy</p>
            <p class="text-3xl font-semibold mt-1">₡{{ number_format($stats['ventas_hoy'] ?? 0, 2) }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5">
            <p class="text-sm text-gray-500">Mesas ocupadas</p>
            <p class="text-3xl font-semibold mt-1">{{ $stats['mesas_ocupadas'] ?? 0 }}</p>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-5">
            <p class="text-sm text-gray-500">Alertas de stock pendientes</p>
            <p class="text-3xl font-semibold mt-1 {{ ($stats['alertas_pendientes'] ?? 0) > 0 ? 'text-red-600' : '' }}">{{ $stats['alertas_pendientes'] ?? 0 }}</p>
        </div>
    </div>

    {{-- ========== ÚLTIMOS PEDIDOS ========== --}}
    <h3 class="text-lg font-semibold mb-3">Últimos pedidos</h3>
    <x-admin.table :headers="['#', 'Mesa', 'Mesero', 'Fecha', 'Subtotal', 'Estado']">
        @forelse ($ultimosPedidos ?? [] as $pedido)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3"><a href="{{ route('admin.pedidos.show', $pedido->id_pedido) }}" class="text-primary hover:underline">#{{ $pedido->id_pedido }}</a></td>
                <td class="px-4 py-3">Mesa {{ $pedido->mesa->numero_mesa ?? '—' }}</td>
                <td class="px-4 py-3">{{ $pedido->empleado->nombre ?? '—' }}</td>
                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($pedido->fecha_hora)->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3">₡{{ number_format($pedido->subtotal, 2) }}</td>
                <td class="px-4 py-3"><x-admin.badge>{{ $pedido->estado }}</x-admin.badge></td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Sin pedidos recientes</td></tr>
        @endforelse
    </x-admin.table>
@endsection
