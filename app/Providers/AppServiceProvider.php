<?php

namespace App\Providers;

use App\Events\PasswordRestore;
use App\Listeners\SendPasswordRestore;
use App\Services\Cloudinary\CloudinaryUploadsAdapter;
use App\Services\DocumentosVarios\DocumentosVariosService;
use App\Services\Enfermeria\EnfermeriaServices;
use App\Services\HistoriaClinica\HistoriaClinicaService;
use App\Services\JwtService;
use App\Services\PerfilUsuario\PerfilUsuarioService;
use Cloudinary\Cloudinary;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use App\Services\AdminManagement\BasesDatosService;

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

        // Disco `cloudinary` (config/filesystems.php): credenciales de config/cloudinary.php.
        Storage::extend('cloudinary_uploads', function ($app, array $config) {
            $adapter = new CloudinaryUploadsAdapter(new Cloudinary([
                'cloud' => [
                    'cloud_name' => config('cloudinary.cloud_name'),
                    'api_key' => config('cloudinary.api_key'),
                    'api_secret' => config('cloudinary.api_secret'),
                ],
                'url' => ['secure' => true],
            ]), fn () => BasesDatosService::carpeta(config('database.default')));

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });
    }
}
