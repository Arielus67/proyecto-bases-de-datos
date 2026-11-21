<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class TiempoCocinaNoSql extends Model
{
    protected $connection = 'mongodb';
    // laravel-mongodb 5 usa $table como nombre de la colección ($collection ya no se lee)
    protected $table = 'tiempos_cocina';

    protected $fillable = [
        'id_pedido_sql',
        'id_producto',
        'tiempo_espera_minutos',
        'estado_etapa', // PENDIENTE, PREPARANDO, LISTO
        'registrado_at'
    ];
}
