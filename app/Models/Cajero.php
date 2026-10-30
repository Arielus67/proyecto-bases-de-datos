<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use MongoDB\Laravel\Relations\BelongsTo;

class Cajero extends Model
{
         protected $table = 'CAJERO';

        protected $primaryKey = 'id_empleado';

        public $timestamps = false;

        public function empleado(): BelongsTo
{
    return $this->belongsTo(Empleado::class, 'id_empleado', 'id_empleado');
}
}