<?php

namespace App\Http\Controllers;

use App\Models\Empleado;
use App\Models\Estacion;
use App\Models\Rol;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmpleadoController extends Controller
{
    // Listado
    public function index()
    {
        $items = Empleado::with([
            'rol',
            'mesero',
            'cocinero.estacion',
            'cajero'
        ])
        ->orderBy('apellido')
        ->orderBy('nombre')
        ->paginate(15);

        return view('modules.admin.empleados.index', compact('items'));
    }

    // Formulario de creación
    public function create()
    {
        return view(
            'modules.admin.empleados.form',
            ['item' => null] + $this->catalogos()
        );
    }

    // Guardar nuevo empleado
    public function store(Request $request)
    {
        [$datos, $tipo, $extra] = $this->validar($request);

        DB::transaction(function () use ($request, $datos, $tipo, $extra) {
            $empleado = new Empleado();

            $empleado->forceFill([
                'nombre' => $datos['nombre'],
                'apellido' => $datos['apellido'],
                'correo' => $datos['correo'],
                'contrasena_hash' => Hash::make($datos['contrasena']),
                'id_rol' => $datos['id_rol'],
                'activo' => $request->boolean('activo'),
            ])->save();

            $this->guardarSubclase(
                $empleado->id_empleado,
                $tipo,
                $extra
            );
        });

        return redirect()
            ->route('admin.empleados.index')
            ->with('success', 'Empleado creado correctamente.');
    }

    // Formulario de edición
    public function edit($id)
    {
        $item = Empleado::with([
            'mesero',
            'cocinero',
            'cajero'
        ])->findOrFail($id);

        return view(
            'modules.admin.empleados.form',
            ['item' => $item] + $this->catalogos()
        );
    }

    // Actualizar empleado
    public function update(Request $request, $id)
    {
        $empleado = Empleado::findOrFail($id);

        [$datos, $tipo, $extra] = $this->validar($request, $id);

        DB::transaction(function () use (
            $request,
            $empleado,
            $datos,
            $tipo,
            $extra
        ) {
            $cambios = [
                'nombre' => $datos['nombre'],
                'apellido' => $datos['apellido'],
                'correo' => $datos['correo'],
                'id_rol' => $datos['id_rol'],
                'activo' => $request->boolean('activo'),
            ];

            if (!empty($datos['contrasena'])) {
                $cambios['contrasena_hash'] =
                    Hash::make($datos['contrasena']);
            }

            $empleado->forceFill($cambios)->save();

            $this->guardarSubclase(
                $empleado->id_empleado,
                $tipo,
                $extra
            );
        });

        return redirect()
            ->route('admin.empleados.index')
            ->with('success', 'Empleado actualizado correctamente.');
    }

    // Eliminar empleado
    public function destroy($id)
    {
        try {
            Empleado::findOrFail($id)->delete();

        } catch (QueryException $e) {
            return back()->with(
                'error',
                'No se puede eliminar: el empleado tiene pedidos, turnos o facturas asociados. Puedes desactivarlo editándolo.'
            );
        }

        return redirect()
            ->route('admin.empleados.index')
            ->with('success', 'Empleado eliminado correctamente.');
    }

    // Catálogos
    private function catalogos(): array
    {
        return [
            'roles' => Rol::orderBy('nombre_rol')
                ->pluck('nombre_rol', 'id_rol'),

            'estaciones' => Estacion::orderBy('nombre_estacion')
                ->pluck('nombre_estacion', 'id_estacion'),
        ];
    }

    // Identificar tipo de empleado
    private function tipoDeRol($idRol): ?string
    {
        $nombre = mb_strtolower(
            (string) Rol::whereKey($idRol)->value('nombre_rol')
        );

        foreach (['mesero', 'cocinero', 'cajero'] as $tipo) {
            if (str_contains($nombre, $tipo)) {
                return $tipo;
            }
        }

        return null;
    }

    // Validación
    private function validar(Request $request, $id = null): array
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:50'],

            'apellido' => ['required', 'string', 'max:50'],

            'correo' => [
                'required',
                'email',
                'max:100',
                Rule::unique('EMPLEADO', 'correo')
                    ->ignore($id, 'id_empleado'),
            ],

            'contrasena' => [
                $id ? 'nullable' : 'required',
                'string',
                'min:8',
                'max:100',
            ],

            'id_rol' => [
                'required',
                'integer',
                'exists:ROL,id_rol',
            ],
        ]);

        $tipo = $this->tipoDeRol($datos['id_rol']);

        $extra = match ($tipo) {
            'mesero' => $request->validate([
                'zona_asignada' => [
                    'required',
                    'string',
                    'max:50'
                ],
            ]),

            'cocinero' => $request->validate([
                'id_estacion' => [
                    'required',
                    'integer',
                    'exists:ESTACION,id_estacion'
                ],

                'especialidad' => [
                    'required',
                    'string',
                    'max:50'
                ],
            ]),

            'cajero' => $request->validate([
                'codigo_caja' => [
                    'required',
                    'string',
                    'max:20',
                    Rule::unique('CAJERO', 'codigo_caja')
                        ->ignore($id, 'id_empleado'),
                ],
            ]),

            default => [],
        };

        return [$datos, $tipo, $extra];
    }

    // Guardar subclase
    private function guardarSubclase(
        $idEmpleado,
        ?string $tipo,
        array $extra
    ): void {

        DB::table('MESERO')
            ->where('id_empleado', $idEmpleado)
            ->delete();

        DB::table('COCINERO')
            ->where('id_empleado', $idEmpleado)
            ->delete();

        DB::table('CAJERO')
            ->where('id_empleado', $idEmpleado)
            ->delete();

        match ($tipo) {
            'mesero' => DB::table('MESERO')->insert([
                'id_empleado' => $idEmpleado,
                'zona_asignada' => $extra['zona_asignada'],
            ]),

            'cocinero' => DB::table('COCINERO')->insert([
                'id_empleado' => $idEmpleado,
                'id_estacion' => $extra['id_estacion'],
                'especialidad' => $extra['especialidad'],
            ]),

            'cajero' => DB::table('CAJERO')->insert([
                'id_empleado' => $idEmpleado,
                'codigo_caja' => $extra['codigo_caja'],
            ]),

            default => null,
        };
    }
}
