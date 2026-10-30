<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Factura extends Model
{
    protected $table = 'FACTURA';

    protected $primaryKey = 'id_factura';

    public $timestamps = false;

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(
            Cliente::class,
            'id_cliente',
            'id_cliente'
        );
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(
            Pedido::class,
            'id_pedido',
            'id_pedido'
        );
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(
            Empleado::class,
            'id_empleado',
            'id_empleado'
        );
    }

    public function turno(): BelongsTo
    {
        return $this->belongsTo(
            TurnoCaja::class,
            'id_turno',
            'id_turno'
        );
    }
    public function cajero()
{
    return $this->hasOneThrough(
        Cajero::class,
        Empleado::class,
        'id_empleado', // FK en EMPLEADO
        'id_empleado', // FK en CAJERO
        'id_empleado', // FK local en FACTURA
        'id_empleado'  // PK/FK en EMPLEADO
    );
}
}