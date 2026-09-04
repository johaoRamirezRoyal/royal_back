<?php

use App\Http\Controllers\Auth\SamiRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('redirect-to-sami', [SamiRedirectController::class, 'redirect']);
