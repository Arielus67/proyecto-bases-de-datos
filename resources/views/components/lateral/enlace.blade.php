@props(['href', 'icon' => 'ti-circle', 'label', 'active' => false])

<a href="{{ $href }}" data-etiqueta="{{ $label }}" class="lateral__enlace {{ $active ? 'lateral__enlace--activo' : '' }}" @if ($active) aria-current="page" @endif>
    <i class="ti {{ $icon }}"></i>
    <span class="lateral__texto">{{ $label }}</span>
</a>
