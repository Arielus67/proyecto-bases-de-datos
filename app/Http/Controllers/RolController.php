<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Permiso;
use App\Models\Rol;
use App\Support\Sp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RolController extends Controller
{
    public function index()
    {
        $items = Rol::with('permisos')->withCount('empleados')->orderBy('nombre_rol')->paginate(15);

        return view('admin.roles.index', compact('items'));
    }

    public function create()
    {
        return view('admin.roles.form', ['item' => null, 'marcados' => [], 'modulos' => $this->modulos()]);
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request);

        return $this->intentar(
            fn () => $this->guardar(null, $datos['nombre_rol'], $datos['permisos']),
            'Rol creado correctamente.',
            'admin.roles.index'
        );
    }

    public function edit($id)
    {
        $item = Rol::with('permisos')->findOrFail($id);

        return view('admin.roles.form', [
            'item' => $item,
            'marcados' => $item->permisos->pluck('clave')->all(),
            'modulos' => $this->modulos(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $rol = Rol::findOrFail($id);
        $datos = $this->validar($request, $rol->id_rol);

        $this->exigirQueAlguienAdministreRoles($rol, $datos['permisos']);

        return $this->intentar(
            fn () => $this->guardar($rol->id_rol, $datos['nombre_rol'], $datos['permisos']),
            'Rol actualizado correctamente.',
            'admin.roles.index'
        );
    }

    public function destroy($id)
    {
        $rol = Rol::withCount('empleados')->findOrFail($id);

        if ($rol->empleados_count > 0) {
            return back()->with('error', 'No se puede eliminar el rol: tiene ' . $rol->empleados_count . ' empleado(s) asignado(s). Cámbialos de rol primero.');
        }

        return $this->intentar(
            fn () => Sp::disponibles() ? Sp::ejecutar('sp_rol_eliminar', ['id_rol' => $rol->id_rol]) : $rol->delete(),
            'Rol eliminado correctamente.',
            'admin.roles.index'
        );
    }

    private function modulos()
    {
        return Permiso::orderBy('id_permiso')->get()->groupBy('modulo');
    }

    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'nombre_rol' => ['required', 'string', 'max:50', Rule::unique(Rol::class, 'nombre_rol')->ignore($id, 'id_rol')],
            'permisos' => ['required', 'array', 'min:1'],
            'permisos.*' => ['string', 'distinct', Rule::exists(Permiso::class, 'clave')],
        ], [
            'permisos.required' => 'Selecciona al menos un permiso para el rol.',
            'permisos.*.exists' => 'Uno de los permisos seleccionados no existe.',
        ]);
    }

    private function exigirQueAlguienAdministreRoles(Rol $rol, array $permisos): void
    {
        if (in_array('roles.editar', $permisos, true)) {
            return;
        }

        $otrosAdministradores = Empleado::where('activo', 1)
            ->where('id_rol', '<>', $rol->id_rol)
            ->whereHas('rol.permisos', fn ($consulta) => $consulta->where('clave', 'roles.editar'))
            ->exists();

        if (! $otrosAdministradores) {
            throw ValidationException::withMessages([
                'permisos' => 'Este rol debe conservar el permiso "Editar roles y asignar permisos": de lo contrario nadie podría administrar los roles.',
            ]);
        }
    }

    private function guardar(?int $id, string $nombre, array $permisos): void
    {
        $permisos = array_values(array_unique($permisos));

        if (Sp::disponibles()) {
            $parametros = ['nombre_rol' => $nombre, 'permisos' => json_encode($permisos)];

            $id === null
                ? Sp::insertar('sp_rol_insertar', $parametros, 'id_rol')
                : Sp::ejecutar('sp_rol_actualizar', ['id_rol' => $id] + $parametros);

            return;
        }

        DB::connection('sqlsrv')->transaction(function () use ($id, $nombre, $permisos) {
            $rol = $id === null ? new Rol : Rol::findOrFail($id);
            $rol->nombre_rol = trim($nombre);
            $rol->save();
            $rol->permisos()->sync(Permiso::whereIn('clave', $permisos)->pluck('id_permiso'));
        });
    }
}
