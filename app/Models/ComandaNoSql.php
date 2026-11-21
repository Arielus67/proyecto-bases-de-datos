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
        'detalles_con_modificadores', // Modificadores libres, notas y alérgenos (cada línea lleva su "ronda")
        'tiempos_cocina',
        'ronda_actual', // Número de la ronda que cocina debe preparar ahora
        'ronda_at',     // Hora en que se envió la ronda actual
    ];

    protected $casts = [
        'ronda_at' => 'datetime',
    ];

    public static function llaveBloqueo(int $idPedido): string
    {
        return 'comanda-mongo-' . $idPedido;
    }
}
