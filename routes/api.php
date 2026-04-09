<?php

use App\Http\Controllers\Areas\AreasController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Cursos\CursosController;
use App\Http\Controllers\Permisos\PermisosController;
use App\Http\Controllers\Usuarios\UsuariosController;
use App\Models\Usuario;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 🔓 RUTAS PÚBLICAS (sin token)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::get('/check', [AuthController::class, 'check']);
});

// RUTAS PROTEGIDAS
Route::middleware('auth:api')->group(function () {

    // AUTH
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
    });

    // USUARIOS
    Route::prefix('usuarios')->group(function () {
        Route::get('/permiso', [UsuariosController::class, 'tienePermiso']);
        Route::get('/filtro', [UsuariosController::class, 'filtrarUsuarios']);

        Route::get('/all/activos', [UsuariosController::class, 'mostrarTodosUsuariosActivos']);
        Route::get('/all/general', [UsuariosController::class, 'mostrarTodosUsuariosPaginado']);
        Route::get('/', [UsuariosController::class, 'mostrarTodosUsuariosActivoPaginado']);
        Route::get('/all', [UsuariosController::class, 'mostrarTodosUsuarios']);

        Route::get('/{id}', [UsuariosController::class, 'mostrarInfoUsuarioId'])->where('id', '[0-9]+');

        Route::put('/{id}', [UsuariosController::class, 'actualizarUsuarios']);
        Route::post('/', [UsuariosController::class, 'agregarUsuario']);
        /**
         * Ejemplo de JSON para agregar usuario:
         * 
         *
        {
            "documento": 10203040,
            "nombre": "Pepito",
            "apellido": "Pérez",
            "correo": "pepito_perez@royalschool.edu.co",
            "perfil": 2,
            "id_nivel": 1,
            "user": "aperez2026",
            "pass": "secret123",
            "grupo": 1,
            "curso": 5
        }
         */
    });

    Route::prefix('cursos')->group(function () {
        Route::get('/all', [CursosController::class, 'findAll']);
    });

    Route::prefix('permisos')->group(function () {
        Route::get('/listado', [PermisosController::class, 'verPermisosPorPerfil']);
        Route::get('/opciones', [PermisosController::class, 'verTodosLosPermisosOpciones']);
    });

    Route::prefix('areas')->group(function () {
        Route::get('/', [AreasController::class, 'obtenerTodasLasAreas']);
    });
});
