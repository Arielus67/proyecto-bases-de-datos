<?php

namespace App\Console\Commands;

use App\Models\BitacoraEventoNoSql;
use App\Models\ComandaNoSql;
use App\Models\TiempoCocinaNoSql;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Crea los índices que usan las consultas de cocina y de bitácora en MongoDB.
 * Es idempotente: si el índice ya existe, MongoDB no lo duplica.
 */
class CrearIndicesMongo extends Command
{
    protected $signature = 'restaurante:mongo-indices';

    protected $description = 'Crea los índices de MongoDB usados por cocina, bitácora y tiempos';

    /**
     * Nombres que la versión anterior generaba sola (laravel-mongodb ignoraba $collection).
     * Se renombran a los nombres definidos en los modelos sin perder documentos ni índices.
     */
    private const NOMBRES_ANTERIORES = [
        'comanda_no_sqls'         => 'comandas',
        'bitacora_evento_no_sqls' => 'bitacora_eventos',
        'tiempo_cocina_no_sqls'   => 'tiempos_cocina',
    ];

    public function handle(): int
    {
        $this->renombrarColeccionesAnteriores();

        ComandaNoSql::raw(function ($coleccion) {
            $coleccion->createIndex(['id_pedido_sql' => 1], ['name' => 'ix_comanda_id_pedido_sql']);
            $coleccion->createIndex(['estado' => 1, 'created_at' => 1], ['name' => 'ix_comanda_estado_fecha']);
        });

        TiempoCocinaNoSql::raw(function ($coleccion) {
            $coleccion->createIndex(['id_pedido_sql' => 1], ['name' => 'ix_tiempo_id_pedido_sql']);
        });

        BitacoraEventoNoSql::raw(function ($coleccion) {
            $coleccion->createIndex(['created_at' => -1], ['name' => 'ix_bitacora_fecha']);
        });

        $this->info('Índices de MongoDB verificados.');

        return self::SUCCESS;
    }

    private function renombrarColeccionesAnteriores(): void
    {
        $bd = DB::connection('mongodb')->getMongoDB();
        $existentes = collect(iterator_to_array($bd->listCollectionNames()));

        foreach (self::NOMBRES_ANTERIORES as $anterior => $nuevo) {
            if (! $existentes->contains($anterior)) {
                continue;
            }

            if (! $existentes->contains($nuevo)) {
                $this->getMongoAdmin()->command([
                    'renameCollection' => $bd->getDatabaseName() . '.' . $anterior,
                    'to'               => $bd->getDatabaseName() . '.' . $nuevo,
                ]);
                $this->line("  colección {$anterior} renombrada a {$nuevo}");
                continue;
            }

            // Ya existen las dos: se pasan los documentos de la anterior a la nueva y se elimina la anterior
            $origen = $bd->selectCollection($anterior);
            $destino = $bd->selectCollection($nuevo);
            $movidos = 0;

            foreach ($origen->find() as $documento) {
                $destino->replaceOne(['_id' => $documento['_id']], $documento, ['upsert' => true]);
                $movidos++;
            }

            $origen->drop();
            $this->line("  {$movidos} documentos de {$anterior} unidos a {$nuevo}");
        }
    }

    private function getMongoAdmin(): \MongoDB\Database
    {
        return DB::connection('mongodb')->getMongoClient()->selectDatabase('admin');
    }
}
