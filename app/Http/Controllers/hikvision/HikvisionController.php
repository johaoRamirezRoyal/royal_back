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

    public function obtenerEmpleadosRegistradosPorPerfil(Request $request){
        $id_perfil = $request->input("id_perfil");

        $usuarios = $this->usuario_services->mostrarUsuariosPorPerfil($id_perfil);

        if($usuarios['error']){
            return $this->apiResponse($usuarios);
        }

        $info_usuarios = $this->hikvision_service->obtenerEmpleadosRegistradosPorPerfil($usuarios['data']);

        return $this->apiResponse($info_usuarios);
    }

    public function registrarEmpleadosMasivoPerfil(Request $request){
        $id_perfil = $request->input("id_perfil");

        $usuarios = $this->usuario_services->mostrarUsuariosPorPerfil($id_perfil);

        if ($usuarios['error']) {
            return $this->apiResponse($usuarios);
        }

        $registro_masivo = $this->hikvision_service->registrarEmpleadosMasivo($usuarios['data']);

        return $this->apiResponse($registro_masivo);
    }

    public function eliminarUsuariosRegistrados(Request $request){
        $id_perfil = $request->input("id_perfil");

        $usuarios = $this->usuario_services->mostrarUsuariosPorPerfil($id_perfil);

        if($usuarios['error']){
            return $this->apiResponse($usuarios);
        }

        $eliminacion_masiva = $this->hikvision_service->eliminarUsuariosRegistrados($usuarios['data']);

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