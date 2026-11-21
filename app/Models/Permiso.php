<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permiso extends Model
{
    protected $table = 'PERMISO';

    protected $primaryKey = 'id_permiso';

    public $timestamps = false;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'ROL_PERMISO', 'id_permiso', 'id_rol');
    }
}
