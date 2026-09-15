<?php

namespace App\Http\Middleware;

use App\Models\Instituciones\Institucion;
use App\Services\AdminManagement\BasesDatosService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Guarda de las rutas protegidas de instituciones. Deliberadamente NO usa JWT ni el guard
 * `auth:api` (atado a la tabla `usuarios`, ver `config/auth.php`) — una institución no es un
 * usuario. La sesión es un token opaco respaldado en caché (`institucion_session_{token}`),
 * mismo patrón que ya usan `verificacion_{token}`/`register_session_{token}` en
 * AdmissionsController para el flujo de acudiente.
 */
class EnsureInstitucionSession
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->cookie('institucion_token');

        if (! $token) {
            return response()->json(['active' => false, 'message' => 'No autenticado'], 401);
        }

        $data = Cache::get("institucion_session_{$token}");

        if (! $data) {
            return response()->json(['active' => false, 'message' => 'Sesión expirada'], 401);
        }

        // Multi-tenant: switchea `database.default` a la base del colegio bajo el que se
        // otorgó ESTA sesión (ver InstitucionController::otorgarSesion) ANTES de que
        // Institucion::find toque la DB — se lee de la sesión cacheada, no del header
        // X-Colegio-Slug de esta request, para no confiar en la URL actual (una cookie
        // institucion_token vieja de otro colegio en la misma pestaña/dominio no debe
        // poder leer datos de un tenant distinto al que realmente inició sesión). 'mysql'
        // si falta el campo (sesiones cacheadas antes de este cambio).
        $connection = $data['connection'] ?? 'mysql';

        if (BasesDatosService::esConnectionValida($connection)) {
            Config::set('database.default', $connection);
        }

        // Se revisa `activo` en cada request, no solo al hacer login — si un admin
        // deshabilita la institución mientras ya está logueada, la sesión (caché, hasta
        // 12h de vigencia) debe dejar de servir de inmediato, no hasta que expire sola.
        $institucion = Institucion::find($data['id']);

        if (! $institucion || ! $institucion->activo) {
            Cache::forget("institucion_session_{$token}");

            return response()->json(['active' => false, 'message' => 'Cuenta deshabilitada'], 401);
        }

        $request->attributes->set('institucion_id', $institucion->id);
        // ?? null: sesiones creadas antes de este campo (caché aún vigente) no lo tienen.
        $request->attributes->set('institucion_session_expires_at', $data['expires_at'] ?? null);

        return $next($request);
    }
}
