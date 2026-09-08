<?php

use App\Http\Controllers\Certificados\CertificadosController;
use Illuminate\Support\Facades\Route;

// Autoservicio: cualquier usuario autenticado
Route::get('/mis-solicitudes', [CertificadosController::class, 'misSolicitudes']);
Route::post('/', [CertificadosController::class, 'crear']);

// Gestión Humana (opción 25)
Route::get('/', [CertificadosController::class, 'listar']);
Route::post('/{id}/documento', [CertificadosController::class, 'subirDocumento']);
