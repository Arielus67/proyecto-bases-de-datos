<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class BitacoraEventoNoSql extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'bitacora_eventos';

    protected $fillable = [
        'evento',
        'detalles',
        'created_at'
    ];
}
