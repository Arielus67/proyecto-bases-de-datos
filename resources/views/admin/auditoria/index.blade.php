@extends('layouts.app')

@section('titulo', 'Auditoría de pedidos')

@section('contenido')
    {{-- ========== FILTRO POR PEDIDO ========== --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-5">
        <x-admin.input name="id_pedido" label="N° de pedido" type="number" :value="request('id_pedido')" />
        <button class="rounded-md bg-primary px-4 py-2 text-sm text-white mb-0.5">Buscar</button>
        <a href="{{ route('admin.auditoria.index') }}" class="px-3 py-2 text-sm text-gray-500 hover:underline">Limpiar</a>
    </form>

    {{-- ========== BITÁCORA (tabla AUDITORIA_PEDIDO, solo lectura: la llena un trigger) ========== --}}
    <x-admin.table :headers="['#', 'Pedido', 'Acción', 'Usuario BD', 'Empleado app', 'Fecha', 'Estado', 'Subtotal']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_auditoria }}</td>
                <td class="px-4 py-3"><a href="{{ route('admin.pedidos.show', $item->id_pedido) }}" class="text-primary hover:underline">#{{ $item->id_pedido }}</a></td>
                <td class="px-4 py-3"><x-admin.badge color="blue">{{ $item->accion }}</x-admin.badge></td>
                <td class="px-4 py-3">{{ $item->usuario_bd }}</td>
                <td class="px-4 py-3">{{ $item->id_empleado_app ?? '—' }}</td>
                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($item->fecha_hora)->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3">{{ $item->estado_anterior ?? '—' }} → {{ $item->estado_nuevo ?? '—' }}</td>
                <td class="px-4 py-3">
                    {{ $item->subtotal_anterior !== null ? '₡' . number_format($item->subtotal_anterior, 2) : '—' }} →
                    {{ $item->subtotal_nuevo !== null ? '₡' . number_format($item->subtotal_nuevo, 2) : '—' }}
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->withQueryString()->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
