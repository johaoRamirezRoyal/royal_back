<?php

namespace App\Http\Controllers\Usuarios;

use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

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
}