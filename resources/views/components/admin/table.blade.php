{{-- COMPONENTE: tabla de listado
     Uso: <x-admin.table :headers="['#','Nombre','Acciones']"> filas <x-slot:footer>paginación</x-slot:footer> </x-admin.table> --}}
@props(['headers' => []])

<div class="bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm text-left">
            {{-- Encabezados --}}
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    @foreach ($headers as $header)
                        <th class="px-4 py-3 font-semibold">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            {{-- Filas --}}
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>

    {{-- Paginación --}}
    @isset($footer)
        <div class="px-4 py-3 border-t border-gray-100">{{ $footer }}</div>
    @endisset
</div>
