<?php

use App\Http\Controllers\Certificados\CertificadosController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

// Autoservicio: cualquier usuario autenticado
Route::get('/mis-solicitudes', [CertificadosController::class, 'misSolicitudes']);
Route::post('/', [CertificadosController::class, 'crear']);

// Gestión Humana (opción 25)
Route::get('/', [CertificadosController::class, 'listar'])->middleware(Permiso::ruta([Permiso::CERTIFICADOS_LISTADO]));
Route::post('/{id}/documento', [CertificadosController::class, 'subirDocumento'])->middleware(Permiso::ruta([Permiso::CERTIFICADOS_LISTADO]));
