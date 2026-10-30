<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Estacion extends Model
{
    protected $table = 'ESTACION';

    protected $primaryKey = 'id_estacion';

    public $timestamps = false;
}
