@extends('layouts.app')

@section('titulo', 'Recetas')

@section('botones')
    <x-admin.new-button :href="route('admin.recetas.create')" label="Nueva receta" />
@endsection

@section('contenido')
    {{-- LISTADO DE RECETAS: un producto por fila con sus insumos (tabla RECETA, relación N:M PRODUCTO-INSUMO)
         El controlador envía $items = productos que tienen recetas, con ->recetas->insumo cargado. --}}
    <x-admin.table :headers="['Producto', 'Insumos de la receta', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3 font-medium">{{ $item->nombre_producto }}</td>
                <td class="px-4 py-3">
                    <ul class="text-gray-600">
                        @foreach ($item->recetas as $linea)
                            <li>{{ $linea->insumo->nombre_insumo }} — {{ rtrim(rtrim(number_format($linea->cantidad, 2), '0'), '.') }} {{ $linea->insumo->unidad_medida }}</li>
                        @endforeach
                    </ul>
                </td>
                <td class="px-4 py-3">
                    <x-admin.actions :edit="route('admin.recetas.edit', $item->id_producto)" :destroy="route('admin.recetas.destroy', $item->id_producto)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">Sin recetas registradas</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
