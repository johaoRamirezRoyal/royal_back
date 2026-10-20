<?php

use App\Http\Controllers\Encuestas\EncuestasController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

// ─── Tipos de pregunta (catálogo, reusa el de Evaluaciones) ───
Route::get('/tipos-pregunta', [EncuestasController::class, 'listarTiposPregunta'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN, Permiso::ENCUESTAS_RESULTADOS]));

// ─── Salones ────────────────────────────────────────────────
Route::get('/salones', [EncuestasController::class, 'listarSalones'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN, Permiso::ENCUESTAS_RESULTADOS]));
// Antes del {idSalon} de abajo: guarda de una sola vez todas las asignaciones hechas en
// la pestaña Salones y QR (botón "Guardar cambios"), 1 sola solicitud en vez de 1 por salón.
Route::post('/salones/asignar-lote', [EncuestasController::class, 'asignarEncuestasLote'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));
Route::put('/salones/{idSalon}', [EncuestasController::class, 'asignarEncuestaSalon'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));
Route::post('/salones/{idSalon}/regenerar-token', [EncuestasController::class, 'regenerarTokenSalon'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));

// ─── Encuestas ──────────────────────────────────────────────
Route::get('/', [EncuestasController::class, 'listar'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN, Permiso::ENCUESTAS_RESULTADOS]));
Route::post('/', [EncuestasController::class, 'crear'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));
Route::get('/{id}', [EncuestasController::class, 'obtenerPorId'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN, Permiso::ENCUESTAS_RESULTADOS]));
Route::put('/{id}', [EncuestasController::class, 'actualizar'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));
Route::delete('/{id}', [EncuestasController::class, 'eliminar'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));
Route::put('/{id}/toggle-activo', [EncuestasController::class, 'toggleActivo'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));
Route::post('/{id}/duplicar', [EncuestasController::class, 'duplicar'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));
Route::get('/{id}/resultados', [EncuestasController::class, 'resultados'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN, Permiso::ENCUESTAS_RESULTADOS]));
Route::get('/{id}/salones-con-respuestas', [EncuestasController::class, 'salonesConRespuestas'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN, Permiso::ENCUESTAS_RESULTADOS]));
Route::get('/{id}/reservas-con-respuestas', [EncuestasController::class, 'reservasConRespuestas'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN, Permiso::ENCUESTAS_RESULTADOS]));

// ─── Preguntas ──────────────────────────────────────────────
Route::post('/{idEncuesta}/preguntas', [EncuestasController::class, 'crearPregunta'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));
Route::put('/preguntas/{idPregunta}', [EncuestasController::class, 'actualizarPregunta'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));
Route::delete('/preguntas/{idPregunta}', [EncuestasController::class, 'eliminarPregunta'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));
Route::put('/{id}/preguntas/orden', [EncuestasController::class, 'reordenarPreguntas'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));

// ─── Opciones ───────────────────────────────────────────────
Route::post('/preguntas/{idPregunta}/opciones', [EncuestasController::class, 'crearOpcion'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));
Route::put('/opciones/{idOpcion}', [EncuestasController::class, 'actualizarOpcion'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));
Route::delete('/opciones/{idOpcion}', [EncuestasController::class, 'eliminarOpcion'])->middleware(Permiso::ruta([Permiso::ENCUESTAS_ADMIN]));
