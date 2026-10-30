<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $table = 'PAGO';

    protected $primaryKey = 'id_pago';

    public $timestamps = false;

    protected $fillable = [
        'id_factura',
        'id_metodo_pago',
        'monto',
        'referencia',
        'fecha_hora',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(
            Factura::class,
            'id_factura',
            'id_factura'
        );
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(
            MetodoPago::class,
            'id_metodo_pago',
            'id_metodo_pago'
        );
    }
    public function metodo(): BelongsTo
{
    return $this->belongsTo(
        MetodoPago::class,
        'id_metodo_pago',
        'id_metodo_pago'
    );
}
}