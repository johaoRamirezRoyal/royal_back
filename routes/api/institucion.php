<?php

use App\Http\Controllers\Institucion\InstitucionController;
use Illuminate\Support\Facades\Route;

// Multi-tenant por URL: antes de que exista sesión, la única forma de saber a qué
// colegio pertenece esta institución (jardín asociado) es el slug de la URL desde la que
// llega el frontend (/{slug}/admissions/institucion, ver AdmissionsTenantProvider) —
// mismo mecanismo que /api/admissions, ver ResolveColegioAdmision. La connection
// resuelta acá queda guardada en la sesión cacheada al otorgarla (ver
// InstitucionController::otorgarSesion), así que las rutas ya autenticadas de abajo NO
// repiten este middleware — dependen de esa connection guardada, no del header en cada
// request (ver EnsureInstitucionSession), para no quedar a merced de una cookie vieja de
// otro colegio en la misma pestaña/dominio.
Route::middleware('colegio.admision')->group(function () {
    Route::get('instituciones', [InstitucionController::class, 'listar']);
    Route::post('login', [InstitucionController::class, 'login']);
    Route::post('resend-login-otp', [InstitucionController::class, 'resendLoginOtp']);
    Route::post('verify-login-otp', [InstitucionController::class, 'verifyLoginOtp']);
});

Route::middleware('institucion.session')->group(function () {
    Route::get('check', [InstitucionController::class, 'check']);
    Route::post('logout', [InstitucionController::class, 'logout']);
    Route::post('request-email-otp', [InstitucionController::class, 'requestEmailOtp']);
    Route::post('verify-email-otp', [InstitucionController::class, 'verifyEmailOtp']);
    Route::post('carta-recomendacion', [InstitucionController::class, 'guardarCartaRecomendacion']);
    Route::get('carta-recomendacion', [InstitucionController::class, 'listarMisCartas']);
});
