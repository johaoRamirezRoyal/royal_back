<?php

namespace App\Http\Controllers\Inventarios;

use App\Http\Controllers\Controller;
use App\Services\inventario\InventarioServices as InventarioServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventariosController extends Controller
{
    protected $inventario_services;

    public function __construct(InventarioServices $inventarioServices)
    {
        $this->inventario_services = $inventarioServices;
    }

    public function agregarInventario(Request $request){
        $validator = Validator::make($request->all(),[
            "descripcion" => "required|string",
            "marca" => "string",
            "modelo" => "string",
            "precio" => "integer",
            "estado" => "required|integer",
            "id_usuario" => "required|integer",
            "activo" => "required|integer",
            "fecha_compra" => "date",
            "id_area" => "required|integer",
            "id_categoria" => "required|integer",
            "id_compra" => "integer"
        ]);

        if($validator->fails()){
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first()
            ]);
        }

        $inventario_data = $request->only([
            "descripcion",
            "marca",
            "modelo",
            "precio",
            "estado",
            "id_usuario",
            "activo",
            "fecha_compra",
            "id_area",
            "id_categoria",
            "id_compra"
        ]);

        $agregar = $this->inventario_services->agregarInventario($inventario_data);

        if($agregar['error']){
            return response()->json([
                'error' => true,
                'message' => $agregar['message'],
                'data' => $agregar['data']
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => "Se ha agregado el articulo al inventario",
            "data" => $agregar['data']
        ]);
    }

    public function obtenerListadoInventario(Request $request){
        $per_page = $request->input('per-page', 10); // Número de elementos por página, por defecto 10
        $search = $request->input('search', null);
        $datos = $request->only(['id_area', 'id_categoria', 'estado', 'id_usuario']);
        $listado_inventario = $this->inventario_services->obtenerListadoInventario($per_page, $search, $datos);

        if($listado_inventario['error']){
            return response()->json([
                'error' => true,
                'message' => $listado_inventario['message'],
                'data' => $listado_inventario['data']
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => $listado_inventario['message'],
            'data' => $listado_inventario['data']
        ]);
    }
}