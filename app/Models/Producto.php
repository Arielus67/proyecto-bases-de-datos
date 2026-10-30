<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $table = 'PRODUCTO';
    protected $primaryKey = 'id_producto';

    public $timestamps = false;

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function estacion()
    {
        return $this->belongsTo(Estacion::class, 'id_estacion', 'id_estacion');
    }

    public function recetas(): HasMany
    {
        return $this->hasMany(Receta::class, 'id_producto', 'id_producto');
    }
}