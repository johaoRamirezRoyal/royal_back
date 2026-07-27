<?php

use App\Http\Controllers\PerfilUsuario\PerfilUsuarioController;
use Illuminate\Support\Facades\Route;

// ── Hoja de Vida ───────────────────────────────────────────

Route::get('/hoja-de-vida', [PerfilUsuarioController::class, 'hojaDeVida']);

// ── Info Adicional ──────────────────────────────────────────

Route::get('/', [PerfilUsuarioController::class, 'mostrarInformacionPerfilUsuario']);
Route::post('/', [PerfilUsuarioController::class, 'crearActualizarInfoAdicional']);
Route::put('/', [PerfilUsuarioController::class, 'actualizarInfoAdicional']);
Route::delete('/', [PerfilUsuarioController::class, 'eliminarInfoAdicional']);
Route::get('/completitud', [PerfilUsuarioController::class, 'verificarCompletitudPerfil']);

// ── Formación ───────────────────────────────────────────────

Route::get('/formaciones', [PerfilUsuarioController::class, 'obtenerFormacionesPorUsuario']);
Route::get('/formaciones/porTipo', [PerfilUsuarioController::class, 'obtenerFormacionesPorTipo']);
Route::post('/formaciones', [PerfilUsuarioController::class, 'crearFormacion']);
Route::put('/formaciones', [PerfilUsuarioController::class, 'actualizarFormacion']);
Route::delete('/formaciones', [PerfilUsuarioController::class, 'eliminarFormacion']);
Route::delete('/formaciones/usuario', [PerfilUsuarioController::class, 'eliminarFormacionesPorUsuario']);

// ── Experiencia Laboral ─────────────────────────────────────

Route::get('/experiencias', [PerfilUsuarioController::class, 'obtenerExperienciasPorUsuario']);
Route::get('/experiencias/activas', [PerfilUsuarioController::class, 'obtenerExperienciasActivas']);
Route::get('/experiencias/resumen', [PerfilUsuarioController::class, 'obtenerResumenExperiencias']);
Route::post('/experiencias', [PerfilUsuarioController::class, 'crearExperienciaLaboral']);
Route::put('/experiencias', [PerfilUsuarioController::class, 'actualizarExperienciaLaboral']);
Route::delete('/experiencias', [PerfilUsuarioController::class, 'eliminarExperienciaLaboral']);
Route::delete('/experiencias/usuario', [PerfilUsuarioController::class, 'eliminarExperienciasPorUsuario']);

// ── Producción Intelectual ───────────────────────────────────

Route::get('/producciones', [PerfilUsuarioController::class, 'obtenerProduccionesPorUsuario']);
Route::post('/producciones', [PerfilUsuarioController::class, 'crearProduccionIntelectual']);
Route::put('/producciones', [PerfilUsuarioController::class, 'actualizarProduccionIntelectual']);
Route::delete('/producciones', [PerfilUsuarioController::class, 'eliminarProduccionIntelectual']);
Route::delete('/producciones/usuario', [PerfilUsuarioController::class, 'eliminarProduccionesPorUsuario']);
