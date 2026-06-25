<?php

use App\Http\Controllers\Hikvision\HikvisionController;
use Illuminate\Support\Facades\Route;

Route::post("/", [HikvisionController::class, 'registrarEmpleado']);
Route::post("/masivo", [HikvisionController::class, 'registrarEmpleadosMasivoPerfil']);
Route::post("/fingerprint/enroll", [HikvisionController::class, 'registrarHuellaEmpleado']);
Route::put("/fingerprint/delete", [HikvisionController::class, 'eliminarHuellaEmpleado']);
Route::post("/password/enroll", [HikvisionController::class, 'registrarContrasenaEmpleado']);
Route::post("/face/enroll", [HikvisionController::class, 'registrarRostroEmpleado']);
Route::get('/testHikvision', [HikvisionController::class, 'testHikvisionConexion']);
Route::get('/httpHosts', [HikvisionController::class, 'obtenerHttpHosts']);
Route::get('/getList', [HikvisionController::class, 'obtenerEmpleadosRegistrados']);
Route::get('/image', [HikvisionController::class, 'obtenerImagenEmpleado']);
Route::get("/userInfo/perfil", [HikvisionController::class, 'obtenerEmpleadosRegistradosPorPerfil']);
Route::get("/userInfo", [HikvisionController::class, 'obtenerUnEmpleadoEspecifico']);
Route::delete("/perfil", [HikvisionController::class, 'eliminarUsuariosRegistrados']);
Route::put("/desactivar", [HikvisionController::class, 'desactivarUsuario']);
Route::get("/asistencia", [HikvisionController::class, 'obtenerAsistenciaEmpleado']);