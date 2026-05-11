<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtFromCookie
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->cookie('token');

        if ($token && ! $request->headers->has('Authorization')) {
            try {
                $decrypted = Crypt::decryptString($token);

                $jwt = str_contains($decrypted, '|')
                    ? explode('|', $decrypted)[1]
                    : $decrypted;

            } catch (\Exception $e) {
                Log::error('Error desencriptando: '.$e->getMessage());

                return $next($request);
            }

            // 👇 Separado del try de desencriptación
            try {
                $payload = JWTAuth::setToken($jwt)->getPayload();

                if ($payload->get('system') === 'admissions') {
                    Log::warning('Token de admisiones bloqueado');

                    return response()->json(['message' => 'Token no válido para este sistema'], 401);
                }

                $request->headers->set('Authorization', 'Bearer '.$jwt);
                Log::info('JWT inyectado:', ['jwt' => $jwt]);

            } catch (\Exception $e) {
                Log::error('Token JWT inválido: '.$e->getMessage());
                // No inyecta el header, continúa sin autenticación
            }
        }

        return $next($request);
    }
}
