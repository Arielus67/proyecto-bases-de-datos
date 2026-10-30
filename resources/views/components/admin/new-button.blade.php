{{-- COMPONENTE: botón "Nuevo ..." para la cabecera de cada listado --}}
@props(['href', 'label' => 'Nuevo'])

<a href="{{ $href }}" class="inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-medium text-white hover:opacity-90">
    <i class="ti ti-plus"></i> {{ $label }}
</a>
