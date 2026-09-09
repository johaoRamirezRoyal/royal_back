<?php

namespace App\Services;

use App\Models\Usuarios\Usuario;
use Illuminate\Contracts\Auth\Authenticatable;
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

        return JWTAuth::claims(array_merge([
            'active' => true,
            'system' => 'general',
            'db_connection' => $connection,
            'user_id' => $user->id_user,
            'nombre' => $user->nombre,
            'apellido' => $user->apellido,
            'correo' => $user->correo,
            'perfil' => $user->perfilRelacion?->nombre ?? $user->perfil,
        ], $extraClaims))->fromUser($user);
    }

    public function generateAdmissionsToken(Authenticatable $user): string
    {
        return JWTAuth::claims([
            'active' => true,
            'system' => 'admissions',
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
