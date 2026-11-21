<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected function urlImagen(): Attribute
    {
        // La columna foto guarda la ruta dentro de public/ (p. ej. img/productos/a1b2c3.jpg)
        return Attribute::get(fn () => $this->foto ? asset($this->foto) : null);
    }
}
