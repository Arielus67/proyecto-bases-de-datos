{{-- COMPONENTE: casilla (campos BIT: activo, atendida, etc.) --}}
@props(['name', 'label', 'checked' => true])

<div class="flex items-center gap-2 pt-6">
    {{-- Envía 0 cuando la casilla está desmarcada --}}
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox" id="{{ $name }}" name="{{ $name }}" value="1" class="rounded border-gray-300"
           @checked((bool) old($name, $checked))>
    <label for="{{ $name }}" class="text-sm font-medium">{{ $label }}</label>
</div>
