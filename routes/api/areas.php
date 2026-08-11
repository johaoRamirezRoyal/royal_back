<?php

use App\Http\Controllers\Areas\AreasController;
use Illuminate\Support\Facades\Route;

Route::put('/', [AreasController::class, 'actualizarArea']);
/**
 * Ejemplo de JSON para actualizar un area:
        {
            "id": 1,
            "nombre": "S40 (1B)",
            "user_log": 1,
            "activo": 1
        }
 */
Route::get('/filtro', [AreasController::class, 'filtrarAreas']);
/*
            Filtrada area
            http://localhost:8000/api/areas/filtro?filtro=filtro_a_buscar
        */

Route::post('/asignar', [AreasController::class, 'asignarArea']);
/**
 * Ejemplo de JSON para asignar un area a un usuario:
            {
                "id_user": 3123,
                "id_area": 104
            }
 */
Route::get('/usuarios-asignables', [AreasController::class, 'usuariosAsignables']);
Route::get('/usuario-responsable', [AreasController::class, 'usuariosResponsablesAreas']);
Route::get('/usuario-responsable/{id_area}', [AreasController::class, 'usuarioResponsableArea']);
Route::post('/estado', [AreasController::class, 'desactivarAreas']);
/**
 * Ejemplo JSON para cambiar el estado del area:
        {
            "ids": [
                    1, 2, 3, 4, 5
                    ],
            "estado": 1 -> activo | 0 -> inactivo
        }
 */
Route::get('/', [AreasController::class, 'obtenerTodasLasAreas']);
