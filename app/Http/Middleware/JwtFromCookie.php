<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class JwtFromCookie
{
public function handle(Request $request, Closure $next)
{
    $token = $request->cookie('token');

    if ($token && !$request->headers->has('Authorization')) {
        try {
            $decrypted = Crypt::decryptString($token);
            
            $jwt = str_contains($decrypted, '|') 
                ? explode('|', $decrypted)[1] 
                : $decrypted;

            $request->headers->set('Authorization', 'Bearer ' . $jwt);
            Log::info('JWT inyectado:', ['jwt' => $jwt]); // ← agrega esto
        } catch (\Exception $e) {
            Log::error('Error desencriptando: ' . $e->getMessage());
        }
    }

    return $next($request);
}
}
