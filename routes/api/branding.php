<?php

use App\Http\Controllers\Branding\ColegioAdmisionController;
use App\Http\Controllers\Branding\MarcaDominioController;
use Illuminate\Support\Facades\Route;

/**
 * Administración del logo por dominio de correo (Super Admin únicamente — ver
 * MarcaDominioController::PERFILES_PERMITIDOS). El logo resuelto en sí se consume
 * indirectamente vía UsuarioResource (GET /api/auth/check) y en los documentos generados
 * server-side (PazYSalvoPdfService, HorarioExcelService) — no hace falta un endpoint de
 * "resolver" público separado.
 */
Route::prefix('dominios')->group(function () {
    Route::get('/', [MarcaDominioController::class, 'listar']);
    Route::post('/', [MarcaDominioController::class, 'crear']);
    Route::put('/', [MarcaDominioController::class, 'actualizar']);
    Route::put('/estado', [MarcaDominioController::class, 'cambiarEstado']);
    Route::delete('/', [MarcaDominioController::class, 'eliminar']);
});

/**
 * CRUD de colegios de admisión (slug de URL -> connection + marca, Super Admin únicamente
 * — ver ColegioAdmisionController::PERFILES_PERMITIDOS). La resolución pública por slug
 * (branding + selección de connection) vive aparte, bajo /api/admissions — ver
 * routes/api.php y ResolveColegioAdmision.
 */
Route::prefix('colegios-admision')->group(function () {
    Route::get('/', [ColegioAdmisionController::class, 'listar']);
    Route::post('/', [ColegioAdmisionController::class, 'crear']);
    Route::put('/', [ColegioAdmisionController::class, 'actualizar']);
    Route::put('/estado', [ColegioAdmisionController::class, 'cambiarEstado']);
    Route::delete('/', [ColegioAdmisionController::class, 'eliminar']);
});
