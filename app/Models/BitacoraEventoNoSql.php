<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BitacoraEventoNoSql extends Model
{
    protected $connection = 'mongodb';
    // laravel-mongodb 5 usa $table como nombre de la colección ($collection ya no se lee)
    protected $table = 'bitacora_eventos';

    protected $fillable = [
        'evento',
        'detalles',
        'created_at'
    ];
}
