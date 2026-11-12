<?php

use App\Http\Controllers\Reservas\ReservaController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ReservaController::class, 'listarReservas']);
Route::post('/', [ReservaController::class, 'crearReserva']);
Route::put('/', [ReservaController::class, 'actualizarReserva']);

/**
 * http://localhost:8000/api/reservas/disponibilidad-portatil?fecha=2026-08-20&hora=1
 * Portátiles disponibles (pool compartido del colegio, no por salón) para esa franja.
 */
Route::get('/disponibilidad-portatil', [ReservaController::class, 'disponibilidadPortatil']);

/**
 * Autorización de reservas en salones con responsable (ej. Maker Lab): el responsable ve
 * las pendientes de sus salones y las aprueba o rechaza. Ver ReservasServices::decidirReservas.
 */
Route::get('/pendientes', [ReservaController::class, 'pendientes']);
Route::put('/decision', [ReservaController::class, 'decidir']);
