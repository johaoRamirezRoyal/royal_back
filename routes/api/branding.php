<?php

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
