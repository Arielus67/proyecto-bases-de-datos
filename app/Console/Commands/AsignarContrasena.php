<?php

namespace App\Console\Commands;

use App\Models\Empleado;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Asigna contraseña a un empleado y lo activa. Pide la contraseña en modo oculto
 * para que no quede en el historial de la terminal.
 */
class AsignarContrasena extends Command
{
    protected $signature = 'restaurante:contrasena {correo : Correo del empleado}';

    protected $description = 'Asigna una contraseña a un empleado y activa su cuenta';

    public function handle(): int
    {
        $correo = (string) $this->argument('correo');

        $empleado = Empleado::where('correo', $correo)->first();

        if (! $empleado) {
            $this->error('No existe un empleado con ese correo.');

            return self::FAILURE;
        }

        $contrasena = (string) $this->secret('Nueva contraseña (mínimo 12 caracteres)');
        $confirmacion = (string) $this->secret('Repita la contraseña');

        $validador = Validator::make(
            ['contrasena' => $contrasena, 'confirmacion' => $confirmacion],
            ['contrasena' => ['required', 'string', 'min:12', 'same:confirmacion']]
        );

        if ($validador->fails()) {
            $this->error($validador->errors()->first('contrasena'));

            return self::FAILURE;
        }

        $empleado->forceFill([
            'contrasena_hash' => Hash::make($contrasena),
            'activo' => 1,
        ])->save();

        $this->info('Contraseña asignada y cuenta activada para ' . $correo . '.');

        return self::SUCCESS;
    }
}
