<?php

use App\Http\Controllers\Reservas\SalonesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SalonesController::class, 'listarHoras']);
Route::post('/', [SalonesController::class, 'crearHora']);
Route::put('/{id}', [SalonesController::class, 'actualizarHora']);
Route::delete('/{id}', [SalonesController::class, 'eliminarHora']);
