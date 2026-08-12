<?php

namespace App\Http\Middleware;

use App\Models\LogActividad;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Audita las peticiones que modifican datos (POST/PUT/PATCH/DELETE): quién,
 * qué endpoint, con qué resultado y en cuánto tiempo. Se registra después de
 * $next($request) para que el usuario ya esté resuelto por el middleware de
 * autenticación (auth:api), sin importar el orden de registro entre ambos.
 */
class LogActividadMiddleware
{
    private const METODOS_AUDITADOS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next)
    {
        if (!in_array($request->method(), self::METODOS_AUDITADOS, true)) {
            return $next($request);
        }

        $inicio = microtime(true);
        $response = $next($request);

        try {
            LogActividad::create([
                'id_user' => auth()->id(),
                'ip' => $request->ip(),
                'metodo' => $request->method(),
                'ruta' => $request->path(),
                'status_code' => $response->getStatusCode(),
                'duracion_ms' => (int) ((microtime(true) - $inicio) * 1000),
                'fechareg' => now(),
            ]);
        } catch (\Exception $e) {
            // No se debe romper la petición si falla la auditoría.
            Log::error('Error al registrar log de actividad', ['error' => $e->getMessage()]);
        }

        return $response;
    }
}
