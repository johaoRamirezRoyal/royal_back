<?php

namespace App\Services\Auth;

use App\Models\Usuarios\DispositivoConfiable;
use App\Models\Usuarios\Usuario;
use App\Services\AdminManagement\BasesDatosService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthServices
{
    /**
     * Login multi-tenant: el identificador es el `correo`, y el usuario puede vivir en
     * cualquiera de las bases con tabla `usuarios` (ver
     * BasesDatosService::connectionsConUsuarios). NO se acepta `user` (username) ni
     * `documento` como identificador — el login por username fue deshabilitado a
     * propósito para forzar correo institucional único; el documento tampoco es único a
     * través de tenants distintos (una misma persona con cuenta en dos colegios comparte
     * documento), así que buscar por documento sería ambiguo y silenciosamente entraría al
     * tenant equivocado. Se asume que `correo` sí es único a través de TODAS las bases —
     * apenas se encuentra una fila que matchea el identificador en una connection, esa es la
     * única candidata: si la contraseña o el estado fallan ahí, no se sigue buscando en
     * otras bases.
     *
     * @return array{usuario: Usuario, connection: string}|null
     */
    public function resolverUsuarioMultiTenant(string $identificador, string $password): ?array
    {
        foreach (BasesDatosService::connectionsConUsuarios() as $connection) {
            $usuario = Usuario::on($connection)
                ->where('correo', $identificador)
                ->first();

            if (!$usuario) {
                continue;
            }

            if (!Hash::check($password, $usuario->pass) || $usuario->estado !== 'activo') {
                return null;
            }

            return ['usuario' => $usuario, 'connection' => $connection];
        }

        return null;
    }

    public function validarLoginUser($usuario) {
        return Usuario::where('user', $usuario)
            ->where('estado', 'activo')
            ->first();
    }

    public function registrarUsuario(Array $usuario){
        try {
            
            $usuario = Usuario::create($usuario);
            
            return [
                'success' => true,
                'data' => $usuario
            ];

        } catch (\Exception $e) {
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
            
        }
    }

    /**
     * true si `$rawToken` (el valor crudo de la cookie `device_token`) ya está registrado
     * para este usuario en este tenant — solo se guarda su hash, nunca el token en claro.
     * Un hit actualiza `ultima_vez`/`ip_registro` para tener un rastro de uso real, no solo
     * de creación.
     */
    public function dispositivoEsConfiable(string $connection, int $idUser, string $rawToken): bool
    {
        $hash = hash('sha256', $rawToken);

        $dispositivo = DispositivoConfiable::on($connection)
            ->where('id_user', $idUser)
            ->where('token_hash', $hash)
            ->first();

        if (!$dispositivo) {
            return false;
        }

        $dispositivo->update([
            'ultima_vez' => now(),
            'ip_registro' => request()->ip(),
        ]);

        return true;
    }

    /** Etiqueta corta para el correo de verificación — heurística simple, no un parser
     * completo de User-Agent (ver AuthController::verifyLoginOtp). */
    public function nombreDispositivoDesdeUserAgent(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        $navegador = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => null,
        };

        $sistema = match (true) {
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        if (!$navegador && !$sistema) {
            return null;
        }

        return trim(($navegador ?? 'Navegador') . ($sistema ? " en {$sistema}" : ''));
    }

    /** Genera un token de dispositivo nuevo (crudo) y lo registra ya hasheado. El valor
     * crudo devuelto es el que se guarda en la cookie `device_token` — nunca persiste en BD. */
    public function registrarDispositivoConfiable(string $connection, int $idUser, string $ip, ?string $userAgent): string
    {
        $rawToken = Str::random(64);

        DispositivoConfiable::on($connection)->create([
            'id_user' => $idUser,
            'token_hash' => hash('sha256', $rawToken),
            'nombre_dispositivo' => $this->nombreDispositivoDesdeUserAgent($userAgent),
            'ip_registro' => $ip,
            'ultima_vez' => now(),
            'created_at' => now(),
        ]);

        return $rawToken;
    }

    public function buscarUsuarioPorEmail($email){
        try{
            $usuario = Usuario::where('correo', $email)->first();
            if (!$usuario){
                return [
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ];
            }

            return [
                'success' => true,
                'data' => $usuario
            ];
        }catch(\Exception $e){
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}