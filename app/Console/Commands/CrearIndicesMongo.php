<?php

namespace App\Console\Commands;

use App\Models\BitacoraEventoNoSql;
use App\Models\ComandaNoSql;
use App\Models\TiempoCocinaNoSql;
use Illuminate\Console\Command;

/**
 * Crea los índices que usan las consultas de cocina y de bitácora en MongoDB.
 * Es idempotente: si el índice ya existe, MongoDB no lo duplica.
 */
class CrearIndicesMongo extends Command
{
    protected $signature = 'restaurante:mongo-indices';

    protected $description = 'Crea los índices de MongoDB usados por cocina, bitácora y tiempos';

    public function handle(): int
    {
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
}
