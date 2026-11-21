@extends('layouts.app')

@section('titulo', 'Detalle: ' . $titulo)

@section('contenido')
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 max-w-3xl">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
            @foreach ($campos as $etiqueta => $valor)
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ str_replace('_', ' ', $etiqueta) }}</dt>
                    <dd class="text-gray-900 break-words">{{ $valor }}</dd>
                </div>
            @endforeach
        </dl>

        <div class="mt-6">
            <a href="{{ $volver }}" class="text-primary hover:underline">← Volver al listado</a>
        </div>
    </div>
@endsection
