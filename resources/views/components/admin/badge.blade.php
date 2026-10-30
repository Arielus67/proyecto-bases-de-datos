{{-- COMPONENTE: etiqueta de color (estados). Colores: green, red, yellow, blue, gray --}}
@props(['color' => 'gray'])

@php
    $clases = [
        'green'  => 'bg-green-100 text-green-700',
        'red'    => 'bg-red-100 text-red-700',
        'yellow' => 'bg-yellow-100 text-yellow-700',
        'blue'   => 'bg-blue-100 text-blue-700',
        'gray'   => 'bg-gray-100 text-gray-600',
    ][$color] ?? 'bg-gray-100 text-gray-600';
@endphp

<span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-medium {{ $clases }}">{{ $slot }}</span>
