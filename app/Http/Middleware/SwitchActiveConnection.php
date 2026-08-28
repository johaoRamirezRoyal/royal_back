<?php

namespace App\Http\Middleware;

use App\Services\AdminManagement\BasesDatosService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Infraestructura para el multi-tenant a futuro: un Super Admin de la allowlist de
 * administración (config('adminmanagement.emails')) puede elegir, vía
 * `/api/admin-management/bases-datos/conexion-activa` (BasesDatosController), qué
 * connection usar como `database.default` durante sus propias peticiones — todo modelo que
 * NO declare `$connection` explícito (la inmensa mayoría: Usuario, Inventario, académico,
 * ...) empieza a resolver contra esa connection en su lugar. Los modelos de
 * `admin_management` (MarcaDominio, LogDominio, BaseDatosNombre, ConexionActiva) declaran
 * su connection explícita y NO se ven afectados — es a propósito, son tablas transversales,
 * no datos de un tenant.
 *
 * Solo afecta la petición actual (Config::set no persiste entre requests en PHP-FPM/
 * artisan serve) y solo la de ESE Super Admin — nunca la de otros usuarios concurrentes.
 * Hoy solo existen `mysql` y `admin_management` como connections reales, y esta última no
 * tiene tablas de negocio: elegirla rompe la mayoría de los módulos hasta que existan
 * bases operativas reales por tenant — comportamiento esperado, no un bug de este
 * middleware.
 */
class SwitchActiveConnection
{
    private const PERFIL_SUPER_ADMIN = 1;

    public function __construct(private BasesDatosService $basesDatosService)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (
            $user
            && $user->perfil === self::PERFIL_SUPER_ADMIN
            && RestrictToAdminEmails::esCorreoPermitido($user->correo)
        ) {
            $connection = $this->basesDatosService->conexionActiva($user->id_user);

            if ($connection !== 'mysql') {
                Config::set('database.default', $connection);
            }
        }

        return $next($request);
    }
}
