<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    protected $table = 'METODO_PAGO';

    protected $primaryKey = 'id_metodo_pago';

    public $timestamps = false;
}