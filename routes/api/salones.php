<?php

use App\Http\Controllers\Reservas\SalonesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SalonesController::class, 'listarSalones']);
Route::post('/', [SalonesController::class, 'crearSalon']);

// Antes de /{id}: si no, "configuracion" se resolvería como el parámetro {id}.
Route::get('/configuracion', [SalonesController::class, 'configuracion']);
Route::put('/configuracion', [SalonesController::class, 'actualizarConfiguracion']);

Route::put('/{id}', [SalonesController::class, 'actualizarSalon']);
Route::delete('/{id}', [SalonesController::class, 'eliminarSalon']);
