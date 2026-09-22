<?php

use App\Http\Controllers\Encuestas\EncuestasController;
use Illuminate\Support\Facades\Route;

// ─── Tipos de pregunta (catálogo, reusa el de Evaluaciones) ───
Route::get('/tipos-pregunta', [EncuestasController::class, 'listarTiposPregunta']);

// ─── Salones ────────────────────────────────────────────────
Route::get('/salones', [EncuestasController::class, 'listarSalones']);
// Antes del {idSalon} de abajo: guarda de una sola vez todas las asignaciones hechas en
// la pestaña Salones y QR (botón "Guardar cambios"), 1 sola solicitud en vez de 1 por salón.
Route::post('/salones/asignar-lote', [EncuestasController::class, 'asignarEncuestasLote']);
Route::put('/salones/{idSalon}', [EncuestasController::class, 'asignarEncuestaSalon']);
Route::post('/salones/{idSalon}/regenerar-token', [EncuestasController::class, 'regenerarTokenSalon']);

// ─── Encuestas ──────────────────────────────────────────────
Route::get('/', [EncuestasController::class, 'listar']);
Route::post('/', [EncuestasController::class, 'crear']);
Route::get('/{id}', [EncuestasController::class, 'obtenerPorId']);
Route::put('/{id}', [EncuestasController::class, 'actualizar']);
Route::delete('/{id}', [EncuestasController::class, 'eliminar']);
Route::put('/{id}/toggle-activo', [EncuestasController::class, 'toggleActivo']);
Route::get('/{id}/resultados', [EncuestasController::class, 'resultados']);
Route::get('/{id}/salones-con-respuestas', [EncuestasController::class, 'salonesConRespuestas']);
Route::get('/{id}/reservas-con-respuestas', [EncuestasController::class, 'reservasConRespuestas']);

// ─── Preguntas ──────────────────────────────────────────────
Route::post('/{idEncuesta}/preguntas', [EncuestasController::class, 'crearPregunta']);
Route::put('/preguntas/{idPregunta}', [EncuestasController::class, 'actualizarPregunta']);
Route::delete('/preguntas/{idPregunta}', [EncuestasController::class, 'eliminarPregunta']);

// ─── Opciones ───────────────────────────────────────────────
Route::post('/preguntas/{idPregunta}/opciones', [EncuestasController::class, 'crearOpcion']);
Route::put('/opciones/{idOpcion}', [EncuestasController::class, 'actualizarOpcion']);
Route::delete('/opciones/{idOpcion}', [EncuestasController::class, 'eliminarOpcion']);
