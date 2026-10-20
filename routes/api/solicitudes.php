<?php

use App\Http\Controllers\ProcesoCompra\SolicitudesController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

Route::get('/', [SolicitudesController::class, 'listar'])->middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN, Permiso::PERFIL_ADMINISTRADOR, Permiso::PERFIL_COORDINADOR]));
Route::get('/mias', [SolicitudesController::class, 'misSolicitudes']);
Route::get('/seguimiento', [SolicitudesController::class, 'seguimiento'])->middleware(Permiso::ruta([Permiso::COMPRAS_GESTION]));
Route::get('/{id}', [SolicitudesController::class, 'ver'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::COMPRAS_LISTADO_SOLICITUDES]));
Route::post('/', [SolicitudesController::class, 'crear'])->middleware(Permiso::ruta([Permiso::COMPRAS_GENERAR_SOLICITUDES]));
Route::post('/{id}/cancelar', [SolicitudesController::class, 'cancelar'])->where('id', '[0-9]+');
Route::post('/{id}/verificar', [SolicitudesController::class, 'verificar'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::COMPRAS_LISTADO_SOLICITUDES]));
Route::post('/{id}/aprobar', [SolicitudesController::class, 'aprobar'])->where('id', '[0-9]+')->middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN, Permiso::PERFIL_ADMINISTRADOR, Permiso::PERFIL_COORDINADOR]));
Route::post('/{id}/rechazar-inicial', [SolicitudesController::class, 'rechazarInicial'])->where('id', '[0-9]+')->middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN, Permiso::PERFIL_ADMINISTRADOR, Permiso::PERFIL_COORDINADOR]));
Route::post('/{id}/asignar-proveedor', [SolicitudesController::class, 'asignarProveedor'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::COMPRAS_GESTION]));
Route::post('/{id}/disponible-stock', [SolicitudesController::class, 'disponibleStock'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::COMPRAS_GESTION]));
Route::put('/{id}/aplazar', [SolicitudesController::class, 'aplazar'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::COMPRAS_LISTADO_SOLICITUDES]));
Route::put('/{id}/rechazar', [SolicitudesController::class, 'rechazar'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::COMPRAS_LISTADO_SOLICITUDES]));
Route::post('/{id}/anular', [SolicitudesController::class, 'anular'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::COMPRAS_GESTION]));
Route::post('/{id}/verificar-entrega', [SolicitudesController::class, 'verificarEntrega'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::COMPRAS_VENTAS]));
Route::post('/{id}/agregar-inventario', [SolicitudesController::class, 'agregarInventario'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::COMPRAS_LISTADO_SOLICITUDES]));