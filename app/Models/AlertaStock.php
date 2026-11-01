<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertaStock extends Model
{
    protected $table = 'ALERTA_STOCK';

    protected $primaryKey = 'id_alerta';

    public $timestamps = false;

    public function insumo()
{
    return $this->belongsTo(Insumo::class, 'id_insumo', 'id_insumo');
}
}