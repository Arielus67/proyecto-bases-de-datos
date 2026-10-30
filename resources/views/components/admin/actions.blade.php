{{-- COMPONENTE: botones de acción por fila (ver / editar / eliminar)
     Se puede añadir contenido extra por el slot (ej. botón "Atender"). --}}
@props(['show' => null, 'edit' => null, 'destroy' => null])

<div class="flex items-center gap-2">
    {{-- Ver detalle --}}
    @if ($show)
        <a href="{{ $show }}" title="Ver" class="text-gray-500 hover:text-primary"><i class="ti ti-eye text-lg"></i></a>
    @endif

    {{-- Editar --}}
    @if ($edit)
        <a href="{{ $edit }}" title="Editar" class="text-gray-500 hover:text-primary"><i class="ti ti-pencil text-lg"></i></a>
    @endif

    {{-- Eliminar (pide confirmación mediante layouts/js.blade.php) --}}
    @if ($destroy)
        <form method="POST" action="{{ $destroy }}" data-confirm="¿Seguro que deseas eliminar este registro?">
            @csrf
            @method('DELETE')
            <button type="submit" title="Eliminar" class="text-gray-500 hover:text-red-600"><i class="ti ti-trash text-lg"></i></button>
        </form>
    @endif

    {{-- Acciones extra --}}
    {{ $slot }}
</div>
