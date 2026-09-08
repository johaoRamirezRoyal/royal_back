<?php

use App\Http\Controllers\Recursos\RecursosDocumentosController;
use Illuminate\Support\Facades\Route;

// Catálogo de tipos de proceso
Route::get('/procesos', [RecursosDocumentosController::class, 'procesos']);
Route::get('/procesos/cantidades', [RecursosDocumentosController::class, 'cantidades']);

// Listado Maestro de documentos
Route::get('/documentos', [RecursosDocumentosController::class, 'listar']);
Route::post('/documentos', [RecursosDocumentosController::class, 'crear']);
Route::match(['put', 'post'], '/documentos/{id}', [RecursosDocumentosController::class, 'actualizar']);
Route::delete('/documentos/{id}', [RecursosDocumentosController::class, 'eliminar']);
