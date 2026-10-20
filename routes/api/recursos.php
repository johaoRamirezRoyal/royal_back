<?php

use App\Http\Controllers\Recursos\RecursosDocumentosController;
use Illuminate\Support\Facades\Route;
use App\Support\Permiso;

// Catálogo de tipos de proceso
Route::get('/procesos', [RecursosDocumentosController::class, 'procesos']);
Route::get('/procesos/cantidades', [RecursosDocumentosController::class, 'cantidades'])->middleware(Permiso::ruta([Permiso::LISTADO_MAESTRO]));

// Listado Maestro de documentos
Route::get('/documentos', [RecursosDocumentosController::class, 'listar']);
Route::post('/documentos', [RecursosDocumentosController::class, 'crear'])->middleware(Permiso::ruta([Permiso::LISTADO_MAESTRO]));
Route::match(['put', 'post'], '/documentos/{id}', [RecursosDocumentosController::class, 'actualizar'])->middleware(Permiso::ruta([Permiso::LISTADO_MAESTRO]));
Route::delete('/documentos/{id}', [RecursosDocumentosController::class, 'eliminar'])->middleware(Permiso::ruta([Permiso::LISTADO_MAESTRO]));
