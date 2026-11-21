@extends('layouts.app')

@section('titulo', 'Respaldos de la base de datos')

@section('botones')
    <div class="flex items-center gap-2">
        @accesible(route('admin.respaldos.restaurar'))
            <a href="{{ route('admin.respaldos.restaurar') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm">
                <i class="ti ti-database-import"></i> Restaurar base de datos
            </a>
        @endaccesible
        @accesible(route('admin.respaldos.store'), 'POST')
            <form method="POST" action="{{ route('admin.respaldos.store') }}" data-confirm="¿Generar un respaldo completo de la base de datos ahora?">
                @csrf
                <button class="rounded-md bg-primary px-4 py-2 text-sm text-white">
                    <i class="ti ti-database-export"></i> Generar respaldo
                </button>
            </form>
        @endaccesible
    </div>
@endsection

@section('contenido')
    <div class="bg-white border border-gray-200 rounded-lg shadow-sm p-6 mb-6 text-sm">
        <p class="text-gray-500">Carpeta de respaldos en el servidor SQL Server</p>
        <p class="font-medium">{{ $ruta ?: 'Sin configurar (DB_BACKUP_PATH)' }}</p>
        <p class="text-gray-500 mt-1">
            @if (filled($rutaEnv))
                Tomada de DB_BACKUP_PATH en el archivo .env. Si no existe, SQL Server la crea al generar el primer respaldo.
            @elseif ($rutaSqlServer)
                DB_BACKUP_PATH está vacío en .env, así que se usa la carpeta de respaldos configurada en SQL Server.
            @else
                Escribe DB_BACKUP_PATH en el archivo .env con una carpeta del equipo donde corre SQL Server.
            @endif
            @if ($rutaSqlServer && $rutaSqlServer !== $ruta)
                Carpeta predeterminada de SQL Server: <span class="font-medium">{{ $rutaSqlServer }}</span>.
            @endif
        </p>
        <p class="text-gray-500 mt-2">Cada respaldo se guarda como un archivo .bak nuevo con fecha y hora en el nombre, se genera con CHECKSUM y se verifica con RESTORE VERIFYONLY. Los archivos no se publican en la aplicación.</p>
    </div>

    @if ($items === null)
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            No se pudo leer el historial. Ejecuta el script database/sql/1_RestauranteDB_estructura.sql en SQL Server para crear la tabla RESPALDO_BD.
        </div>
    @else
        <x-admin.table :headers="['#', 'Archivo', 'Fecha', 'Solicitado por', 'Verificado', 'Resultado', 'Acciones']">
            @forelse ($items as $item)
                <tr class="border-t border-gray-100">
                    <td class="px-4 py-3">{{ $item->id_respaldo }}</td>
                    <td class="px-4 py-3">{{ $item->nombre_archivo }}</td>
                    <td class="px-4 py-3">{{ \Carbon\Carbon::parse($item->fecha_hora)->format('d/m/Y H:i:s') }}</td>
                    <td class="px-4 py-3">{{ $item->empleado->nombre ?? '—' }} {{ $item->empleado->apellido ?? '' }}</td>
                    <td class="px-4 py-3"><x-admin.badge :color="$item->verificado ? 'green' : 'red'">{{ $item->verificado ? 'Sí' : 'No' }}</x-admin.badge></td>
                    <td class="px-4 py-3 text-gray-500">{{ $item->resultado ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            @accesible(route('admin.respaldos.verificar', $item->id_respaldo), 'POST')
                                <form method="POST" action="{{ route('admin.respaldos.verificar', $item->id_respaldo) }}">
                                    @csrf
                                    <button type="submit" title="Verificar integridad" class="text-gray-500 hover:text-primary"><i class="ti ti-shield-check text-lg"></i></button>
                                </form>
                            @endaccesible
                            @if ($item->verificado && \App\Support\Acceso::permite(route('admin.respaldos.restaurar')))
                                <a href="{{ route('admin.respaldos.restaurar', ['respaldo' => $item->id_respaldo]) }}" title="Restaurar este respaldo" class="text-gray-500 hover:text-red-600"><i class="ti ti-database-import text-lg"></i></a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500">Todavía no se han generado respaldos</td></tr>
            @endforelse

            <x-slot:footer>{{ $items->links() }}</x-slot:footer>
        </x-admin.table>
    @endif
@endsection
