<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea una ruta de contraseña tras varios intentos FALLIDOS (respuesta 401 o 422), no tras
 * varias peticiones: un acierto borra la cuenta. Uso en la ruta: `intentos:5,15` = 5 fallos
 * y 15 minutos de bloqueo.
 *
 * La clave es ruta + IP + cuenta (el correo del login, o el usuario de la sesión): así nadie
 * bloquea la cuenta de otro desde su propio equipo, y probar varias cuentas desde una IP
 * cuenta por separado para cada una.
 * ponytail: sin tope global por IP. Agrégalo (otro RateLimiter por IP) si aparece un ataque
 * probando muchas cuentas.
 */
class LimitarIntentosFallidos
{
    public function handle(Request $request, Closure $next, int $maximo = 5, int $minutos = 15): Response
    {
        $cuenta = $request->user()?->id_user ?? mb_strtolower(trim((string) $request->input('user', '')));
        $clave = 'intentos:' . $request->path() . ':' . $request->ip() . ':' . sha1((string) $cuenta);

        if (RateLimiter::tooManyAttempts($clave, $maximo)) {
            $segundos = RateLimiter::availableIn($clave);

            return response()->json([
                'error' => true,
                'message' => 'Demasiados intentos fallidos. Intenta de nuevo en ' . max(1, (int) ceil($segundos / 60)) . ' min.',
            ], 429, ['Retry-After' => $segundos]);
        }

        $respuesta = $next($request);

        if (in_array($respuesta->getStatusCode(), [401, 422], true)) {
            RateLimiter::hit($clave, $minutos * 60);
        } elseif ($respuesta->isSuccessful()) {
            RateLimiter::clear($clave);
        }

        return $respuesta;
    }
}
