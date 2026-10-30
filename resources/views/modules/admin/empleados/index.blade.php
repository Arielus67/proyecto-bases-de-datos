@extends('layouts.app')

@section('titulo', 'Empleados')

@section('botones')
    <x-admin.new-button :href="route('admin.empleados.create')" label="Nuevo empleado" />
@endsection

@section('contenido')
    {{-- LISTADO DE EMPLEADOS (tabla EMPLEADO + relación rol y subclases mesero/cocinero/cajero) --}}
    <x-admin.table :headers="['#', 'Empleado', 'Correo', 'Rol', 'Detalle del cargo', 'Ingreso', 'Estado', 'Acciones']">
        @forelse ($items as $item)
            <tr class="border-t border-gray-100">
                <td class="px-4 py-3">{{ $item->id_empleado }}</td>
                <td class="px-4 py-3 font-medium">{{ $item->nombre }} {{ $item->apellido }}</td>
                <td class="px-4 py-3">{{ $item->correo }}</td>
                <td class="px-4 py-3">{{ $item->rol->nombre_rol ?? '—' }}</td>

                {{-- Datos de la subclase según el rol --}}
                <td class="px-4 py-3 text-gray-500">
                    @if ($item->mesero)
                        Zona: {{ $item->mesero->zona_asignada }}
                    @elseif ($item->cocinero)
                        {{ $item->cocinero->especialidad }} · {{ $item->cocinero->estacion->nombre_estacion ?? '' }}
                    @elseif ($item->cajero)
                        Caja: {{ $item->cajero->codigo_caja }}
                    @else
                        —
                    @endif
                </td>

                <td class="px-4 py-3">{{ \Carbon\Carbon::parse($item->fecha_ingreso)->format('d/m/Y') }}</td>
                <td class="px-4 py-3">
                    <x-admin.badge :color="$item->activo ? 'green' : 'gray'">{{ $item->activo ? 'Activo' : 'Inactivo' }}</x-admin.badge>
                </td>
                <td class="px-4 py-3">
                    <x-admin.actions :edit="route('admin.empleados.edit', $item->id_empleado)" :destroy="route('admin.empleados.destroy', $item->id_empleado)" />
                </td>
            </tr>
        @empty
            <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">Sin registros</td></tr>
        @endforelse

        <x-slot:footer>{{ $items->links() }}</x-slot:footer>
    </x-admin.table>
@endsection
