<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ModoMantenimiento
{
    /**
     * Con MANTENIMIENTO=true en el .env rechaza toda petición a la API con 503, antes de
     * leer la sesión o tocar la base de datos. Se lee de config (no de env()) para que
     * funcione con `config:cache`: tras cambiar el .env hay que volver a cachear.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('app.mantenimiento')) {
            return $next($request);
        }

        return response()->json([
            'error' => true,
            'mantenimiento' => true,
            'message' => 'El sistema está en mantenimiento. Vuelve a intentarlo en unos minutos.',
        ], 503, ['Retry-After' => 300]);
    }
}
