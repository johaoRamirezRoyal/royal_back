<?php

namespace App\Services\Sami;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SamiSsoService
{
    public function iniciarSesion(int $userId, string $username, string $password): void
    {
        try {
            $response = Http::asForm()
                ->withOptions(['allow_redirects' => false])
                ->timeout(5)
                ->post(
                    config('services.sami.base_url') . config('services.sami.login_path'),
                    ['user' => $username, 'pass' => $password]
                );

            $status = $response->status();
            $location = $response->header('Location');
            $setCookie = $response->header('Set-Cookie');

            Log::debug('SamiSso: respuesta de SAMI', [
                'user_id' => $userId,
                'status' => $status,
                'location' => $location,
            ]);

            // Solo considerar éxito si SAMI redirige a "inicio" (no a "login?er=")
            if ($status !== 302 || !$location || str_contains($location, 'er=') || !str_contains($location, 'inicio')) {
                Log::warning('SamiSso: login rechazado por SAMI', [
                    'user_id' => $userId,
                    'status' => $status,
                    'location' => $location,
                ]);
                return;
            }

            // Pueden venir múltiples Set-Cookie; buscar PHPSESSID en todos
            $sessionId = null;
            foreach ((array) $setCookie as $cookie) {
                if (preg_match('/' . config('services.sami.cookie_name') . '=([^;]+)/', $cookie, $m)) {
                    $sessionId = $m[1];
                    break;
                }
            }

            if (!$sessionId) {
                Log::warning('SamiSso: no se encontró PHPSESSID en headers', [
                    'user_id' => $userId,
                ]);
                return;
            }

            Cache::put(
                $this->cacheKey($userId),
                Crypt::encryptString($sessionId),
                now()->addMinutes((int) config('services.sami.cache_ttl'))
            );

            Log::info('SamiSso: sesión creada', ['user_id' => $userId]);

        } catch (\Exception $e) {
            Log::error('SamiSso: error al iniciar sesión en SAMI', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function obtenerSesion(int $userId): ?string
    {
        $encrypted = Cache::get($this->cacheKey($userId));

        if (!$encrypted) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Exception $e) {
            Log::error('SamiSso: error al descifrar sesión', ['user_id' => $userId]);
            return null;
        }
    }

    public function olvidarSesion(int $userId): void
    {
        Cache::forget($this->cacheKey($userId));
    }

    private function cacheKey(int $userId): string
    {
        return 'sami_session_' . $userId;
    }
}
