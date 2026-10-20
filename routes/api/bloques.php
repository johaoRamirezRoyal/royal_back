<?php

use App\Http\Controllers\Areas\AreasComunesController;
use App\Http\Controllers\Areas\BloquesController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

Route::get('/', [BloquesController::class, 'obtenerTodosLosBloques'])->middleware(Permiso::ruta([Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO]));
// "Mis áreas" — autoservicio, permiso propio (ver migración 2026_09_14_120000_seed_opcion_mis_areas_comunes).
Route::get('/mis-bloques', [BloquesController::class, 'misBloques'])->middleware(Permiso::ruta([Permiso::MIS_AREAS_COMUNES]));
Route::post('/', [BloquesController::class, 'crearBloque'])->middleware(Permiso::ruta([Permiso::AREAS_COMUNES_ADMIN]));
Route::put('/', [BloquesController::class, 'actualizarBloque'])->middleware(Permiso::ruta([Permiso::AREAS_COMUNES_ADMIN]));
Route::post('/estado', [BloquesController::class, 'desactivarBloques'])->middleware(Permiso::ruta([Permiso::AREAS_COMUNES_ADMIN]));
Route::post('/responsables', [BloquesController::class, 'asignarResponsables'])->middleware(Permiso::ruta([Permiso::AREAS_COMUNES_ADMIN]));
Route::get('/usuarios-asignables', [BloquesController::class, 'usuariosAsignablesBloque'])->middleware(Permiso::ruta([Permiso::AREAS_COMUNES_ADMIN]));

// Acciones administrativas masivas (AreasComunesController, permiso 108 propio).
Route::post('/areas', [AreasComunesController::class, 'asignarAreasBloque'])->middleware(Permiso::ruta([Permiso::AREAS_COMUNES_ADMIN]));
Route::post('/inventario/reclasificar', [AreasComunesController::class, 'reclasificarInventario'])->middleware(Permiso::ruta([Permiso::AREAS_COMUNES_ADMIN]));
// Check semestral (108 o 109) — migración del legacy chek_zonas.
Route::post('/inventario/check', [AreasComunesController::class, 'registrarCheck'])->middleware(Permiso::ruta([Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO, Permiso::MIS_AREAS_COMUNES]));
// Mover un ítem ya existente a otro bloque/área (108 propio).
Route::post('/inventario/mover', [AreasComunesController::class, 'moverItem'])->middleware(Permiso::ruta([Permiso::AREAS_COMUNES_ADMIN]));
// Historial completo de checks (108 o 109) — ver "Historial de Checks".
Route::get('/inventario/checks', [AreasComunesController::class, 'historialChecks'])->middleware(Permiso::ruta([Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO]));
// % de áreas comunes con al menos un check registrado (108 o 109).
Route::get('/inventario/checks/indicador', [AreasComunesController::class, 'indicadorChecks'])->middleware(Permiso::ruta([Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO]));
// PDF checklist del historial de checks (108 o 109) — ver "Historial de Checks".
Route::post('/inventario/checks/pdf', [AreasComunesController::class, 'historialChecksPdf'])->middleware(Permiso::ruta([Permiso::AREAS_COMUNES_ADMIN, Permiso::AREAS_COMUNES_USO]));
