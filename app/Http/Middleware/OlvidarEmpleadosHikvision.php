<?php

namespace App\Http\Middleware;

use App\Services\Hikvisionattendance\hikvisionattendanceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tras cualquier escritura en los terminales (POST/PUT/DELETE bajo /api/hikvision:
 * enrolar huella/tarjeta/rostro/contraseña, eliminar, desactivar, renombrar terminal...),
 * olvida el listado cacheado de GET /hikvision/getList para que la próxima consulta
 * traiga el estado real. Va en las rutas y no en cada método del servicio para no
 * depender de que cada escritura nueva se acuerde de invalidar.
 */
class OlvidarEmpleadosHikvision
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!$request->isMethodSafe()) {
            app(hikvisionattendanceService::class)->olvidarEmpleadosRegistrados();
        }

        return $response;
    }
}
