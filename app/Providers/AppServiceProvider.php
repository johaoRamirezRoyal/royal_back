<?php

namespace App\Providers;

use App\Events\PasswordRestore;
use App\Listeners\SendPasswordRestore;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
