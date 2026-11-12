<?php

namespace App\Http\Middleware;

use App\Enums\TipoPerfil;
use App\Services\Usuarios\PermisosPerfil;
use App\Support\Permiso;
use Closure;
use Illuminate\Http\Request;

/**
 * Puerta de entrada por permisos, declarada en la ruta: deja pasar si el perfil tiene
 * CUALQUIERA de las opciones, si su perfil está en la lista `perfil:N`, o si su perfil es de
 * un tipo de la lista `tipo:X` (perfiles.tipo; autoservicio, p. ej. docentes). Fail-closed: sin
 * usuario o sin nada de lo anterior, 403.
 *
 *   ->middleware('permiso:133,134,135,99,tipo:DOCENTE')
 *
 * Solo decide si entra. Lo que puede hacer adentro (qué datos ve, qué acciones) sigue en el
 * controller/service, leyendo las mismas opciones con PermisosPerfil (sin otra consulta).
 * Va dentro del grupo autenticado (`auth:api`).
 */
class RequirePermiso
{
    public function handle(Request $request, Closure $next, string ...$reglas)
    {
        $usuario = $request->user();
        $perfil = $usuario?->perfil;
        $opciones = [];
        $perfiles = [];
        $tipos = [];

        foreach ($reglas as $regla) {
            match (true) {
                str_starts_with($regla, 'perfil:') => $perfiles[] = (int) substr($regla, 7),
                str_starts_with($regla, 'tipo:') => $tipos[] = TipoPerfil::from(substr($regla, 5)),
                default => $opciones[] = (int) $regla,
            };
        }

        // Super Admin pasa siempre, también en reglas solo de perfil o de tipo.
        $permitido = $perfil !== null
            && ((int) $perfil === Permiso::PERFIL_SUPER_ADMIN
                || in_array((int) $perfil, $perfiles, true)
                || ($tipos && $usuario->esTipo(...$tipos))
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
