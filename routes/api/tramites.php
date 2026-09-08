<?php

use App\Http\Controllers\Tramites\TramitesController;
use Illuminate\Support\Facades\Route;

// Catálogos (sin gate, necesarios en el formulario de solicitud)
Route::get('/tipos', [TramitesController::class, 'tipos']);
Route::get('/grupos-familiares', [TramitesController::class, 'gruposFamiliares']);
Route::get('/eps', [TramitesController::class, 'eps']);

// Autoservicio: cualquier usuario autenticado
Route::get('/mis-tramites', [TramitesController::class, 'misTramites']);
Route::post('/', [TramitesController::class, 'crear']);

// Gestión (opción 77)
Route::get('/', [TramitesController::class, 'listar']);
Route::get('/{id}', [TramitesController::class, 'detalle']);
Route::post('/{id}/finalizar', [TramitesController::class, 'finalizar']);
Route::post('/{id}/rechazar', [TramitesController::class, 'rechazar']);
