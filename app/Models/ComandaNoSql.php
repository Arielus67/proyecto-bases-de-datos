<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ComandaNoSql extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'comandas';

    protected $fillable = [
        'id_pedido_sql', // Relación con el ID real de SQL Server
        'token_idempotencia',
        'id_mesa',
        'id_empleado',
        'estado',
        'detalles_con_modificadores', // Modificadores libres, notas y alérgenos
        'tiempos_cocina'
    ];
}
