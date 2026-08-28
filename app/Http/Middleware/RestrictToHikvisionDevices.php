<?php

namespace App\Http\Middleware;

use App\Services\Hikvisionattendance\hikvisionattendanceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RestrictToHikvisionDevices
{
    /**
     * Solo deja pasar peticiones cuya IP de origen sea la de una terminal Hikvision
     * configurada (.env, por colegio — ver config/services.php) — el resto (internet
     * abierto, ya que /pushNotification no lleva auth:api por ser un webhook del
     * dispositivo) se rechaza antes de tocar el controlador.
     *
     * Multi-tenant: el webhook no trae JWT, así que no hay forma de saber a qué colegio
     * pertenece hasta identificar la IP — resolverTenantPorIp() busca entre las
     * terminales de TODOS los colegios. En cuanto se identifica, se switchea
     * `database.default` a la base de ese colegio ANTES de que el controlador toque
     * cualquier modelo (mismo mecanismo que JwtFromCookie usa para el login), para que
     * el employeeNo del evento se resuelva contra la tabla `usuarios` correcta.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = hikvisionattendanceService::resolverTenantPorIp($request->ip());

        if ($tenant === null) {
            Log::warning('[hikvision-notification] Petición rechazada: origen no registrado', [
                'ip' => $request->ip(),
            ]);

            abort(403, 'Origen no autorizado');
        }

        Config::set('database.default', $tenant);

        return $next($request);
    }
}
