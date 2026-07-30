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

        if ($request->is('api/compartido/*')) {

            $token = $request->cookie('admissions_token') ?? $request->cookie('token');

        } elseif ($request->is('api/admisiones/*')) {

            $token = $request->cookie('admissions_token');

        } else {

            $token = $request->cookie('token');

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
