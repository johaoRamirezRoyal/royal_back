<?php

namespace App\Http\Controllers\Areas;

use App\Http\Controllers\Controller;
use App\Services\Areas\AreasServices as AreasAreasServices;
use Illuminate\Http\Request;

class AreasController extends Controller
{
    protected $service_areas;

    public function __construct(AreasAreasServices $areasServices)
    {
        $this->service_areas = $areasServices;
    }

    public function crearArea(Request $request){
        $datos = $request->all();
        $area_nueva = $this->service_areas->crearArea($datos);

        if($area_nueva['error']){
            return response()->json([
                'error' => true,
                'message'=> $area_nueva['message']
            ]);
        }

        return response()->json([
            'error' => false,
            'data' => $area_nueva['data']
        ]);
    }

    public function actualizarArea(Request $request) {
        $id = $request->input('id');
        $datos = $request->except('id');

        $actualizacion = $this->service_areas->actualizarArea($id, $datos);

        if($actualizacion['error']){
            return response()->json([
                'error' => true,
                'message' => $actualizacion['message']
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => $actualizacion['message']
        ]);
    }

    public function filtrarAreas(Request $request){
        $filtro = $request->input('filtro');

        $areas_filtradas = $this->service_areas->filtrarAreas($filtro);

        if ($areas_filtradas['error']){
            return response()->json([
                'error' => true,
                'message' => $areas_filtradas['message'],
                'data' => null
             ]);
        }

        return response()->json([
            'error' => false,
            'message' => $areas_filtradas['message'],
            'data' => $areas_filtradas['data']
        ]);
    }

    public function obtenerTodasLasAreas(){
        $areas = $this->service_areas->obtenerTodasLasAreas();

        if ($areas['error']){
            return response()->json([
                'error' => true,
                'message'=> $areas['message']
            ]);
        }

        return response()->json([
            'error' => false,
            'data' => $areas['data']
        ]);
    }

    public function desactivarAreas(Request $request){
        $ids = $request->input('ids');
        $estado = $request->input('estado');

        $resultado = $this->service_areas->desactivarAreas($ids, $estado);
        if($resultado['error']){
            return response()->json([
                'error' => true,
                'message' => $resultado['message']
            ]);
        }
        return response()->json([
            'error' => false,
            'message' => $resultado['message']
        ]);
    }

    public function asignarArea(Request $request){
        $id_user = $request->input('id_user');
        $id_area = $request->input('id_area');

        $asignacion = $this->service_areas->asignarArea($id_user, $id_area);

        if($asignacion['error']){
            return response()->json([
                'error' => true,
                'message' => $asignacion['message']
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => $asignacion['message']
        ]);
    }
}