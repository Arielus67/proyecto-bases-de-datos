@extends('layouts.app')

@section('titulo', 'Productos')

@section('botones')
    <x-admin.new-button :href="route('admin.productos.create')" label="Nuevo producto" />
@endsection

@section('contenido')
    {{-- LISTADO DE PRODUCTOS (tabla PRODUCTO, con relaciones categoria y estacion) --}}
    <x-admin.table :headers="['#', 'Producto', 'Categoría', 'Estación', 'Precio', 'Estado', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_producto }}</td>
                <td class="px-4 py-3">
                    <p class="font-medium">{{ $item->nombre_producto }}</p>
                    <p class="text-xs text-gray-500">{{ $item->descripcion }}</p>
                </td>
                <td class="px-4 py-3">{{ $item->categoria->nombre_categoria ?? '—' }}</td>
                <td class="px-4 py-3">{{ $item->estacion->nombre_estacion ?? '—' }}</td>
                <td class="px-4 py-3">₡{{ number_format($item->precio, 2) }}</td>
                <td class="px-4 py-3">
                    <x-admin.badge :color="$item->activo ? 'green' : 'gray'">{{ $item->activo ? 'Activo' : 'Inactivo' }}</x-admin.badge>
                </td>
                <td class="px-4 py-3">
                    <x-admin.actions :edit="route('admin.productos.edit', $item->id_producto)" :destroy="route('admin.productos.destroy', $item->id_producto)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
