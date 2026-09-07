<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\TokenExchangeController;
use App\Http\Controllers\PasswordReset\PasswordResetController;
use Illuminate\Support\Facades\Route;

// Validacion para el ingreso de la vista de 'admisiones' desde la pagina principal
Route::prefix('admissions')->group(function () {
    Route::post('exchange-token', [TokenExchangeController::class, 'exchange']);
    Route::post('revoke-admisiones', [TokenExchangeController::class, 'revoke']);
});

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::get('/check', [AuthController::class, 'check']);
Route::get('branding-preview', [AuthController::class, 'brandingPreview']);

// Compartida entre ambos sistemas (igual que check) — el método ya elige qué cookie
// invalidar según ?system=, no necesita el guard system:general que antes la bloqueaba
// para las peticiones de logout de admisiones (ver JwtFromCookie para cómo resuelve
// auth('api')->user() en este caso).
Route::post('logout', [AuthController::class, 'logout']);

Route::prefix('password')->group(function () {
    Route::post('restore', [PasswordResetController::class, 'createToken']);
    Route::post('validate-token', [PasswordResetController::class, 'validateToken']);
    Route::patch('update-password', [PasswordResetController::class, 'resetPassword']);
});
