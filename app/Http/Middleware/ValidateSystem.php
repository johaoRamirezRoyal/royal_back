<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class ValidateSystem
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
     public function handle($request, Closure $next, $system)
    {
        $payload = JWTAuth::parseToken()->getPayload();

        if ($payload->get('system') !== $system) {

            return response()->json([
                'message' => 'Acceso no autorizado'
            ], 403);

        }

        return $next($request);
    }
}
