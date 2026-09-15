<?php

namespace App\Http\Middleware;

use App\Services\branding\ColegioAdmisionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Multi-tenant por URL para el módulo público de admisiones (`/api/admissions/*`, ver
 * routes/api.php): el frontend identifica el colegio con el slug de la URL
 * (`/{slug}/admissions`, ver router de React) y lo manda en el header `X-Colegio-Slug` en
 * cada petición a este grupo (ver apiFetch/admissionsTenant.ts) — acá se resuelve ese slug
 * a su connection real y se switchea `database.default` ANTES de que AdmissionsController
 * toque cualquier modelo de negocio, mismo requisito que JwtFromCookie/SwitchActiveConnection.
 *
 * Fail-closed a propósito (ver "Everything here is fail-closed" en CLAUDE.md): sin header
 * o con un slug que no resuelve a un colegio activo, se corta acá con 400/404 en vez de
 * caer silenciosamente a `mysql` — evitar que una petición admisiones de un colegio B
 * termine escribiendo en la base del colegio A por un slug ausente/mal tipeado.
 *
 * No cubre `/api/admisiones/*` (español, autenticado): ese tramo ya resuelve la connection
 * desde el claim `db_connection` del JWT de admisiones (ver JwtService::generateAdmissionsToken
 * y JwtFromCookie), fijado en el momento del login con la connection que este middleware
 * resolvió entonces.
 */
class ResolveColegioAdmision
{
    public function __construct(private ColegioAdmisionService $service)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $slug = $request->header('X-Colegio-Slug');

        if (!$slug) {
            return response()->json(['error' => true, 'message' => 'Falta identificar el colegio de la solicitud.'], 400);
        }

        $colegio = $this->service->resolverPorSlug($slug);

        if (!$colegio) {
            return response()->json(['error' => true, 'message' => 'Colegio no encontrado o inactivo.'], 404);
        }

        Config::set('database.default', $colegio->connection);

        // Leído por AdmissionsController al emitir el JWT de admisiones (login/registro),
        // para que ese token cargue la misma connection y la sesión completa del
        // acudiente (incluido /api/admisiones/* después del login) siga apuntando al
        // colegio correcto — ver JwtService::generateAdmissionsToken.
        $request->attributes->set('colegio_admision_connection', $colegio->connection);
        $request->attributes->set('colegio_admision_slug', $colegio->slug);

        return $next($request);
    }
}
