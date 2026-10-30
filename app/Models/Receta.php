<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receta extends Model
{
    protected $table = 'RECETA';

    public $timestamps = false;

    public $incrementing = false;

    protected $primaryKey = null;

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'id_producto', 'id_producto');
    }

    public function insumo()
    {
        return $this->belongsTo(Insumo::class, 'id_insumo', 'id_insumo');
    }
}