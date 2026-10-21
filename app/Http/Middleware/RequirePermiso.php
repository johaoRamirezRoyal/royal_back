<?php

namespace App\Http\Middleware;

use App\Services\Usuarios\PermisosPerfil;
use App\Support\Permiso;
use Closure;
use Illuminate\Http\Request;

/**
 * Puerta de entrada por permisos, declarada en la ruta: deja pasar si el perfil tiene
 * CUALQUIERA de las opciones, o si su perfil está en la lista `perfil:N` (autoservicio, p. ej.
 * docentes). Fail-closed: sin usuario o sin nada de lo anterior, 403.
 *
 *   ->middleware('permiso:133,134,135,99,perfil:3,perfil:14')
 *
 * Solo decide si entra. Lo que puede hacer adentro (qué datos ve, qué acciones) sigue en el
 * controller/service, leyendo las mismas opciones con PermisosPerfil (sin otra consulta).
 * Va dentro del grupo autenticado (`auth:api`).
 */
class RequirePermiso
{
    public function handle(Request $request, Closure $next, string ...$reglas)
    {
        $perfil = $request->user()?->perfil;
        $opciones = [];
        $perfiles = [];

        foreach ($reglas as $regla) {
            str_starts_with($regla, 'perfil:')
                ? $perfiles[] = (int) substr($regla, 7)
                : $opciones[] = (int) $regla;
        }

        // Super Admin pasa siempre, también en reglas solo de perfil (`perfil:N` sin el 1).
        $permitido = $perfil !== null
            && ((int) $perfil === Permiso::PERFIL_SUPER_ADMIN
                || in_array((int) $perfil, $perfiles, true)
                || PermisosPerfil::tiene($request, ...$opciones));

        if (! $permitido) {
            return response()->json([
                'error' => true,
                'error_type' => 'logic',
                'error_code' => 403,
                'message' => 'No tienes permiso para acceder a este recurso.',
            ], 403);
        }

        return $next($request);
    }
}
