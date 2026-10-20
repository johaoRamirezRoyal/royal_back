<?php

use App\Http\Controllers\Instituciones\InstitucionAdminController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

Route::get('/', [InstitucionAdminController::class, 'index'])->middleware(Permiso::ruta([Permiso::INSTITUCIONES_GESTION, Permiso::INSTITUCIONES_LECTURA]));
Route::post('/', [InstitucionAdminController::class, 'store'])->middleware(Permiso::ruta([Permiso::INSTITUCIONES_GESTION]));
Route::put('/estado', [InstitucionAdminController::class, 'cambiarEstado'])->middleware(Permiso::ruta([Permiso::INSTITUCIONES_GESTION]));
// Antes del wildcard /{id} — si no, "configuracion" se interpretaría como un id.
Route::get('/configuracion', [InstitucionAdminController::class, 'configuracion'])->middleware(Permiso::ruta([Permiso::INSTITUCIONES_GESTION]));
Route::put('/configuracion', [InstitucionAdminController::class, 'actualizarConfiguracion'])->middleware(Permiso::ruta([Permiso::INSTITUCIONES_GESTION]));
Route::put('/{id}', [InstitucionAdminController::class, 'update'])->middleware(Permiso::ruta([Permiso::INSTITUCIONES_GESTION]));
Route::get('/{id}/cartas', [InstitucionAdminController::class, 'cartas'])->middleware(Permiso::ruta([Permiso::INSTITUCIONES_GESTION, Permiso::INSTITUCIONES_LECTURA]));
Route::post('/{id}/recordatorio', [InstitucionAdminController::class, 'enviarRecordatorio'])->middleware(Permiso::ruta([Permiso::INSTITUCIONES_GESTION]));
Route::delete('/{institucionId}/cartas/{cartaId}', [InstitucionAdminController::class, 'eliminarCarta'])->middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]));
