<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Los módulos de administración transversal (marcas por dominio, bases de datos vinculadas,
 * logs por dominio, elegir la connection activa — todos en `admin_management`) solo son
 * alcanzables para los correos exactos de `config('adminmanagement.emails')`
 * (ADMIN_MANAGEMENT_EMAILS) — allowlist fija, no un dominio de correo. Fail-closed: un
 * correo fuera de la lista (o sin correo) siempre devuelve 403, sin importar el perfil del
 * usuario — este chequeo va antes/además de PERFILES_PERMITIDOS en cada controller, no lo
 * reemplaza. Corre dentro del grupo ya autenticado, así que `$request->user()` siempre existe.
 */
class RestrictToAdminEmails
{
    public function handle(Request $request, Closure $next)
    {
        if (!self::esCorreoPermitido($request->user()->correo)) {
            abort(response()->json([
                'error' => true,
                'error_type' => 'logic',
                'error_code' => 403,
                'message' => 'Este recurso solo es accesible para correos autorizados de administración.',
            ], 403));
        }

        return $next($request);
    }

    /** Reutilizado por SwitchActiveConnection — mismo chequeo, no un dominio. */
    public static function esCorreoPermitido(?string $correo): bool
    {
        if (!$correo) {
            return false;
        }

        return in_array(mb_strtolower(trim($correo)), config('adminmanagement.emails'), true);
    }
}
