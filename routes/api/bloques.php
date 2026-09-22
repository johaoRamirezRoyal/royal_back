<?php

use App\Http\Controllers\Areas\AreasComunesController;
use App\Http\Controllers\Areas\BloquesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [BloquesController::class, 'obtenerTodosLosBloques']);
// "Mis áreas" — autoservicio, permiso propio (ver migración 2026_09_14_120000_seed_opcion_mis_areas_comunes).
Route::get('/mis-bloques', [BloquesController::class, 'misBloques']);
Route::post('/', [BloquesController::class, 'crearBloque']);
Route::put('/', [BloquesController::class, 'actualizarBloque']);
Route::post('/estado', [BloquesController::class, 'desactivarBloques']);
Route::post('/responsables', [BloquesController::class, 'asignarResponsables']);
Route::get('/usuarios-asignables', [BloquesController::class, 'usuariosAsignablesBloque']);

// Acciones administrativas masivas (AreasComunesController, permiso 108 propio).
Route::post('/areas', [AreasComunesController::class, 'asignarAreasBloque']);
Route::post('/inventario/reclasificar', [AreasComunesController::class, 'reclasificarInventario']);
// Check semestral (108 o 109) — migración del legacy chek_zonas.
Route::post('/inventario/check', [AreasComunesController::class, 'registrarCheck']);
// Mover un ítem ya existente a otro bloque/área (108 propio).
Route::post('/inventario/mover', [AreasComunesController::class, 'moverItem']);
// Historial completo de checks (108 o 109) — ver "Historial de Checks".
Route::get('/inventario/checks', [AreasComunesController::class, 'historialChecks']);
// % de áreas comunes con al menos un check registrado (108 o 109).
Route::get('/inventario/checks/indicador', [AreasComunesController::class, 'indicadorChecks']);
// PDF checklist del historial de checks (108 o 109) — ver "Historial de Checks".
Route::post('/inventario/checks/pdf', [AreasComunesController::class, 'historialChecksPdf']);
