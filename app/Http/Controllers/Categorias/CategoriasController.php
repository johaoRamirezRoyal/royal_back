<?php
namespace App\Http\Controllers\Categorias;

use App\Http\Controllers\Controller;
use App\Services\Categorias\CategoriasServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoriasController extends Controller{
    protected $categoria_services;

    public function __construct(CategoriasServices $categoriasServices)
    {
        $this->categoria_services = $categoriasServices;
    }

    public function obtenerTodasLasCategorias(){
        $categoria = $this->categoria_services->obtenerTodasLasCategorias();
        $code = match (true) {
            $categoria['error'] && str_contains($categoria['message'], 'SQL') => 500,
            $categoria['error'] => 400,
            default => 200,
        };

        return response()->json($categoria, $code);
    }

    public function agregarNuevaCategoria(Request $request){
        $datos = $request->all();

        $validator = Validator::make($datos,[
            "nombre" => "required|string",
            "tipo_categoria" => "required|numeric",
            "activo" => "numeric",
        ]); 

        if($validator->fails()){
            return response()->json([
                    "error" => true,
                    "message" => $validator->errors()->first(), 
            ], 422);
        }

        $categoria = $this->categoria_services->agregarNuevaCategoria($datos);
        
        $status = match (true) {
            $categoria['error'] && str_contains($categoria['message'], "SQL") => 500,
            $categoria['error'] => 400,
            default => 200,
        };

        return response()->json($categoria, $status);
    }

    public function actualizarCategoria(Request $request){
        $datos = $request->except("ids");
        $ids = $request->input("ids");

        $validator = Validator::make($request->all(), [
            "ids" => "required|array|min:1",
            "ids.*" => "integer|distinct|exists:categoria,id",

            "nombre" => "sometimes|string",
            "tipo_categoria" => "sometimes|numeric",
            "activo" => "sometimes|numeric",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "error" => true,
                "message" => $validator->errors()->first(),
            ], 422);
        }

        $actualizacion = $this->categoria_services->actualizarCategoria($datos, $ids);
        $status = match (true) {
            $actualizacion['error'] && str_contains($actualizacion['message'], "SQL") => 500,
            $actualizacion['error'] => 400,
            default => 200,
        };

        return response()->json($actualizacion, $status);
    }
}