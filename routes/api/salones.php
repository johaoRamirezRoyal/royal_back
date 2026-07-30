<?php

use App\Http\Controllers\Reservas\SalonesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SalonesController::class, 'listarSalones']);
Route::post('/', [SalonesController::class, 'crearSalon']);
Route::put('/{id}', [SalonesController::class, 'actualizarSalon']);
Route::delete('/{id}', [SalonesController::class, 'eliminarSalon']);
