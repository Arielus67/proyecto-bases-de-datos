<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Empleado extends Model
{
    protected $table = 'EMPLEADO';

    protected $primaryKey = 'id_empleado';

    public $timestamps = false;

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function mesero(): HasOne
    {
        return $this->hasOne(Mesero::class, 'id_empleado', 'id_empleado');
    }

    public function cocinero(): HasOne
    {
        return $this->hasOne(Cocinero::class, 'id_empleado', 'id_empleado');
    }

    public function cajero(): HasOne
    {
        return $this->hasOne(Cajero::class, 'id_empleado', 'id_empleado');
    }
}