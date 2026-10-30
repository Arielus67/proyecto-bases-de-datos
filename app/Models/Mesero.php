<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mesero extends Model
{
    protected $table = 'MESERO';

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
}