<?php

use App\Http\Controllers\TipoDocumento\TipoDocumentoController;
use Illuminate\Support\Facades\Route;

Route::get('/tipoDocumento', [TipoDocumentoController::class, 'obtenerTipoDocumentoPorId']);
Route::get('/tiposDocumentos', [TipoDocumentoController::class, 'obtenerTiposDocumentos']);