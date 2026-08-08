<?php

namespace App\Providers;

use App\Events\PasswordRestore;
use App\Listeners\SendPasswordRestore;
use App\Services\DocumentosVarios\DocumentosVariosService;
use App\Services\Enfermeria\EnfermeriaServices;
use App\Services\HistoriaClinica\HistoriaClinicaService;
use App\Services\JwtService;
use App\Services\PerfilUsuario\PerfilUsuarioService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // PasswordReset
        $this->app->bind(
            PasswordRestore::class,
            SendPasswordRestore::class
        );

        // JWTService
        $this->app->singleton(JwtService::class);

        // HistoriaClinicaService
        $this->app->singleton(HistoriaClinicaService::class);

        // PerfilUsuarioService
        $this->app->singleton(PerfilUsuarioService::class);

        // DocumentosVariosService
        $this->app->singleton(DocumentosVariosService::class);

        // EnfermeriaServices
        $this->app->singleton(EnfermeriaServices::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Cuota propia por dispositivo/IP (no por usuario autenticado): un kiosco
        // compartido donde marcan varios trabajadores no debe agotar la cuota de
        // uno por las marcaciones de otro.
        RateLimiter::for('asistencia', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));
    }
}
