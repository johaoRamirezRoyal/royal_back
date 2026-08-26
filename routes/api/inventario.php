<?php

use App\Http\Controllers\Inventarios\InventariosController;
use Illuminate\Support\Facades\Route;

Route::get('/listado', [InventariosController::class, 'obtenerListadoInventario']);
Route::get('/listado-consolidado', [InventariosController::class, 'listadoConsolidado']);
Route::put('/listado-consolidado/descripcion', [InventariosController::class, 'editarDescripcionGrupo']);
Route::post('/listado-consolidado/incrementar', [InventariosController::class, 'incrementarCantidadGrupo']);
Route::post('/listado-consolidado/disminuir', [InventariosController::class, 'disminuirCantidadGrupo']);
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
            http://localhost:8000/api/inventario/listado?per-page=1000&estado_not_in[]=5&id_usuario=3123
 */
Route::put('/descontinuar', [InventariosController::class, 'descontinuarInventario']);
Route::post('/', [InventariosController::class, 'agregarInventario']);
Route::put('/liberar', [InventariosController::class, 'liberarInventario']);
Route::put('/asignar', [InventariosController::class, 'asignarInventario']);

Route::post('/reportar', [InventariosController::class, 'reportarInventario']);
Route::get('/reportes', [InventariosController::class, 'mostrarReportesDeInventario']);
Route::put('/reportes/solucionar', [InventariosController::class, 'solucionarReporteInventario']);
Route::post('/mantenimiento', [InventariosController::class, 'programarMantenimientoPreventivo']);
Route::get('/mantenimiento/indicador', [InventariosController::class, 'indicadorMantenimiento']);
Route::get('/mantenimiento/grafica', [InventariosController::class, 'graficaMantenimiento']);

// Rutas con {id} al final para no interceptar las rutas estáticas de arriba
// (Laravel matchea en orden de registro, no por especificidad).
Route::get('/{id}/historial', [InventariosController::class, 'historialInventario'])->whereNumber('id');
Route::put('/{id}', [InventariosController::class, 'actualizarInventario'])->whereNumber('id');