<?php

use App\Http\Controllers\Admissions\AcudientesAdminController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

Route::get('/', [AcudientesAdminController::class, 'index'])->middleware(Permiso::ruta([Permiso::ACUDIENTES_GESTION]));
Route::put('/estado', [AcudientesAdminController::class, 'actualizarEstado'])->middleware(Permiso::ruta([Permiso::ACUDIENTES_GESTION]));
