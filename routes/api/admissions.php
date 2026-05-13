<?php

use App\Http\Controllers\Admissions\AdmissionsController;
use Illuminate\Support\Facades\Route;

Route::post('request-validation', [AdmissionsController::class, 'requestVerification']);
Route::post('validate-session', [AdmissionsController::class, 'validateVerificationCode']);
Route::post('validate-code', [AdmissionsController::class, 'forgetVerificationCode']);
Route::post('register-guardian', [AdmissionsController::class, 'familyRegister']);
