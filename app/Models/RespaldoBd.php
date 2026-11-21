<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RespaldoBd extends Model
{
    protected $table = 'RESPALDO_BD';

    protected $primaryKey = 'id_respaldo';

    public $timestamps = false;

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado::class, 'id_empleado', 'id_empleado');
    }
}
