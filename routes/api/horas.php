<?php

use App\Http\Controllers\Reservas\SalonesController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

Route::get('/', [SalonesController::class, 'listarHoras']);
Route::post('/', [SalonesController::class, 'crearHora'])->middleware(Permiso::ruta([Permiso::SALONES]));
Route::put('/{id}', [SalonesController::class, 'actualizarHora'])->middleware(Permiso::ruta([Permiso::SALONES]));
Route::delete('/{id}', [SalonesController::class, 'eliminarHora'])->middleware(Permiso::ruta([Permiso::SALONES]));
