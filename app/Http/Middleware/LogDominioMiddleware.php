<?php

namespace App\Http\Middleware;

use App\Models\LogDominio;
use App\Services\branding\MarcaDominioService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Registra TODA petición (a diferencia de LogActividadMiddleware, que solo audita
 * escrituras) con el dominio de correo del usuario autenticado — para ver qué dominios
 * están usando qué páginas/endpoints, no para auditoría de cambios. Se registra después de
 * $next($request) por la misma razón que LogActividadMiddleware: que el usuario ya esté
 * resuelto por el middleware de autenticación, sin importar el orden de registro entre ambos.
 */
class LogDominioMiddleware
{
    public function __construct(private MarcaDominioService $marcaDominioService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $inicio = microtime(true);
        $response = $next($request);

        try {
            LogDominio::create([
                'dominio' => $this->marcaDominioService->dominioDeCorreo($request->user()?->correo),
                'ip' => $request->ip(),
                'id_user' => auth()->id(),
                'metodo' => $request->method(),
                'ruta' => $request->path(),
                'status_code' => $response->getStatusCode(),
                'duracion_ms' => (int) ((microtime(true) - $inicio) * 1000),
                'fechareg' => now(),
            ]);
        } catch (\Exception $e) {
            // No se debe romper la petición si falla el registro.
            Log::error('Error al registrar log de dominio', ['error' => $e->getMessage()]);
        }

        return $response;
    }
}
