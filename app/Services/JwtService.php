<?php

namespace App\Services;

use App\Models\Usuarios\Usuario;
use App\Services\Auth\SesionesActivasService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtService
{
    /** `$extraClaims` se mergea tal cual dentro del payload — usado hoy solo por
     * AuthController::redeemMasterKey para marcar `via_llave_maestra`/`generado_por` en
     * una sesión abierta por llave de soporte en vez de contraseña normal. */
    public function generateToken(Authenticatable $user, string $connection = 'mysql', array $extraClaims = []): string
    {
        /** @var Usuario $user */

        $user->loadMissing('perfilRelacion');

        // Login normal: cuenta para el máximo de dispositivos (ver SesionesActivasService).
        // La llave maestra tiene su propio control (LlaveMaestraService) y no ocupa lugar.
        $sid = empty($extraClaims['via_llave_maestra']) ? Str::random(32) : null;

        // tymon guarda en su factory compartida los claims de todo token que lee en la petición
        // (JwtFromCookie lee la cookie anterior): sin vaciarla, el token nuevo heredaba
        // `via_llave_maestra` de una sesión vieja y quedaba cerrado desde el primer request.
        JWTAuth::factory()->emptyClaims();

        $token = JWTAuth::claims(array_merge([
            'active' => true,
            'system' => 'general',
            'db_connection' => $connection,
            'user_id' => $user->id_user,
            'nombre' => $user->nombre,
            'apellido' => $user->apellido,
            'correo' => $user->correo,
            'perfil' => $user->perfilRelacion?->nombre ?? $user->perfil,
        ], $sid ? ['sid' => $sid] : [], $extraClaims))->fromUser($user);

        if ($sid) {
            app(SesionesActivasService::class)->registrar($connection, (int) $user->id_user, $sid);
            // Todo login del sistema general pasa por aquí (contraseña, código de equipo nuevo,
            // Google). La llave maestra no: es soporte entrando, no el usuario. Antes solo
            // Admisiones la guardaba y el módulo Acudientes mostraba «Nunca» a quien sí entraba.
            Usuario::on($connection)->whereKey($user->id_user)->update(['ultima_conexion' => now(), 'ultima_ip' => request()->ip()]);
        }

        return $token;
    }

    /** `$connection` viene del colegio resuelto por ResolveColegioAdmision para esta
     * petición de login/registro (ver AdmissionsController) — el resto de la sesión del
     * acudiente (/api/admisiones/*) sigue apuntando a esa misma connection vía este claim,
     * leído por JwtFromCookie en cada petición posterior. 'mysql' (Royal School) si no
     * viene ninguna, mismo default que JwtFromCookie usa para un token sin el claim. */
    public function generateAdmissionsToken(Authenticatable $user, string $connection = 'mysql'): string
    {
        JWTAuth::factory()->emptyClaims(); // ver generateToken

        return JWTAuth::claims([
            'active' => true,
            'system' => 'admissions',
            'db_connection' => $connection,
            'user_id' => $user->id_user,
            'nombre' => $user->nombre,
            'apellido' => $user->apellido,
            'correo' => $user->correo,
            'perfil' => $user->perfilRelacion?->nombre ?? $user->perfil,
            ])->fromUser($user);
    }

    public function refreshToken(string $token): string
    {
        return JWTAuth::setToken($token)->refresh();
    }

    public function invalidateToken(string $token): void
    {
        JWTAuth::setToken($token)->invalidate();
    }

    public function getPayload(string $token): array
    {
        return JWTAuth::setToken($token)->getPayload()->toArray();
    }

    public function authenticate(): ?Authenticatable
    {
        return JWTAuth::parseToken()->authenticate();
    }
}
