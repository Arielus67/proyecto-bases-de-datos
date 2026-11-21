<?php

namespace App\Models;

use App\Support\SesionUnica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class SesionActiva extends Model
{
    protected $table = 'SESION_ACTIVA';

    protected $primaryKey = 'id_sesion';

    public $timestamps = false;

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'ultima_actividad' => 'datetime',
    ];

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'id_empleado', 'id_empleado');
    }

    public function vigente(): bool
    {
        return $this->ultima_actividad instanceof Carbon
            && $this->ultima_actividad->greaterThanOrEqualTo(now()->subMinutes(SesionUnica::minutosInactividad()));
    }
}
