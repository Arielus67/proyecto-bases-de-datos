@extends('layouts.app')

@section('titulo', 'Roles')

@section('botones')
    <x-admin.new-button :href="route('admin.roles.create')" label="Nuevo rol" />
@endsection

@section('contenido')
    <x-admin.table :headers="['#', 'Nombre del rol', 'Permisos', 'Empleados', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_rol }}</td>
                <td class="px-4 py-3 font-medium">{{ $item->nombre_rol }}</td>
                <td class="px-4 py-3">
                    <x-admin.badge :color="$item->permisos->isEmpty() ? 'red' : 'blue'">{{ $item->permisos->count() }} {{ $item->permisos->count() === 1 ? 'permiso' : 'permisos' }}</x-admin.badge>
                    <div class="mt-1 text-xs text-gray-500">{{ $item->permisos->pluck('modulo')->unique()->implode(', ') ?: 'Sin acceso a ningún módulo' }}</div>
                </td>
                <td class="px-4 py-3">{{ $item->empleados_count }}</td>
                <td class="px-4 py-3">
                    <x-admin.actions :edit="route('admin.roles.edit', $item->id_rol)" :destroy="route('admin.roles.destroy', $item->id_rol)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
