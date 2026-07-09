<?php

use App\Http\Controllers\Reservas\ReservaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReservaController::class, 'listarReservas']);
Route::post('/', [ReservaController::class, 'crearReserva']);
Route::put('/', [ReservaController::class, 'actualizarReserva']);
