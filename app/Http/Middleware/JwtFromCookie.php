<?php

namespace App\Http\Middleware;

use App\Services\AdminManagement\BasesDatosService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
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

                // Multi-tenant: switchea `database.default` a la base del tenant ANTES de que
                // cualquier modelo (empezando por la resolución de $request->user() vía el
                // guard JWT) toque la DB — Usuario/LogActividad ya resuelven su connection
                // dinámicamente contra `database.default` (ver Usuario::__construct). Solo
                // aplica al sistema general: el token de admisiones es un contexto de auth
                // aparte, sin este claim.
                if ($payload->get('system') === 'general') {
                    // Payload::get() no soporta un segundo argumento de default (ver
                    // vendor/tymon/jwt-auth/src/Payload.php) — devuelve null si el claim no
                    // existe (tokens emitidos antes de este cambio).
                    $connection = $payload->get('db_connection') ?? 'mysql';

                    if (BasesDatosService::esConnectionValida($connection)) {
                        Config::set('database.default', $connection);
                    }
                }

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
