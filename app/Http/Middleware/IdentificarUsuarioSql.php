<?php

namespace App\Http\Middleware;

use App\Support\SesionSql;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Le dice a SQL Server quién hace la petición (sp_set_session_context) para que el
 * trigger trg_pedido_auditoria registre al empleado real y no la cuenta de conexión
 * compartida de la aplicación. En los triggers se lee con SESSION_CONTEXT(N'id_empleado').
 */
class IdentificarUsuarioSql
{
    public function handle(Request $request, Closure $next): Response
    {
        $empleado = $request->user();

        if ($empleado) {
            SesionSql::establecer('id_empleado', (int) $empleado->id_empleado);
            SesionSql::establecer('usuario_app', 'app:' . $empleado->correo);
        }

        return $next($request);
    }
}
