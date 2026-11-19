<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Máximo de dispositivos con la misma cuenta abierta (login normal del sistema general; las
 * sesiones por llave maestra van aparte, ver LlaveMaestraService). Cada token lleva un claim
 * `sid`; al pasar del máximo se cierra la sesión más antigua y JwtFromCookie corta su token.
 *
 * Vive en caché, no en BD (el deploy por FTP no corre migraciones). Solo se rechaza un sid que
 * esté en `cerradas`: si la caché se limpia nadie pierde la sesión, solo se reinicia el conteo.
 */
class SesionesActivasService
{
    public const MAXIMO = 4;

    public function registrar(string $connection, int $idUser, string $sid): void
    {
        $this->modificar($connection, $idUser, function (array $estado) use ($sid) {
            $estado['activas'][$sid] = now()->getTimestamp();

            // Las claves quedan en orden de inicio: la primera es la más antigua.
            while (count($estado['activas']) > self::MAXIMO) {
                $masAntigua = array_key_first($estado['activas']);
                unset($estado['activas'][$masAntigua]);
                $estado['cerradas'][$masAntigua] = now()->getTimestamp();
            }

            return $estado;
        });
    }

    /** Logout: libera el lugar del dispositivo. */
    public function quitar(string $connection, int $idUser, string $sid): void
    {
        $this->modificar($connection, $idUser, function (array $estado) use ($sid) {
            unset($estado['activas'][$sid]);

            return $estado;
        });
    }

    /** true si esta sesión se cerró por entrar en otro dispositivo de más. */
    public function cerrada(string $connection, int $idUser, string $sid): bool
    {
        return isset(Cache::get($this->clave($connection, $idUser))['cerradas'][$sid]);
    }

    private function modificar(string $connection, int $idUser, callable $cambio): void
    {
        $clave = $this->clave($connection, $idUser);

        try {
            // Lock: dos logins a la vez de la misma cuenta no se pisan la lista.
            Cache::lock("{$clave}:lock", 5)->block(3, function () use ($clave, $cambio) {
                $ttl = (int) config('jwt.ttl');
                $limite = now()->subMinutes($ttl)->getTimestamp();
                $vigentes = fn (array $sids) => array_filter($sids, fn (int $desde) => $desde > $limite);

                $estado = Cache::get($clave) ?? [];
                $estado = $cambio([
                    'activas' => $vigentes($estado['activas'] ?? []),
                    'cerradas' => $vigentes($estado['cerradas'] ?? []),
                ]);

                // Todo token de la lista vence antes que esto: el JWT dura `ttl` desde su login.
                Cache::put($clave, $estado, now()->addMinutes($ttl));
            });
        } catch (\Throwable $e) {
            // No se bloquea el login por esto (lock ocupado, o un store sin locks, p. ej. database
            // sin tabla cache_locks): esa sesión solo queda sin contar.
            Log::warning('No se pudo registrar la sesión activa', [
                'id_user' => $idUser,
                'connection' => $connection,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function clave(string $connection, int $idUser): string
    {
        return "sesiones_activas:{$connection}:{$idUser}";
    }
}
