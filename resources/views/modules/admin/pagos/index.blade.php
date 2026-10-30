@extends('layouts.app')

@section('titulo', 'Pagos')

@section('contenido')
    {{-- ========== FILTRO POR MÉTODO DE PAGO ========== --}}
    <form method="GET" class="flex flex-wrap items-end gap-3 mb-5">
        <x-admin.select name="id_metodo_pago" label="Método de pago" :options="$metodos" :value="request('id_metodo_pago')" placeholder="Todos" />
        <button class="rounded-md bg-primary px-4 py-2 text-sm text-white mb-0.5">Filtrar</button>
        <a href="{{ route('admin.pagos.index') }}" class="px-3 py-2 text-sm text-gray-500 hover:underline">Limpiar</a>
    </form>

    {{-- ========== LISTADO DE PAGOS (tabla PAGO, solo lectura) ========== --}}
    <x-admin.table :headers="['#', 'Factura', 'Método', 'Monto', 'Referencia', 'Fecha']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_pago }}</td>
                <td class="px-4 py-3"><a href="{{ route('admin.facturas.show', $item->id_factura) }}" class="text-primary hover:underline">{{ $item->factura->numero_factura ?? '#' . $item->id_factura }}</a></td>
                <td class="px-4 py-3">{{ $item->metodo->nombre_metodo ?? '—' }}</td>
                <td class="px-4 py-3">₡{{ number_format($item->monto, 2) }}</td>
                <td class="px-4 py-3">{{ $item->referencia ?? '—' }}</td>
                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($item->fecha_hora)->format('d/m/Y H:i') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->withQueryString()->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
