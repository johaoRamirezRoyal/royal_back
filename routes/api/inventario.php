<?php

use App\Http\Controllers\Inventarios\InventariosController;
use Illuminate\Support\Facades\Route;

Route::get('/listado', [InventariosController::class, 'obtenerListadoInventario']);
/**
 *  Ejemplo de JSON para obtener el listado de inventario paginado:
            {
                "search": "computado",
                "id_categoria": [],
                "estado": [],
                "id_usuario": 3123,
                "per-page": 20
            }

 * URL Puede ser:
            http://localhost:8000/api/inventario/listado?page=300&per_page=10
 */
Route::put('/descontinuar', [InventariosController::class, 'descontinuarInventario']);
Route::post('/', [InventariosController::class, 'agregarInventario']);
Route::put('/liberar', [InventariosController::class, 'liberarInventario']);
Route::put('/asignar', [InventariosController::class, 'asignarInventario']);