<?php

namespace App\Providers;

use App\Events\PasswordRestore;
use App\Listeners\SendPasswordRestore;
use App\Services\DocumentosVarios\DocumentosVariosService;
use App\Services\HistoriaClinica\HistoriaClinicaService;
use App\Services\JwtService;
use App\Services\PerfilUsuario\PerfilUsuarioService;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
