<?php

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\JwtFromCookie;
use App\Http\Middleware\LogActividadMiddleware;
use App\Http\Middleware\LogDominioMiddleware;
use App\Http\Middleware\RestrictToAdminEmails;
use App\Http\Middleware\RestrictToHikvisionDevices;
use App\Http\Middleware\SwitchActiveConnection;
use App\Http\Middleware\ValidateSystem;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prependToGroup('api', JwtFromCookie::class);
        // Necesita $request->user() (JwtFromCookie ya corrió) y debe correr ANTES que
        // cualquier modelo de negocio toque la DB — por eso va primero entre los appended.
        $middleware->appendToGroup('api', SwitchActiveConnection::class);
        $middleware->appendToGroup('api', LogActividadMiddleware::class);
        $middleware->appendToGroup('api', LogDominioMiddleware::class);
        $middleware->encryptCookies(except: ['token', 'admissions_token']);
        $middleware->alias([
            'auth' => Authenticate::class,
            'system' => ValidateSystem::class,
            'hikvision.device' => RestrictToHikvisionDevices::class,
            'admin.access' => RestrictToAdminEmails::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'active' => false,
                'message' => 'No autenticado',
            ], 401);
        });
    })->create();
