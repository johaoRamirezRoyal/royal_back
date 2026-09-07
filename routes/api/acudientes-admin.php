<?php

use App\Http\Controllers\Admissions\AcudientesAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AcudientesAdminController::class, 'index']);
Route::put('/estado', [AcudientesAdminController::class, 'actualizarEstado']);
