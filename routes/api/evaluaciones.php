<?php

use App\Http\Controllers\Evaluaciones\EvaluacionesController;
use Illuminate\Support\Facades\Route;

// ─── Catálogo de servicios ─────────────────────────────────
Route::get('/servicios', [EvaluacionesController::class, 'listarServicios']);
Route::post('/servicios', [EvaluacionesController::class, 'crearServicio']);
Route::put('/servicios/{id}', [EvaluacionesController::class, 'actualizarServicio']);
Route::delete('/servicios/{id}', [EvaluacionesController::class, 'eliminarServicio']);

// ─── Tipos de pregunta ────────────────────────────────────
Route::get('/tipos-pregunta', [EvaluacionesController::class, 'listarTiposPregunta']);

// ─── Evaluaciones ─────────────────────────────────────────
Route::get('/mis-evaluaciones', [EvaluacionesController::class, 'misEvaluaciones']);
Route::get('/mis-resultados', [EvaluacionesController::class, 'misResultados']);
Route::get('/periodo-activo', [EvaluacionesController::class, 'periodoActivo']);
Route::get('/periodos', [EvaluacionesController::class, 'listarPeriodos']);
Route::get('/', [EvaluacionesController::class, 'listar']);
Route::post('/', [EvaluacionesController::class, 'crear']);
Route::get('/{id}', [EvaluacionesController::class, 'obtenerPorId']);
Route::put('/{id}', [EvaluacionesController::class, 'actualizar']);
Route::delete('/{id}', [EvaluacionesController::class, 'eliminar']);
Route::put('/{id}/toggle-activo', [EvaluacionesController::class, 'toggleActivo']);
Route::get('/{id}/evaluables', [EvaluacionesController::class, 'obtenerEvaluables']);

// ─── Secciones ────────────────────────────────────────────
Route::post('/{idEvaluacion}/secciones', [EvaluacionesController::class, 'crearSeccion']);
Route::put('/secciones/{idSeccion}', [EvaluacionesController::class, 'actualizarSeccion']);
Route::delete('/secciones/{idSeccion}', [EvaluacionesController::class, 'eliminarSeccion']);

// ─── Preguntas ────────────────────────────────────────────
Route::post('/secciones/{idSeccion}/preguntas', [EvaluacionesController::class, 'crearPregunta']);
Route::put('/preguntas/{idPregunta}', [EvaluacionesController::class, 'actualizarPregunta']);
Route::delete('/preguntas/{idPregunta}', [EvaluacionesController::class, 'eliminarPregunta']);

// ─── Opciones ─────────────────────────────────────────────
Route::post('/preguntas/{idPregunta}/opciones', [EvaluacionesController::class, 'crearOpcion']);
Route::put('/opciones/{idOpcion}', [EvaluacionesController::class, 'actualizarOpcion']);
Route::delete('/opciones/{idOpcion}', [EvaluacionesController::class, 'eliminarOpcion']);

// ─── Respuestas ───────────────────────────────────────────
Route::post('/{idEvaluacion}/responder', [EvaluacionesController::class, 'enviarRespuesta']);
Route::get('/{idEvaluacion}/respuestas', [EvaluacionesController::class, 'listarRespuestas']);
Route::get('/respuestas/{idRespuesta}', [EvaluacionesController::class, 'obtenerRespuesta']);
Route::put('/respuestas/{idRespuesta}', [EvaluacionesController::class, 'actualizarRespuesta']);
Route::post('/respuestas/{idRespuesta}/reenviar-correo', [EvaluacionesController::class, 'reenviarCorreo']);
Route::get('/respuestas/{idRespuesta}/pdf', [EvaluacionesController::class, 'descargarPdf']);

// ─── Resultados ───────────────────────────────────────────
Route::get('/{idEvaluacion}/resultados', [EvaluacionesController::class, 'calcularResultados']);
