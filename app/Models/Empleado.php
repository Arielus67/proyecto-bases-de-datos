<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

class Empleado extends Authenticatable
{
    protected $table = 'EMPLEADO';

    protected $primaryKey = 'id_empleado';

    public $timestamps = false;

    protected $authPasswordName = 'contrasena_hash';

    protected $rememberTokenName = '';

    protected $hidden = [
        'contrasena_hash',
    ];

    public const PANTALLAS_DE_INICIO = [
        'dashboard.ver' => 'admin.dashboard',
        'mesero.usar' => 'mesero.index',
        'cocina.usar' => 'cocina.index',
        'caja.usar' => 'caja.index',
        'pedidos.ver' => 'admin.pedidos.index',
        'facturas.ver' => 'admin.facturas.index',
        'productos.ver' => 'admin.productos.index',
        'insumos.ver' => 'admin.insumos.index',
        'empleados.ver' => 'admin.empleados.index',
        'roles.ver' => 'admin.roles.index',
        'reportes.ver' => 'admin.reportes.ventas',
        'respaldos.ver' => 'admin.respaldos.index',
    ];

    private ?array $permisosCargados = null;

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'id_rol', 'id_rol');
    }

    public function mesero(): HasOne
    {
        return $this->hasOne(Mesero::class, 'id_empleado', 'id_empleado');
    }

    public function cocinero(): HasOne
    {
        return $this->hasOne(Cocinero::class, 'id_empleado', 'id_empleado');
    }

    public function cajero(): HasOne
    {
        return $this->hasOne(Cajero::class, 'id_empleado', 'id_empleado');
    }

    public function sesionActiva(): HasOne
    {
        return $this->hasOne(SesionActiva::class, 'id_empleado', 'id_empleado');
    }

    public function permisos(): array
    {
        return $this->permisosCargados ??= DB::connection('sqlsrv')
            ->table('vw_permisos_empleado')
            ->where('id_empleado', $this->id_empleado)
            ->pluck('clave')
            ->all();
    }

    public function tienePermiso(string $clave): bool
    {
        return (bool) $this->activo && in_array($clave, $this->permisos(), true);
    }

    public function rutaInicio(): ?string
    {
        foreach (self::PANTALLAS_DE_INICIO as $permiso => $ruta) {
            if ($this->tienePermiso($permiso)) {
                return route($ruta);
            }
        }

        return null;
    }
}
