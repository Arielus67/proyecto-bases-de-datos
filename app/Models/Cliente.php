<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'CLIENTE';

    protected $primaryKey = 'id_cliente';

    public $timestamps = false;
}