<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtFromCookie
{
    public function handle(Request $request, Closure $next)
    {
        $token = null;

        if ($request->is('api/admisiones/*') || $request->is('api/tipos-documentos/*')) {

            $token = $request->cookie('admissions_token');

            Log::debug('JwtFromCookie: ruta admisiones/tipos-documentos', [
                'path' => $request->path(),
                'admissions_token_exists' => !is_null($token),
                'admissions_token_length' => $token ? strlen($token) : 0,
            ]);

        } else {

            $token = $request->cookie('token');

            Log::debug('JwtFromCookie: ruta general', [
                'path' => $request->path(),
                'token_exists' => !is_null($token),
            ]);
        }

        if ($token && ! $request->headers->has('Authorization')) {

            try {

                $payload = JWTAuth::setToken($token)->getPayload();

                Log::debug('JwtFromCookie: token válido, system=' . ($payload->get('system') ?? 'sin system'));

                $request->headers->set(
                    'Authorization',
                    'Bearer '.$token
                );

            } catch (\Exception $e) {

                Log::error(
                    'Token JWT inválido: '.$e->getMessage()
                );
            }
        } else {
            Log::debug('JwtFromCookie: no se setea Authorization', [
                'token_is_null' => is_null($token),
                'has_authorization' => $request->headers->has('Authorization'),
            ]);
        }

        return $next($request);
    }
}
