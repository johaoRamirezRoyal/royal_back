<?php

use App\Http\Controllers\Usuarios\UsuariosController;
use Illuminate\Support\Facades\Route;

Route::get('/firma', [UsuariosController::class, 'verFirma']);
Route::post('/firma', [UsuariosController::class, 'subirFirma']);

Route::get('/permiso', [UsuariosController::class, 'tienePermiso']);
Route::get('/filtro', [UsuariosController::class, 'filtrarUsuarios']);
Route::get('/perfiles', [UsuariosController::class, 'mostrarTodosPerfiles']);
Route::get('/niveles', [UsuariosController::class, 'mostrarTodosNiveles']);

Route::get('/all/activos', [UsuariosController::class, 'mostrarTodosUsuariosActivos']);
Route::get('/all/general', [UsuariosController::class, 'mostrarTodosUsuariosPaginado']);
Route::get('/', [UsuariosController::class, 'mostrarTodosUsuariosActivoPaginado']);
Route::get('/all', [UsuariosController::class, 'mostrarTodosUsuarios']);
Route::get('/paginados', [UsuariosController::class, 'mostrarUsuariosPaginados']);

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
Route::get('inscripcionesUsuarios', [UsuariosController::class, 'mostrarUsuariosConInscripciones']);
