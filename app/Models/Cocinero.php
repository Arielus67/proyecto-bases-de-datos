<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cocinero extends Model
{
    protected $table = 'COCINERO';

    protected $primaryKey = 'id_empleado';

    public $incrementing = false;

    public $timestamps = false;

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(
            Empleado::class,
            'id_empleado',
            'id_empleado'
        );
    }

    public function estacion(): BelongsTo
    {
        return $this->belongsTo(
            Estacion::class,
            'id_estacion',
            'id_estacion'
        );
    }
}
