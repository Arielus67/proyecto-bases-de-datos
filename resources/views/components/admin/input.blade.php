{{-- COMPONENTE: campo de texto / número / fecha / contraseña con etiqueta y error --}}
@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium mb-1">
        {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
    </label>
    <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}"
           value="{{ $type === 'password' ? '' : old($name, $value) }}"
           @if ($required) required @endif
           {{ $attributes->merge(['class' => 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none']) }}>
    @error($name)
        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
    @enderror
</div>
