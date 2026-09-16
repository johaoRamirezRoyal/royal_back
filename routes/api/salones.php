<?php

use App\Http\Controllers\Reservas\SalonesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SalonesController::class, 'listarSalones']);
Route::post('/', [SalonesController::class, 'crearSalon']);

// Antes de /{id}: si no, "configuracion"/"select" se resolverían como el parámetro {id}.
Route::get('/configuracion', [SalonesController::class, 'configuracion']);
Route::put('/configuracion', [SalonesController::class, 'actualizarConfiguracion']);
Route::get('/select', [SalonesController::class, 'salonesSelect']);

Route::put('/{id}', [SalonesController::class, 'actualizarSalon']);
Route::delete('/{id}', [SalonesController::class, 'eliminarSalon']);
