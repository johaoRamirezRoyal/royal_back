<?php

use App\Http\Controllers\Admissions\AdmissionsController;
use Illuminate\Support\Facades\Route;

// Punto de entrada único de la pantalla principal — un solo formulario correo+contraseña
// que decide internamente si aplica login por contraseña o el flujo histórico de
// solo-OTP (ver AdmissionsController::iniciarAcceso).
Route::post('iniciar-acceso', [AdmissionsController::class, 'iniciarAcceso']);

Route::post('request-validation', [AdmissionsController::class, 'requestVerification']);
Route::post('validate-session', [AdmissionsController::class, 'validateVerificationCode']);
Route::post('validate-code', [AdmissionsController::class, 'forgetVerificationCode']);
Route::post('register-guardian', [AdmissionsController::class, 'familyRegister']);
Route::get('test-email', [AdmissionsController::class, 'testEmail']);

// Login por contraseña de acudientes — usado directamente por iniciar-acceso; se deja
// también como ruta pública propia por si se necesita reintentar solo ese paso.
Route::post('login', [AdmissionsController::class, 'loginConPassword']);
Route::post('resend-login-otp', [AdmissionsController::class, 'resendLoginOtpAcudiente']);
Route::post('verify-login-otp', [AdmissionsController::class, 'verifyLoginOtpAcudiente']);
