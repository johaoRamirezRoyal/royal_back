<?php

use App\Http\Controllers\Inventarios\InventariosController;
use App\Support\Permiso;
use Illuminate\Support\Facades\Route;

Route::get('/listado', [InventariosController::class, 'obtenerListadoInventario'])->middleware(Permiso::ruta([Permiso::INVENTARIO, Permiso::MIS_INVENTARIOS]));
Route::get('/listado-consolidado', [InventariosController::class, 'listadoConsolidado'])->middleware(Permiso::ruta([Permiso::INVENTARIO, Permiso::PRESTAMOS_INVENTARIO, Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO, Permiso::MIS_AREAS_COMUNES]));
Route::get('/listado-consolidado/conteo', [InventariosController::class, 'conteoPorEstado'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::put('/listado-consolidado/descripcion', [InventariosController::class, 'editarDescripcionGrupo'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::post('/listado-consolidado/incrementar', [InventariosController::class, 'incrementarCantidadGrupo'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::post('/listado-consolidado/disminuir', [InventariosController::class, 'disminuirCantidadGrupo'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
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
Route::put('/descontinuar', [InventariosController::class, 'descontinuarInventario'])->middleware(Permiso::ruta([Permiso::INVENTARIO, Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO]));
Route::post('/', [InventariosController::class, 'agregarInventario'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::put('/liberar', [InventariosController::class, 'liberarInventario'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::put('/asignar', [InventariosController::class, 'asignarInventario'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));

Route::post('/reportar', [InventariosController::class, 'reportarInventario'])->middleware(Permiso::ruta([Permiso::INVENTARIO, Permiso::MIS_INVENTARIOS, Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO, Permiso::MIS_AREAS_COMUNES, Permiso::MIS_AREAS_NIVEL, Permiso::MIS_AREAS_RESPONSABLE]));
Route::get('/reportes', [InventariosController::class, 'mostrarReportesDeInventario'])->middleware(Permiso::ruta([Permiso::INVENTARIO, Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO]));
Route::put('/reportes/solucionar', [InventariosController::class, 'solucionarReporteInventario'])->middleware(Permiso::ruta([Permiso::INVENTARIO, Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO]));
Route::get('/reportes/estadisticas', [InventariosController::class, 'estadisticasReportes'])->middleware(Permiso::ruta([Permiso::INVENTARIO, Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO]));
Route::get('/reportes/visto-bueno', [InventariosController::class, 'reportesPendientesVistoBueno'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::put('/reportes/visto-bueno', [InventariosController::class, 'vistoBuenoGeneral'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::put('/reportes/{id}/visto-bueno', [InventariosController::class, 'vistoBuenoReporte'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::get('/reportes/{id}/pdf', [InventariosController::class, 'reportePdf'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::INVENTARIO]));
// También Áreas Comunes (108/109): programan preventivos desde su listado.
Route::post('/mantenimiento', [InventariosController::class, 'programarMantenimientoPreventivo'])->middleware(Permiso::ruta([Permiso::INVENTARIO, Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO]));
Route::post('/mantenimiento/pdf', [InventariosController::class, 'generarMantenimientoPdf'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::post('/mantenimiento/guia-pdf', [InventariosController::class, 'guiaMantenimientoPdf'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::get('/mantenimiento/periodos', [InventariosController::class, 'periodosMantenimiento'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::get('/mantenimiento/sin-mantenimiento', [InventariosController::class, 'sinMantenimiento'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::get('/mantenimiento/indicador', [InventariosController::class, 'indicadorMantenimiento'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));
Route::get('/mantenimiento/grafica', [InventariosController::class, 'graficaMantenimiento'])->middleware(Permiso::ruta([Permiso::INVENTARIO]));

// Rutas con {id} al final para no interceptar las rutas estáticas de arriba
// (Laravel matchea en orden de registro, no por especificidad).
Route::get('/{id}/historial', [InventariosController::class, 'historialInventario'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::INVENTARIO, Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO, Permiso::MIS_AREAS_NIVEL, Permiso::MIS_AREAS_RESPONSABLE]));
Route::get('/{id}/hoja-vida/pdf', [InventariosController::class, 'hojaVidaPdf'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::INVENTARIO, Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO]));
Route::put('/{id}', [InventariosController::class, 'actualizarInventario'])->whereNumber('id')->middleware(Permiso::ruta([Permiso::INVENTARIO]));
