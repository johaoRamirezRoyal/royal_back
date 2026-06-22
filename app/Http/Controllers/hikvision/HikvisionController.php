<?php
namespace App\Http\Controllers\Hikvision;

use App\Http\Controllers\Controller;
use App\Services\Hikvisionattendance\hikvisionattendanceService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class HikvisionController extends Controller
{
    protected hikvisionattendanceService $hikvision_service;
    protected UsuariosServices $usuario_services;

    public function __construct(hikvisionattendanceService $hikvisionService, UsuariosServices $usuariosServices)
    {
        $this->hikvision_service = $hikvisionService;
        $this->usuario_services = $usuariosServices;
    }


    public function testHikvisionConexion(){
        $informacion = $this->hikvision_service->testConnection();

        if($informacion['isConnected'] == false){
            return response()->json([
                'error' => true,
                'message' => "No se logró conectar a hikvision",
                'data' => null
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => "Conexión exitosa!",
            'data' => $informacion['data'],
        ]);
    }

    public function obtenerEmpleadosRegistrados(Request $request){
        $pageSize = $request->input('per-page', 30);

        $usuarios = $this->hikvision_service->obtenerEmpleadosRegistrados($pageSize);

        return $this->apiResponse($usuarios);
    }

    public function obtenerUnEmpleadoEspecifico(Request $request){
        $id_user = $request->input('id_user');

        if(!$id_user){
            return response()->json([
                'error' => true,
                'message' => "Debe proporcionar un id_user válido",
                'data' => []
            ]);
        }

        $datos_usuario = $this->hikvision_service->obtenerUnEmpleadoEspecifico($id_user);

        return $this->apiResponse($datos_usuario);
    }

    public function obtenerImagenEmpleado(Request $request){
        $path = $request->input('path');

        if(!$path){
            return response()->json([
                'error' => true,
                'message' => "Debe proporcionar la ruta de la imagen",
                'data' => null
            ], 400);
        }

        $resultado = $this->hikvision_service->obtenerImagenEmpleado($path);

        if($resultado['error']){
            return response()->json($resultado, 400);
        }

        return response($resultado['data']['contenido'])
            ->header('Content-Type', $resultado['data']['contentType'])
            ->header('Cache-Control', 'private, max-age=300');
    }

    public function obtenerAsistenciaEmpleado(Request $request){
        $id_empleado = $request->input("id_empleado");
        $start_date = $request->input("start_date", null);
        $end_date = $request->input("end_date", null);

        $response = $this->hikvision_service->obtenerAsistenciaEmpleado($id_empleado, $start_date, $end_date);

        return $this->apiResponse($response);
    }

    public function obtenerEmpleadosRegistradosPorPerfil(Request $request){
        $id_perfil = $request->input("id_perfil");

        $usuarios = $this->usuario_services->mostrarUsuariosPorPerfil($id_perfil);

        if($usuarios['error']){
            return $this->apiResponse($usuarios);
        }

        $info_usuarios = $this->hikvision_service->obtenerEmpleadosRegistradosPorPerfil($usuarios['data']);

        return $this->apiResponse($info_usuarios);
    }

    public function registrarEmpleado(Request $request){
        $data = $request->all();
        
        $validator = Validator::make($data, [
            'id_user' => ['required', 'integer', 'exists:usuarios,id_user'],
            'nombre' => ['required', 'string', 'max:30'],
            'apellido' => ['required', 'string', 'max:30'],
            'correo' => ['required', 'email', 'max:30'],
            'perfil' => ['required', 'integer', 'exists:perfiles,id'],
            'id_nivel' => ['required', 'integer', 'exists:niveles,id'],
            'id_curso' => ['required', 'integer', 'exists:cursos,id'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'id_grupo' => ['required', 'integer', 'exists:grupos,id'],
        ]);

        if($validator->fails()){
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
                ], 400);
        }

        $usuario = $this->hikvision_service->registrarEmpleado($data);

        return $this->apiResponse($usuario);
    }

    public function registrarEmpleadosMasivoPerfil(Request $request){
        $id_perfil = $request->input("id_perfil");

        $usuarios = $this->usuario_services->mostrarUsuariosPorPerfil($id_perfil);

        if ($usuarios['error']) {
            return $this->apiResponse($usuarios);
        }

        $usuariosPendientes = array_values(array_filter(
            $usuarios['data'],
            fn ($usuario) => empty($usuario['asistenciaRegistrada'])
        ));

        if (empty($usuariosPendientes)) {
            return $this->apiResponse([
                'error' => false,
                'message' => 'Todos los usuarios de este perfil ya estaban registrados en el dispositivo de asistencia',
                'data' => ['success' => [], 'error' => []],
            ]);
        }

        $registro_masivo = $this->hikvision_service->registrarEmpleadosMasivo($usuariosPendientes);

        if (!$registro_masivo['error']) {
            $idsExitosos = array_column($registro_masivo['data']['success'], 'id_user');
            $this->usuario_services->actualizarAsistenciaRegistrada($idsExitosos, true);
        }

        return $this->apiResponse($registro_masivo);
    }

    public function eliminarUsuariosRegistrados(Request $request){
        $id_perfil = $request->input("id_perfil");

        $usuarios = $this->usuario_services->mostrarUsuariosPorPerfil($id_perfil);

        Log::info("usuarios obtenidos", [
            "users" => $usuarios
        ]);

        if($usuarios['error']){
            return $this->apiResponse($usuarios);
        }

        $usuariosRegistrados = array_values(array_filter(
            $usuarios['data'],
            fn ($usuario) => !empty($usuario['asistenciaRegistrada'])
        ));

        if (empty($usuariosRegistrados)) {
            return $this->apiResponse([
                'error' => true,
                'message' => 'Ningún usuario de este perfil estaba registrado en el dispositivo de asistencia',
                'data' => [],
            ]);
        }

        $eliminacion_masiva = $this->hikvision_service->eliminarUsuariosRegistrados($usuariosRegistrados);

        if (!$eliminacion_masiva['error']) {
            $idsEliminados = array_column($usuariosRegistrados, 'id_user');
            $this->usuario_services->actualizarAsistenciaRegistrada($idsEliminados, false);
        }

        return $this->apiResponse($eliminacion_masiva);
    }

    public function desactivarUsuario(Request $request){
        $data = $request->all();

        $validator = Validator::make($data, [
            'id_user' => ['required', 'integer', 'exists:usuarios,id_user'],
            'enable' => ['required', 'integer', 'in:0,1'],
        ]);

        if($validator->fails()){
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
            ]);
        }

        $id_user = $request->input("id_user");
        $enable = $request->input("enable");

        $usuario = $this->usuario_services->mostrarInfoUsuarioId($id_user);
        Log::info("Usuario cargado", $usuario['usuario']->toArray());
        if($usuario['error']){
            return $this->apiResponse($usuario);
        }

        $desactivar = $this->hikvision_service->desactivarUsuario($usuario['usuario']->toArray(), $enable);

        return $this->apiResponse($desactivar);
    }
}