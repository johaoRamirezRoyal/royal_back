<?php

use App\Http\Controllers\Branding\ColegioAdmisionController;
use App\Http\Controllers\Branding\MarcaController;
use Illuminate\Support\Facades\Route;

/**
 * Marcas: identidad de cada institución (nombre, lema, color, logos, imagen de fondo) y sus
 * dominios de correo. Super Admin únicamente (ver MarcaController). La marca resuelta se
 * consume vía UsuarioResource (GET /api/auth/check), los documentos generados en el servidor
 * (DocumentoPdfService, HorarioExcelService) y el branding por slug de /api/admissions.
 */
Route::prefix('marcas')->group(function () {
    Route::get('/', [MarcaController::class, 'listar']);
    Route::post('/', [MarcaController::class, 'crear']);
    Route::put('/', [MarcaController::class, 'actualizar']);
    Route::put('/estado', [MarcaController::class, 'cambiarEstado']);
    Route::delete('/', [MarcaController::class, 'eliminar']);
});

// Solo lectura: dominio + nombre de su marca (alcance por colegio de los banners informativos).
Route::get('dominios', [MarcaController::class, 'listarDominios']);

/**
 * Colegios por slug de URL (slug -> connection + marca, Super Admin únicamente — ver
 * ColegioAdmisionController). La resolución pública por slug (branding + selección de
 * connection) vive aparte, bajo /api/admissions — ver routes/api.php y ResolveColegioAdmision.
 */
Route::prefix('colegios-admision')->group(function () {
    Route::get('/', [ColegioAdmisionController::class, 'listar']);
    Route::post('/', [ColegioAdmisionController::class, 'crear']);
    Route::put('/', [ColegioAdmisionController::class, 'actualizar']);
    Route::put('/estado', [ColegioAdmisionController::class, 'cambiarEstado']);
    Route::delete('/', [ColegioAdmisionController::class, 'eliminar']);
});
