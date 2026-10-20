<?php

use App\Http\Controllers\Evaluaciones\EvaluacionesController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

// ─── Catálogo de servicios ─────────────────────────────────
Route::get('/servicios', [EvaluacionesController::class, 'listarServicios'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN, Permiso::EVALUACIONES_RESULTADOS, Permiso::EVALUACIONES_REALIZAR]));
Route::post('/servicios', [EvaluacionesController::class, 'crearServicio'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::put('/servicios/{id}', [EvaluacionesController::class, 'actualizarServicio'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::delete('/servicios/{id}', [EvaluacionesController::class, 'eliminarServicio'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));

// ─── Tipos de pregunta ────────────────────────────────────
Route::get('/tipos-pregunta', [EvaluacionesController::class, 'listarTiposPregunta'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN, Permiso::EVALUACIONES_RESULTADOS]));
Route::post('/tipos-pregunta', [EvaluacionesController::class, 'crearTipoPregunta'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::post('/tipos-pregunta/{idTipo}/opciones', [EvaluacionesController::class, 'crearOpcionTipoPregunta'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::put('/tipos-pregunta/opciones/{id}', [EvaluacionesController::class, 'actualizarOpcionTipoPregunta'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::delete('/tipos-pregunta/opciones/{id}', [EvaluacionesController::class, 'eliminarOpcionTipoPregunta'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));

// ─── Evaluaciones ─────────────────────────────────────────
Route::get('/mis-evaluaciones', [EvaluacionesController::class, 'misEvaluaciones'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN, Permiso::EVALUACIONES_REALIZAR]));
Route::get('/mis-encuestas-satisfaccion', [EvaluacionesController::class, 'misEncuestasSatisfaccion']);
Route::get('/mis-resultados', [EvaluacionesController::class, 'misResultados']);
Route::get('/periodo-activo', [EvaluacionesController::class, 'periodoActivo'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN, Permiso::EVALUACIONES_RESULTADOS, Permiso::EVALUACIONES_REALIZAR]));
Route::get('/periodos', [EvaluacionesController::class, 'listarPeriodos']);
Route::get('/evaluado/{idUsuario}', [EvaluacionesController::class, 'obtenerUsuarioEvaluado'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN, Permiso::EVALUACIONES_RESULTADOS, Permiso::EVALUACIONES_REALIZAR]));
Route::get('/', [EvaluacionesController::class, 'listar'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN, Permiso::EVALUACIONES_RESULTADOS]));
Route::post('/', [EvaluacionesController::class, 'crear'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::get('/{id}', [EvaluacionesController::class, 'obtenerPorId'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN, Permiso::EVALUACIONES_RESULTADOS, Permiso::EVALUACIONES_REALIZAR]));
Route::put('/{id}', [EvaluacionesController::class, 'actualizar'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::delete('/{id}', [EvaluacionesController::class, 'eliminar'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::put('/{id}/toggle-activo', [EvaluacionesController::class, 'toggleActivo'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::post('/{id}/duplicar', [EvaluacionesController::class, 'duplicar'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::get('/{id}/evaluables', [EvaluacionesController::class, 'obtenerEvaluables'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN, Permiso::EVALUACIONES_REALIZAR]));

// ─── Secciones ────────────────────────────────────────────
Route::post('/{idEvaluacion}/secciones', [EvaluacionesController::class, 'crearSeccion'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::put('/secciones/{idSeccion}', [EvaluacionesController::class, 'actualizarSeccion'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::delete('/secciones/{idSeccion}', [EvaluacionesController::class, 'eliminarSeccion'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));

// ─── Preguntas ────────────────────────────────────────────
Route::post('/secciones/{idSeccion}/preguntas', [EvaluacionesController::class, 'crearPregunta'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::put('/preguntas/{idPregunta}', [EvaluacionesController::class, 'actualizarPregunta'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::delete('/preguntas/{idPregunta}', [EvaluacionesController::class, 'eliminarPregunta'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::put('/{id}/preguntas/orden', [EvaluacionesController::class, 'reordenarPreguntas'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));

// ─── Opciones ─────────────────────────────────────────────
Route::post('/preguntas/{idPregunta}/opciones', [EvaluacionesController::class, 'crearOpcion'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::put('/opciones/{idOpcion}', [EvaluacionesController::class, 'actualizarOpcion'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));
Route::delete('/opciones/{idOpcion}', [EvaluacionesController::class, 'eliminarOpcion'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN]));

// ─── Respuestas ───────────────────────────────────────────
Route::post('/{idEvaluacion}/responder', [EvaluacionesController::class, 'enviarRespuesta'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN, Permiso::EVALUACIONES_REALIZAR]));
Route::get('/{idEvaluacion}/respuestas', [EvaluacionesController::class, 'listarRespuestas'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN, Permiso::EVALUACIONES_RESULTADOS, Permiso::EVALUACIONES_REALIZAR]));
Route::get('/respuestas/{idRespuesta}', [EvaluacionesController::class, 'obtenerRespuesta']);
Route::put('/respuestas/{idRespuesta}', [EvaluacionesController::class, 'actualizarRespuesta'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN, Permiso::EVALUACIONES_REALIZAR]));
Route::post('/respuestas/{idRespuesta}/reenviar-correo', [EvaluacionesController::class, 'reenviarCorreo'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN, Permiso::EVALUACIONES_REALIZAR]));
Route::get('/respuestas/{idRespuesta}/pdf', [EvaluacionesController::class, 'descargarPdf']);

// ─── Resultados ───────────────────────────────────────────
Route::get('/{idEvaluacion}/resultados', [EvaluacionesController::class, 'calcularResultados'])->middleware(Permiso::ruta([Permiso::EVALUACIONES_ADMIN, Permiso::EVALUACIONES_RESULTADOS]));
