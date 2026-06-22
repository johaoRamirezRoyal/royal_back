<?php

use App\Http\Controllers\Hikvision\HikvisionController;
use Illuminate\Support\Facades\Route;

Route::post("/", [HikvisionController::class, 'registrarEmpleadosMasivoPerfil']);
Route::get('/testHikvision', [HikvisionController::class, 'testHikvisionConexion']);
Route::get('/getList', [HikvisionController::class, 'obtenerEmpleadosRegistrados']);
Route::get("/userInfo/perfil", [HikvisionController::class, 'obtenerEmpleadosRegistradosPorPerfil']);
Route::get("/userInfo", [HikvisionController::class, 'obtenerUnEmpleadoEspecifico']);
Route::delete("/perfil", [HikvisionController::class, 'eliminarUsuariosRegistrados']);
Route::put("/desactivar", [HikvisionController::class, 'desactivarUsuario']);
Route::get("/asistencia", [HikvisionController::class, 'obtenerAsistenciaEmpleado']);
