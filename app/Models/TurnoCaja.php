<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TurnoCaja extends Model
{
    protected $table = 'TURNO_CAJA';

    protected $primaryKey = 'id_turno';

    public $timestamps = false;

    protected $fillable = [
        'id_empleado',
        'fecha_apertura',
        'fecha_cierre',
        'monto_inicial',
        'monto_final',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(
            Empleado::class,
            'id_empleado',
            'id_empleado'
        );
    }

    public function facturas(): HasMany
    {
        return $this->hasMany(
            Factura::class,
            'id_turno',
            'id_turno'
        );
    }
}