<?php

use App\Http\Controllers\Instituciones\InstitucionAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', [InstitucionAdminController::class, 'index']);
Route::post('/', [InstitucionAdminController::class, 'store']);
Route::put('/estado', [InstitucionAdminController::class, 'cambiarEstado']);
// Antes del wildcard /{id} — si no, "configuracion" se interpretaría como un id.
Route::get('/configuracion', [InstitucionAdminController::class, 'configuracion']);
Route::put('/configuracion', [InstitucionAdminController::class, 'actualizarConfiguracion']);
Route::put('/{id}', [InstitucionAdminController::class, 'update']);
Route::get('/{id}/cartas', [InstitucionAdminController::class, 'cartas']);
