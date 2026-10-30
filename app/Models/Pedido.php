<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}