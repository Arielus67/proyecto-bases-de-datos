<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    protected $table = 'PEDIDO';

    protected $primaryKey = 'id_pedido';

    public $timestamps = false;

    // Relación con MESA
    public function mesa(): BelongsTo
    {
        return $this->belongsTo(
            Mesa::class,
            'id_mesa',
            'id_mesa'
        );
    }

    // Relación con EMPLEADO
    public function empleado(): BelongsTo
    {
        return $this->belongsTo(
            Empleado::class,
            'id_empleado',
            'id_empleado'
        );
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(
            Empleado::class,
            'anulado_por',
            'id_empleado'
        );
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(
            DetallePedido::class,
            'id_pedido',
            'id_pedido'
        )->orderBy('num_linea');
    }
}