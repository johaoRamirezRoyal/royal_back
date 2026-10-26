<?php

use App\Http\Controllers\Hikvision\HikvisionController;
use App\Http\Controllers\Hikvision\HikvisionDispositivosController;
use App\Support\Permiso;
use Illuminate\Support\Facades\Route;

Route::post("/", [HikvisionController::class, 'registrarEmpleado']);
Route::post("/masivo", [HikvisionController::class, 'registrarEmpleadosMasivoPerfil']);
Route::post("/fingerprint/enroll", [HikvisionController::class, 'registrarHuellaEmpleado']);
Route::put("/fingerprint/delete", [HikvisionController::class, 'eliminarHuellaEmpleado']);
Route::post("/card/enroll", [HikvisionController::class, 'registrarTarjetaEmpleado']);
Route::post("/card/enroll/captura", [HikvisionController::class, 'registrarTarjetaEmpleadoCaptura']);
Route::put("/card/delete", [HikvisionController::class, 'eliminarTarjetaEmpleado']);
Route::post("/password/enroll", [HikvisionController::class, 'registrarContrasenaEmpleado']);
Route::post("/face/enroll", [HikvisionController::class, 'registrarRostroEmpleado']);
Route::post("/face/enroll/cancelar", [HikvisionController::class, 'cancelarRegistroRostro']);
Route::get('/testHikvision', [HikvisionController::class, 'testHikvisionConexion']);
Route::get('/devices', [HikvisionController::class, 'listarDispositivos']);
// Alta, edición y baja de terminales: solo Super Admin (también en el controller).
Route::middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]))->group(function () {
    Route::post('/devices', [HikvisionDispositivosController::class, 'crear']);
    Route::put('/devices/{id}', [HikvisionDispositivosController::class, 'actualizar'])->whereNumber('id');
    Route::delete('/devices/{id}', [HikvisionDispositivosController::class, 'eliminar'])->whereNumber('id');
});
Route::get('/httpHosts', [HikvisionController::class, 'obtenerHttpHosts']);
Route::get('/getList', [HikvisionController::class, 'obtenerEmpleadosRegistrados']);
Route::get('/image', [HikvisionController::class, 'obtenerImagenEmpleado']);
Route::get("/userInfo/perfil", [HikvisionController::class, 'obtenerEmpleadosRegistradosPorPerfil']);
Route::get("/userInfo", [HikvisionController::class, 'obtenerUnEmpleadoEspecifico']);
Route::delete("/", [HikvisionController::class, 'eliminarEmpleados']);
Route::delete("/perfil", [HikvisionController::class, 'eliminarUsuariosRegistrados']);
Route::put("/desactivar", [HikvisionController::class, 'desactivarUsuario']);
Route::get("/asistencia", [HikvisionController::class, 'obtenerAsistenciaEmpleado']);
