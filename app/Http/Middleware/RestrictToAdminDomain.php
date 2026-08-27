<?php

namespace App\Http\Middleware;

use App\Services\branding\MarcaDominioService;
use Closure;
use Illuminate\Http\Request;

/**
 * Los módulos de administración transversal (marcas por dominio, bases de datos vinculadas,
 * logs por dominio — todos en `admin_management`) solo son alcanzables para usuarios cuyo
 * correo pertenece al dominio especial de gestión (config('adminmanagement.domain'),
 * ADMIN_MANAGEMENT_DOMAIN) — no depende de por qué Host/URL entra la petición. Fail-closed:
 * un correo sin ese dominio (o sin correo) siempre devuelve 403, sin importar el perfil del
 * usuario — este chequeo va antes/además de PERFILES_PERMITIDOS en cada controller, no lo
 * reemplaza. Corre dentro del grupo ya autenticado, así que `$request->user()` siempre existe.
 */
class RestrictToAdminDomain
{
    public function __construct(private MarcaDominioService $marcaDominioService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $dominio = $this->marcaDominioService->dominioDeCorreo($request->user()->correo);

        if ($dominio !== config('adminmanagement.domain')) {
            abort(response()->json([
                'error' => true,
                'error_type' => 'logic',
                'error_code' => 403,
                'message' => 'Este recurso solo es accesible para usuarios del dominio de administración.',
            ], 403));
        }

        return $next($request);
    }
}
