<?php

namespace App\Http\Middleware;

use App\Services\AdminManagement\BasesDatosService;
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
     * Identificación del colegio (multi-tenant, el webhook no trae JWT):
     * - Ruta con {tenant} explícito (/pushNotification/{tenant} — terminal ya reconfigurada
     *   para avisar a la URL propia de su colegio, ver docs/segundo-colegio-hebreo-union.md)
     *   → la URL es la fuente de verdad, pero se valida igual que la IP de origen sea una
     *   terminal conocida DE ESE colegio puntual (la URL sola no autentica, cualquiera
     *   podría adivinarla y decir ser otro colegio).
     * - Ruta vieja sin {tenant} (terminales sin reconfigurar todavía) → cae al
     *   comportamiento anterior: resolverTenantPorIp() busca la IP entre TODOS los
     *   colegios configurados.
     *
     * En cuanto se identifica, se switchea `database.default` a la base de ese colegio
     * ANTES de que el controlador toque cualquier modelo (mismo mecanismo que
     * JwtFromCookie usa para el login), para que el employeeNo del evento se resuelva
     * contra la tabla `usuarios` correcta.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tenantUrl = $request->route('tenant');

        $tenant = $tenantUrl !== null
            ? $this->resolverPorTenantExplicito((string) $tenantUrl, $request->ip())
            : hikvisionattendanceService::resolverTenantPorIp($request->ip());

        if ($tenant === null) {
            Log::warning('[hikvision-notification] Petición rechazada: origen no registrado', [
                'ip' => $request->ip(),
                'tenant_url' => $tenantUrl,
            ]);

            abort(403, 'Origen no autorizado');
        }

        Config::set('database.default', $tenant);

        return $next($request);
    }

    private function resolverPorTenantExplicito(string $tenant, string $ip): ?string
    {
        if (!BasesDatosService::esConnectionValida($tenant)) {
            return null;
        }

        return in_array($ip, hikvisionattendanceService::hostsDeTenant($tenant), true) ? $tenant : null;
    }
}
