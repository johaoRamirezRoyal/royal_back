<?php

namespace App\Providers;

use App\Listeners\SendPasswordRestore;
use Illuminate\Auth\Events\PasswordReset;
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
            PasswordReset::class,
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
