<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SamiRedirectController;
use Illuminate\Support\Facades\Route;

Route::post('logout', [AuthController::class, 'logout']);

Route::get('redirect-to-sami', [SamiRedirectController::class, 'redirect']);
