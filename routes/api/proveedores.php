<?php

use App\Http\Controllers\ProcesoCompra\ProveedoresController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProveedoresController::class, 'listar']);
Route::get('/tipos-documento', [ProveedoresController::class, 'listarTiposDocumento']);
Route::get('/{id}', [ProveedoresController::class, 'ver'])->where('id', '[0-9]+');
Route::post('/', [ProveedoresController::class, 'crear']);
Route::put('/{id}', [ProveedoresController::class, 'actualizar'])->where('id', '[0-9]+');
Route::put('/{id}/estado', [ProveedoresController::class, 'cambiarEstado'])->where('id', '[0-9]+');

Route::get('/{id}/documentos', [ProveedoresController::class, 'listarDocumentos'])->where('id', '[0-9]+');
Route::post('/{id}/documentos', [ProveedoresController::class, 'subirDocumento'])->where('id', '[0-9]+');

Route::put('/documentos/{docId}', [ProveedoresController::class, 'actualizarDocumento'])->where('docId', '[0-9]+');
Route::post('/documentos/{docId}', [ProveedoresController::class, 'actualizarDocumento'])->where('docId', '[0-9]+');
Route::put('/documentos/{docId}/estado', [ProveedoresController::class, 'cambiarEstadoDocumento'])->where('docId', '[0-9]+');
Route::delete('/documentos/{docId}', [ProveedoresController::class, 'eliminarDocumento'])->where('docId', '[0-9]+');