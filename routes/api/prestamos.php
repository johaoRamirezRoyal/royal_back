<?php

use App\Http\Controllers\Prestamos\PrestamosController;
use Illuminate\Support\Facades\Route;

/**
 * GET /api/prestamos?descripcion=...&activo=true&devueltos=true&vencidos=true&id_user_entrega=...&id_user_prestamo=...&per_page=10
 */
Route::get('/', [PrestamosController::class, 'listarPrestamos']);

/**
 * POST /api/prestamos
{
    "id_inventario": 1,
    "id_user_entrega": 1,
    "id_user_prestamo": 2,
    "fecha_prestamo": "2026-08-13 09:00:00",
    "fecha_compromiso": "2026-07-15 17:00:00",
    "observacion": "Préstamo de proyector"
}
 */
Route::post('/', [PrestamosController::class, 'crearPrestamo']);

/**
 * PUT /api/prestamos
{
    "id": 1,
    "id_user_recibe": 3,
    "fecha_devolucion": "2026-07-10 15:00:00",
    "observacion": "Devuelto en buen estado"
}
 */
Route::put('/', [PrestamosController::class, 'actualizarPrestamo']);
