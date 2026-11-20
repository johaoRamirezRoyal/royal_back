<?php

namespace App\Http\Controllers\Deploy;

use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Artisan;

/**
 * Dispara `migrate:colegios` desde el workflow de deploy, que sube por FTP a un hosting
 * sin SSH. Mismo patrón que MigracionCloudinaryController: sin JWT, protegido por el
 * header X-Deploy-Token contra DEPLOY_MIGRACION_TOKEN; con la variable vacía el endpoint
 * no existe (404).
 */
class MigrarColegiosController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $request, Closure $next) {
                $token = (string) config('deploy.migracion_token');

                if ($token === '') {
                    abort(404);
                }

                if (! hash_equals($token, (string) $request->header('X-Deploy-Token'))) {
                    return response()->json(['error' => true, 'message' => 'Token de deploy inválido.'], 401);
                }

                return $next($request);
            }),
        ];
    }

    public function ejecutar(): JsonResponse
    {
        $exit = Artisan::call('migrate:colegios', ['--force' => true]);

        return response()->json([
            'error' => $exit !== 0,
            'message' => $exit === 0
                ? 'Migraciones aplicadas en todos los colegios.'
                : 'Alguna migración falló; revisa el detalle.',
            'data' => ['output' => Artisan::output()],
        ], $exit === 0 ? 200 : 500);
    }
}
