<?php 
namespace App\Http\Controllers\LlegadasTarde;

use App\Http\Controllers\Controller;
use App\Services\LlegadasTardeEstudiantes\LlegadasTarde;
use Illuminate\Http\Request;

class LlegadasTardeController extends Controller
{
    private LlegadasTarde $llegadas_tarde;

    public function __construct(LlegadasTarde $llegadas_tarde){
        $this->llegadas_tarde = $llegadas_tarde;
    }

    public function obtenerLlegadasTarde(Request $request){

        $id_anio_academico = $request->input('id_periodo_academico');
        $id_alumno = $request->input('id_alumno', null);

        $response = $this->llegadas_tarde->obtenerLlegadasTarde($id_anio_academico, $id_alumno);

        return $this->apiResponse($response);

    }

    public function eliminarLlegadaTarde(Request $request){
        $ids_llegadas_tarde = $request->input('ids_llegadas_tarde');

        $response = $this->llegadas_tarde->eliminarLlegadaTarde($ids_llegadas_tarde);

        return $this->apiResponse($response);
    }
}