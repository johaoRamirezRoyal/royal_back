<?php

namespace App\Http\Controllers\Usuarios;

use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator as FacadesValidator;

class UsuariosController extends Controller
{
    protected $service_usuarios;

    public function __construct(UsuariosServices $usuariosServices)
    {
        $this->service_usuarios = $usuariosServices;
    }

    //GET /usuarios
    public function mostrarTodosUsuariosActivos(){
        return response()->json(
            $this->service_usuarios->mostrarTodosUsuariosActivos(), 
            200
        );
    }

    //GET /usuariosPaginados
    public function mostrarTodosUsuariosActivoPaginado(Request $request){
        $per_page = $request->input('per-page', 10);

        return response()->json(
            $this->service_usuarios->mostrarTodosUsuariosActivoPaginado($per_page),
            200
        );
    }

    public function mostrarTodosUsuariosPaginado(Request $request){
        $per_page = $request->input('per-page', 10);

        return response()->json(
            $this->service_usuarios->mostrarTodosUsuariosPaginado($per_page),
            200
        );
    }

    public function mostrarTodosUsuarios(){
        return response()->json(
            $this->service_usuarios->mostrarTodosUsuarios(),
            200
        );
    }

    public function filtrarUsuarios(Request $request)
    {
        $datos = $request->all();
        $search = $request->input('s', '');
        $per_page = $request->input('per-page', 10);

        $filtro = $this->service_usuarios->filtrarUsuarios($datos, $search, $per_page);

        if ($filtro['error']) {
            return response()->json([
                'error' => true,
                'message' => $filtro['message']
            ], 500);
        }

        return response()->json([
            'error' => false,
            'data' => json_decode(json_encode($filtro['data'], JSON_INVALID_UTF8_SUBSTITUTE))
        ], 200);
    }

    public function tienePermiso(Request $request){
        $opcion = $request->input('opt');
        $perfil = $request->input('per');

        $permiso = $this->service_usuarios->tienePermiso($opcion, $perfil);
        $code = ($permiso['permiso']) ? 200:405;

        if($permiso['error']){
            return response()->json([
                'error'=> true,
                'message' => $permiso['message']
            ], $code);
        }
        
        return response()->json($permiso, $code);
    }

    public function mostrarInfoUsuarioId($id)
    {
        $usuario_id = $id;

        if (empty($usuario_id)) {
            return response()->json([
                'error' => true,
                'message' => 'Debe agregar el ID del usuario'
            ], 400);
        }

        $response = $this->service_usuarios->mostrarInfoUsuarioId($usuario_id);

        if ($response['error']) {
            return response()->json([
                'error' => true,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'error' => false,
            'message' => 'Usuario encontrado',
            'data' => $response['usuario']
        ]);
    }

    public function actualizarUsuarios(Request $request, $id){
        $usuario_id = $id;

        $validator = FacadesValidator::make($request->all(), [
            "documento" => 'required|numeric',
            "nombre" => 'required|string',
            "apellido" => 'nullable|string',
            "correo" => 'required|email|ends_with:@royalschool.edu.co|unique:usuarios,correo',
            'perfil' => 'required|integer',
            'id_nivel' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message'=> $validator->errors()->first(),
            ]);
        }

        if(empty($usuario_id)){
            return response()->json([
                'error'=> true,
                'message'=> 'Debes insertar el id del usuario',
            ]);
        }

        $response = $this->service_usuarios->actualizarUsuarios($id, $request->all());

        if ($response['error']) {
            return response()->json([
                'error' => true,
                'message' => $response['message'],
            ]);
        }

        return response()->json([
            'error'=> false,
            'message' => 'Usuario actualizado',
            'data' => $response['usuario'],
        ]);
    }
}