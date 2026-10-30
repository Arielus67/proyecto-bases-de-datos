{{-- COMPONENTE: lista desplegable. options = [valor => texto] --}}
@props(['name', 'label', 'options' => [], 'value' => null, 'required' => false, 'placeholder' => 'Seleccione...'])

<div>
    <label for="{{ $name }}" class="block text-sm font-medium mb-1">
        {{ $label }} @if ($required)<span class="text-red-500">*</span>@endif
    </label>
    <select id="{{ $name }}" name="{{ $name }}" @if ($required) required @endif
            {{ $attributes->merge(['class' => 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:outline-none']) }}>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $key => $text)
            <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>
        @endforeach
    </select>
    @error($name)
        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
    @enderror
</div>
