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
}