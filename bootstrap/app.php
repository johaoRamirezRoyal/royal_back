<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\EnsureInstitucionSession;
use App\Http\Middleware\JwtFromCookie;
use App\Http\Middleware\LogActividadMiddleware;
use App\Http\Middleware\LogDominioMiddleware;
use App\Http\Middleware\ModoMantenimiento;
use App\Http\Middleware\PaginaAccesoNavegador;
use App\Http\Middleware\RequirePermiso;
use App\Http\Middleware\ResolveColegioAdmision;
use App\Http\Middleware\RestrictToAdminEmails;
use App\Http\Middleware\RestrictToHikvisionDevices;
use App\Http\Middleware\SwitchActiveConnection;
use App\Http\Middleware\ValidateSystem;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('api', JwtFromCookie::class);
        // Va antes que todo (prepend posterior = primero): en mantenimiento ni lee la sesión.
        $middleware->prependToGroup('api', ModoMantenimiento::class);
        // El más externo: ve el 401/403 de cualquier capa (también el de AuthenticationException,
        // ya convertido en respuesta) y, si lo abrió un navegador, lo muestra como página.
        $middleware->prependToGroup('api', PaginaAccesoNavegador::class);
        // Necesita $request->user() (JwtFromCookie ya corrió) y debe correr ANTES que
        // cualquier modelo de negocio toque la DB — por eso va primero entre los appended.
        $middleware->appendToGroup('api', SwitchActiveConnection::class);
        $middleware->appendToGroup('api', LogActividadMiddleware::class);
        $middleware->appendToGroup('api', LogDominioMiddleware::class);
        $middleware->encryptCookies(except: ['token', 'admissions_token']);
        $middleware->alias([
            'auth' => Authenticate::class,
            'system' => ValidateSystem::class,
            'institucion.session' => EnsureInstitucionSession::class,
            'hikvision.device' => RestrictToHikvisionDevices::class,
            'admin.access' => RestrictToAdminEmails::class,
            'colegio.admision' => ResolveColegioAdmision::class,
            'permiso' => RequirePermiso::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'active' => false,
                'message' => 'No autenticado',
            ], 401);
        });
        // Una ruta que no existe no pasa por el grupo `api` ni por su middleware: su 404 se
        // convierte aquí. Si hubo ruta, lo hace PaginaAccesoNavegador (evita hacerlo dos veces).
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            return $request->route() ? $response : PaginaAccesoNavegador::pagina($request, $response);
        });
    })->create();
