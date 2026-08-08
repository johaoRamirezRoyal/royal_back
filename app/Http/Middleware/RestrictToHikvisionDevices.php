<?php

namespace App\Http\Middleware;

use App\Services\Hikvisionattendance\hikvisionattendanceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RestrictToHikvisionDevices
{
    public function __construct(private hikvisionattendanceService $hikvisionService) {}

    /**
     * Solo deja pasar peticiones cuya IP de origen sea la de un terminal Hikvision
     * configurado en .env (HIKVISION_HOST / HIKVISION_HOSTS) — el resto (internet
     * abierto, ya que /pushNotification no lleva auth:api por ser un webhook del
     * dispositivo) se rechaza antes de tocar el controlador.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!in_array($request->ip(), $this->hikvisionService->deviceHosts(), true)) {
            Log::warning('[hikvision-notification] Petición rechazada: origen no registrado', [
                'ip' => $request->ip(),
            ]);

            abort(403, 'Origen no autorizado');
        }

        return $next($request);
    }
}
