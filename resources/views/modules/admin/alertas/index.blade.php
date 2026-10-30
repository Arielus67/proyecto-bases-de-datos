@extends('layouts.app')

@section('titulo', 'Alertas de stock')

@section('contenido')
    {{-- ========== LISTADO DE ALERTAS (tabla ALERTA_STOCK, generadas por trigger) ========== --}}
    <x-admin.table :headers="['#', 'Insumo', 'Stock actual', 'Stock mínimo', 'Fecha', 'Estado', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_alerta }}</td>
                <td class="px-4 py-3 font-medium">{{ $item->insumo->nombre_insumo ?? '—' }}</td>
                <td class="px-4 py-3">{{ number_format($item->stock_actual, 2) }}</td>
                <td class="px-4 py-3">{{ number_format($item->stock_minimo, 2) }}</td>
                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($item->fecha_hora)->format('d/m/Y H:i') }}</td>
                <td class="px-4 py-3"><x-admin.badge :color="$item->atendida ? 'green' : 'red'">{{ $item->atendida ? 'Atendida' : 'Pendiente' }}</x-admin.badge></td>
                <td class="px-4 py-3">
                    {{-- Marcar como atendida --}}
                    @unless ($item->atendida)
                        <form method="POST" action="{{ route('admin.alertas.atender', $item->id_alerta) }}">
                            @csrf
                            @method('PATCH')
                            <button class="text-sm text-primary hover:underline">Marcar atendida</button>
                        </form>
                    @endunless
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Sin alertas</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
