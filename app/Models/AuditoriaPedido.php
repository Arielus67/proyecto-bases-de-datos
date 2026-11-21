<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditoriaPedido extends Model
{
    protected $table = 'AUDITORIA_PEDIDO';

    protected $primaryKey = 'id_auditoria';

    public $timestamps = false;
}
