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
}