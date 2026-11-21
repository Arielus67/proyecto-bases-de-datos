<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    public function anuladaPor(): BelongsTo
    {
        return $this->belongsTo(
            Empleado::class,
            'anulada_por',
            'id_empleado'
        );
    }

    public function cajero(): BelongsTo
    {
        return $this->belongsTo(
            Empleado::class,
            'id_empleado',
            'id_empleado'
        );
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(
            Pago::class,
            'id_factura',
            'id_factura'
        );
    }
}