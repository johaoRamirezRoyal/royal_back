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

        if ($request->is('api/admisiones/*')) {

            $token = $request->cookie('admissions_token');

        } else {

            $token = $request->cookie('token');

        }

        if ($token && ! $request->headers->has('Authorization')) {

            try {

                JWTAuth::setToken($token)->getPayload();

                $request->headers->set(
                    'Authorization',
                    'Bearer '.$token
                );

            } catch (\Exception $e) {

                Log::error(
                    'Token JWT inválido: '.$e->getMessage()
                );
            }
        }

        return $next($request);
    }
}
