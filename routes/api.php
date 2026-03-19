<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Usuarios\UsuariosController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('usuarios')->group(function () {
    Route::get('/all', [UsuariosController::class, 'mostrarTodosUsuariosActivos']);
    Route::get('/', [UsuariosController::class, 'mostrarTodosUsuariosActivoPaginado']);
});

Route::prefix('auth')->group(function (){
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
});