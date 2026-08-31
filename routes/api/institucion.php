<?php

use App\Http\Controllers\Institucion\InstitucionController;
use Illuminate\Support\Facades\Route;

Route::get('instituciones', [InstitucionController::class, 'listar']);
Route::post('login', [InstitucionController::class, 'login']);
Route::post('resend-login-otp', [InstitucionController::class, 'resendLoginOtp']);
Route::post('verify-login-otp', [InstitucionController::class, 'verifyLoginOtp']);

Route::middleware('institucion.session')->group(function () {
    Route::get('check', [InstitucionController::class, 'check']);
    Route::post('logout', [InstitucionController::class, 'logout']);
    Route::post('request-email-otp', [InstitucionController::class, 'requestEmailOtp']);
    Route::post('verify-email-otp', [InstitucionController::class, 'verifyEmailOtp']);
    Route::post('carta-recomendacion', [InstitucionController::class, 'guardarCartaRecomendacion']);
    Route::get('carta-recomendacion', [InstitucionController::class, 'listarMisCartas']);
});
