@props(['show' => null, 'edit' => null, 'destroy' => null])

<div class="flex items-center gap-2">
    @if (\App\Support\Acceso::permite($show))
        <a href="{{ $show }}" title="Ver" class="text-gray-500 hover:text-primary"><i class="ti ti-eye text-lg"></i></a>
    @endif

    @if (\App\Support\Acceso::permite($edit))
        <a href="{{ $edit }}" title="Editar" class="text-gray-500 hover:text-primary"><i class="ti ti-pencil text-lg"></i></a>
    @endif

    @if (\App\Support\Acceso::permite($destroy, 'DELETE'))
        <form method="POST" action="{{ $destroy }}" data-confirm="¿Seguro que deseas eliminar este registro?">
            @csrf
            @method('DELETE')
            <button type="submit" title="Eliminar" class="text-gray-500 hover:text-red-600"><i class="ti ti-trash text-lg"></i></button>
        </form>
    @endif

    {{ $slot }}
</div>
