<?php

use App\Http\Controllers\ProcesoCompra\ProveedoresController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

Route::get('/', [ProveedoresController::class, 'listar']);
Route::get('/select', [ProveedoresController::class, 'listarParaSelect']);
Route::get('/tipos-documento', [ProveedoresController::class, 'listarTiposDocumento'])->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::get('/{id}', [ProveedoresController::class, 'ver'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::get('/{id}/compras', [ProveedoresController::class, 'listarCompras'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::COMPRAS_LISTADO_SOLICITUDES, Permiso::PROVEEDORES]));
Route::post('/', [ProveedoresController::class, 'crear'])->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::put('/{id}', [ProveedoresController::class, 'actualizar'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::put('/{id}/estado', [ProveedoresController::class, 'cambiarEstado'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));

Route::get('/{id}/documentos', [ProveedoresController::class, 'listarDocumentos'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::post('/{id}/documentos', [ProveedoresController::class, 'subirDocumento'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));

Route::put('/documentos/{docId}', [ProveedoresController::class, 'actualizarDocumento'])->where('docId', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::post('/documentos/{docId}', [ProveedoresController::class, 'actualizarDocumento'])->where('docId', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::put('/documentos/{docId}/estado', [ProveedoresController::class, 'cambiarEstadoDocumento'])->where('docId', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::delete('/documentos/{docId}', [ProveedoresController::class, 'eliminarDocumento'])->where('docId', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));

Route::get('/{id}/contactos', [ProveedoresController::class, 'listarContactos'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::post('/{id}/contactos', [ProveedoresController::class, 'crearContacto'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));

Route::put('/contactos/{contactoId}', [ProveedoresController::class, 'actualizarContacto'])->where('contactoId', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::put('/contactos/{contactoId}/estado', [ProveedoresController::class, 'cambiarEstadoContacto'])->where('contactoId', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::delete('/contactos/{contactoId}', [ProveedoresController::class, 'eliminarContacto'])->where('contactoId', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));

Route::get('/{id}/bancos', [ProveedoresController::class, 'listarBancos'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::post('/{id}/bancos', [ProveedoresController::class, 'crearBanco'])->where('id', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));

Route::put('/bancos/{bancoId}', [ProveedoresController::class, 'actualizarBanco'])->where('bancoId', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::put('/bancos/{bancoId}/estado', [ProveedoresController::class, 'cambiarEstadoBanco'])->where('bancoId', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));
Route::delete('/bancos/{bancoId}', [ProveedoresController::class, 'eliminarBanco'])->where('bancoId', '[0-9]+')->middleware(Permiso::ruta([Permiso::PROVEEDORES]));