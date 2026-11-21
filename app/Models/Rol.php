<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    public const MESERO = 'mesero';
    public const COCINERO = 'cocinero';
    public const CAJERO = 'cajero';

    public const PERMISOS_OPERATIVOS = [
        'mesero.usar' => self::MESERO,
        'cocina.usar' => self::COCINERO,
        'caja.usar' => self::CAJERO,
    ];

    protected $table = 'ROL';

    protected $primaryKey = 'id_rol';

    public $timestamps = false;

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'ROL_PERMISO', 'id_rol', 'id_permiso');
    }

    public function empleados(): HasMany
    {
        return $this->hasMany(Empleado::class, 'id_rol', 'id_rol');
    }

    public function puestoOperativo(): ?string
    {
        $puestos = $this->permisos
            ->pluck('clave')
            ->map(fn ($clave) => self::PERMISOS_OPERATIVOS[$clave] ?? null)
            ->filter()
            ->values();

        return $puestos->count() === 1 ? $puestos->first() : null;
    }
}
