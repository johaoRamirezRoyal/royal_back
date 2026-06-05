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

Route::prefix('password')->group(function () {
    Route::post('restore', [PasswordResetController::class, 'createToken']);
    Route::post('validate-token', [PasswordResetController::class, 'validateToken']);
    Route::patch('update-password', [PasswordResetController::class, 'resetPassword']);
});
