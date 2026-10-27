<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    protected $table = 'PRODUCTO';

    protected $primaryKey = 'id_producto';

    public $timestamps = false;

    protected $fillable = [
        'nombre_producto',
        'descripcion',
        'precio',
        'id_categoria',
        'id_estacion',
        'activo'
    ];
}
