<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Estacion;
use App\Models\Rol;
use App\Support\SesionUnica;
use App\Support\Sp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Empleados y su subclase (MESERO, COCINERO o CAJERO según el rol).
 * EMPLEADO se escribe con sp_empleado_insertar / actualizar / eliminar; la subclase
 * se guarda en la misma transacción, así nunca queda un empleado "a medias".
 * La contraseña se guarda como hash bcrypt; el texto plano no se almacena nunca.
 */
class EmpleadoController extends Controller
{
    public function index()
    {
        $items = Empleado::with(['rol', 'mesero', 'cocinero.estacion', 'cajero', 'sesionActiva'])
            ->orderBy('apellido')
            ->orderBy('nombre')
            ->paginate(15);

        return view('admin.empleados.index', compact('items'));
    }

    public function cerrarSesion(Request $request, $id)
    {
        $empleado = Empleado::findOrFail($id);

        if ((int) $empleado->id_empleado === (int) $request->user()->id_empleado) {
            return back()->with('error', 'Para cerrar tu propia sesión usa el botón «Salir».');
        }

        return $this->intentar(
            fn () => SesionUnica::cerrar((int) $empleado->id_empleado),
            'Se cerró la sesión de ' . $empleado->nombre . ' ' . $empleado->apellido . '. Ya puede volver a entrar.',
            'admin.empleados.index'
        );
    }

    public function create()
    {
        return view('admin.empleados.form', ['item' => null] + $this->catalogos());
    }

    public function store(Request $request)
    {
        [$datos, $tipo, $extra] = $this->validar($request);

        return $this->intentar(function () use ($request, $datos, $tipo, $extra) {
            DB::transaction(function () use ($request, $datos, $tipo, $extra) {
                $id = Sp::insertar('sp_empleado_insertar', [
                    'nombre'          => $datos['nombre'],
                    'apellido'        => $datos['apellido'],
                    'correo'          => $datos['correo'],
                    'contrasena_hash' => Hash::make($datos['contrasena']),
                    'id_rol'          => $datos['id_rol'],
                    'activo'          => $request->boolean('activo'),
                ], 'id_empleado');

                $this->guardarSubclase($id, $tipo, $extra);
            });
        }, 'Empleado creado correctamente.', 'admin.empleados.index');
    }

    public function edit($id)
    {
        $item = Empleado::with(['mesero', 'cocinero', 'cajero'])->findOrFail($id);

        return view('admin.empleados.form', ['item' => $item] + $this->catalogos());
    }

    public function update(Request $request, $id)
    {
        $empleado = Empleado::findOrFail($id);
        [$datos, $tipo, $extra] = $this->validar($request, $id);

        return $this->intentar(function () use ($request, $empleado, $datos, $tipo, $extra) {
            DB::transaction(function () use ($request, $empleado, $datos, $tipo, $extra) {
                Sp::ejecutar('sp_empleado_actualizar', [
                    'id_empleado'     => $empleado->id_empleado,
                    'nombre'          => $datos['nombre'],
                    'apellido'        => $datos['apellido'],
                    'correo'          => $datos['correo'],
                    'contrasena_hash' => empty($datos['contrasena']) ? null : Hash::make($datos['contrasena']),
                    'id_rol'          => $datos['id_rol'],
                    'activo'          => $request->boolean('activo'),
                ]);

                $this->guardarSubclase($empleado->id_empleado, $tipo, $extra);
            });
        }, 'Empleado actualizado correctamente.', 'admin.empleados.index');
    }

    public function destroy($id)
    {
        return $this->intentar(
            fn () => Sp::ejecutar('sp_empleado_eliminar', ['id_empleado' => (int) $id]),
            'Empleado eliminado correctamente.',
            'admin.empleados.index'
        );
    }

    private function catalogos(): array
    {
        $roles = Rol::with('permisos')->orderBy('nombre_rol')->get();

        return [
            'roles'      => $roles->pluck('nombre_rol', 'id_rol'),
            'puestos'    => $roles->mapWithKeys(fn (Rol $rol) => [$rol->id_rol => $rol->puestoOperativo()]),
            'estaciones' => Estacion::orderBy('nombre_estacion')->pluck('nombre_estacion', 'id_estacion'),
        ];
    }

    private function tipoDeRol($idRol): ?string
    {
        return Rol::with('permisos')->find($idRol)?->puestoOperativo();
    }

    private function validar(Request $request, $id = null): array
    {
        $datos = $request->validate([
            'nombre'     => ['required', 'string', 'max:50'],
            'apellido'   => ['required', 'string', 'max:50'],
            'correo'     => ['required', 'email', 'max:100', Rule::unique('EMPLEADO', 'correo')->ignore($id, 'id_empleado')],
            'contrasena' => [$id ? 'nullable' : 'required', 'string', 'min:8', 'max:100'],
            'id_rol'     => ['required', 'integer', 'exists:ROL,id_rol'],
        ]);

        $tipo = $this->tipoDeRol($datos['id_rol']);

        $extra = match ($tipo) {
            Rol::MESERO => $request->validate([
                'zona_asignada' => ['required', 'string', 'max:50'],
            ]),
            Rol::COCINERO => $request->validate([
                'id_estacion'  => ['required', 'integer', 'exists:ESTACION,id_estacion'],
                'especialidad' => ['required', 'string', 'max:50'],
            ]),
            Rol::CAJERO => $request->validate([
                'codigo_caja' => ['required', 'string', 'max:20', Rule::unique('CAJERO', 'codigo_caja')->ignore($id, 'id_empleado')],
            ]),
            default => [],
        };

        return [$datos, $tipo, $extra];
    }

    private function guardarSubclase(int $idEmpleado, ?string $tipo, array $extra): void
    {
        foreach (['MESERO', 'COCINERO', 'CAJERO'] as $tabla) {
            DB::table($tabla)->where('id_empleado', $idEmpleado)->delete();
        }

        match ($tipo) {
            Rol::MESERO   => DB::table('MESERO')->insert(['id_empleado' => $idEmpleado, 'zona_asignada' => $extra['zona_asignada']]),
            Rol::COCINERO => DB::table('COCINERO')->insert(['id_empleado' => $idEmpleado, 'id_estacion' => $extra['id_estacion'], 'especialidad' => $extra['especialidad']]),
            Rol::CAJERO   => DB::table('CAJERO')->insert(['id_empleado' => $idEmpleado, 'codigo_caja' => $extra['codigo_caja']]),
            default       => null,
        };
    }
}
