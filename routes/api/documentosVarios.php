<?php

use App\Http\Controllers\DocumentosVarios\DocumentosVariosController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DocumentosVariosController::class, 'obtenerDocumentosPorUsuario']);
Route::get('/porTipo', [DocumentosVariosController::class, 'obtenerDocumentosPorTipo']);
Route::get('/conteo', [DocumentosVariosController::class, 'contarDocumentosPorTipo']);
Route::post('/', [DocumentosVariosController::class, 'crearDocumento']);
Route::put('/', [DocumentosVariosController::class, 'actualizarDocumento']);
Route::delete('/', [DocumentosVariosController::class, 'eliminarDocumento']);
Route::delete('/usuario', [DocumentosVariosController::class, 'eliminarDocumentosPorUsuario']);
Route::delete('/tipo', [DocumentosVariosController::class, 'eliminarDocumentosPorTipo']);
