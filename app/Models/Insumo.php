<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Insumo extends Model
{
    protected $table = 'INSUMO';

    protected $primaryKey = 'id_insumo';

    public $timestamps = false;

    protected $fillable = [
        'nombre_insumo',
        'unidad_medida',
        'stock_actual',
        'stock_minimo',
        'costo_unitario',
    ];
}