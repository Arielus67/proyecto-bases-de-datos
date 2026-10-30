{{-- COMPONENTE: contenedor de formulario (crear / editar)
     Uso: <x-admin.form :action="..." :editing="(bool) $item" :cancel="route(...)"> campos </x-admin.form> --}}
@props(['action', 'editing' => false, 'cancel'])

<form method="POST" action="{{ $action }}" class="bg-white border border-gray-200 rounded-lg shadow-sm p-6">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    {{-- Campos --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        {{ $slot }}
    </div>

    {{-- Botones --}}
    <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
        <a href="{{ $cancel }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Cancelar</a>
        <button type="submit" class="rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90">Guardar</button>
    </div>
</form>
