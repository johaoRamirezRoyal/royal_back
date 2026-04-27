<?php

use App\Http\Controllers\Areas\AreasController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Categorias\CategoriasController;
use App\Http\Controllers\Cursos\CursosController;
use App\Http\Controllers\Inventarios\InventariosController;
use App\Http\Controllers\PasswordReset\PasswordResetController;
use App\Http\Controllers\Permisos\PermisosController;
use App\Http\Controllers\Usuarios\UsuariosController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Bienvenido a la API de Royal School',
        'version' => '1.0',
        'status' => 'success',
    ]);
});

// 🔓 RUTAS PÚBLICAS (sin token)
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::get('/check', [AuthController::class, 'check']);

    Route::prefix('password')->group(function () {
        Route::post('restore', [PasswordResetController::class, 'createToken']);
    });
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
        Route::get('/perfiles', [UsuariosController::class, 'mostrarTodosPerfiles']);
        Route::get('/niveles', [UsuariosController::class, 'mostrarTodosNiveles']);

        Route::get('/all/activos', [UsuariosController::class, 'mostrarTodosUsuariosActivos']);
        Route::get('/all/general', [UsuariosController::class, 'mostrarTodosUsuariosPaginado']);
        Route::get('/', [UsuariosController::class, 'mostrarTodosUsuariosActivoPaginado']);
        Route::get('/all', [UsuariosController::class, 'mostrarTodosUsuarios']);

        Route::get('/{id}', [UsuariosController::class, 'mostrarInfoUsuarioId'])->where('id', '[0-9]+');

        Route::put('/estado', [UsuariosController::class, 'actualizarEstadoUsuarios']);
        /**
         * Ejemplo de JSON para actualizar estado de varios usuarios:
        {
        "ids": [
                11,
                12,
                13
            ],
        "estado": "activo"
        }
         */
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

    // CURSOS
    Route::prefix('cursos')->group(function () {
        Route::get('/all', [CursosController::class, 'findAll']);
    });

    // PERMISOS
    Route::prefix('permisos')->group(function () {
        Route::get('/listado', [PermisosController::class, 'verPermisosPorPerfil']);
        Route::get('/opciones', [PermisosController::class, 'verTodosLosPermisosOpciones']);
    });

    // AREAS
    Route::prefix('areas')->group(function () {
        Route::put('/', [AreasController::class, 'actualizarArea']);
        /**
         * Ejemplo de JSON para actualizar un area:
        {
            "id": 1,
            "nombre": "S40 (1B)",
            "user_log": 1,
            "activo": 1
        }
         */
        Route::get('/filtro', [AreasController::class, 'filtrarAreas']);
        /*
            Filtrada area
            http://localhost:8000/api/areas/filtro?filtro=filtro_a_buscar
        */

        Route::post('/asignar', [AreasController::class, 'asignarArea']);
        /**
         * Ejemplo de JSON para asignar un area a un usuario:
            {
                "id_user": 3123,
                "id_area": 104
            }
         */
        Route::post('/estado', [AreasController::class, 'desactivarAreas']);
        /**
         * Ejemplo JSON para cambiar el estado del area:
        {
            "ids": [
                    1, 2, 3, 4, 5
                    ],
            "estado": 1 -> activo | 0 -> inactivo
        }
         */
        Route::get('/', [AreasController::class, 'obtenerTodasLasAreas']);
    });

    // INVENTARIO
    Route::prefix('inventario')->group(function () {
        Route::get('/listado', [InventariosController::class, 'obtenerListadoInventario']);
        /**
         *  Ejemplo de JSON para obtener el listado de inventario paginado:
            {
                "search": "computado",
                "id_categoria": [],
                "estado": [],
                "id_usuario": 3123,
                "per-page": 20
            }

         * URL Puede ser:
            http://localhost:8000/api/inventario/listado?page=300&per_page=10
         */
        Route::put('/descontinuar', [InventariosController::class, 'descontinuarInventario']);
        Route::post('/', [InventariosController::class, 'agregarInventario']);
        Route::put('/liberar', [InventariosController::class, 'liberarInventario']);
    });

    // CATEGORIAS
    Route::prefix('categorias')->group(function () {
        Route::get('/', [CategoriasController::class, 'obtenerTodasLasCategorias']);
        Route::post('/', [CategoriasController::class, 'agregarNuevaCategoria']);
        Route::put('/', [CategoriasController::class, 'actualizarCategoria']);
    });

});
