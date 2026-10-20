<?php

use App\Http\Controllers\Reservas\SalonesController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

Route::get('/', [SalonesController::class, 'listarSalones']);
Route::post('/', [SalonesController::class, 'crearSalon'])->middleware(Permiso::ruta([Permiso::SALONES]));

// Antes de /{id}: si no, "configuracion"/"select" se resolverían como el parámetro {id}.
Route::get('/configuracion', [SalonesController::class, 'configuracion']);
Route::put('/configuracion', [SalonesController::class, 'actualizarConfiguracion'])->middleware(Permiso::ruta([Permiso::SALONES]));
Route::get('/select', [SalonesController::class, 'salonesSelect']);

Route::put('/{id}', [SalonesController::class, 'actualizarSalon'])->middleware(Permiso::ruta([Permiso::SALONES]));
Route::delete('/{id}', [SalonesController::class, 'eliminarSalon'])->middleware(Permiso::ruta([Permiso::SALONES]));
