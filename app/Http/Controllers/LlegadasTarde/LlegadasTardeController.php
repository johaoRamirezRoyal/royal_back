<?php 
namespace App\Http\Controllers\LlegadasTarde;

use App\Http\Controllers\Controller;
use App\Http\Requests\LlegadasTarde\LlegadaTardeRequest;
use App\Services\LlegadasTardeEstudiantes\LlegadasTarde;
use Illuminate\Http\Request;

class LlegadasTardeController extends Controller
{
    private LlegadasTarde $llegadas_tarde;

    public function __construct(LlegadasTarde $llegadas_tarde){
        $this->llegadas_tarde = $llegadas_tarde;
    }

    public function agregarLlegadaTarde(LlegadaTardeRequest $request){
        $body = $request->validated();

        $id_alumno = $body['id_alumno'];
        $fecha = $body['fecha'];
        $hora = $body['hora'];

        $response = $this->llegadas_tarde->agregarLlegadaTarde($id_alumno, $fecha, $hora);

        return $this->apiResponse($response);
    }

    public function obtenerLlegadasTarde(Request $request){

        $id_anio_academico = $request->input('id_periodo_academico', null);
        $id_alumno = $request->input('id_alumno', null);

        $response = $this->llegadas_tarde->obtenerLlegadasTarde($id_anio_academico, $id_alumno);

        return $this->apiResponse($response);

    }

    public function dashboardLlegadasTarde(Request $request){
        $id_periodo_academico = $request->input('id_periodo_academico', null);

        $response = $this->llegadas_tarde->dashboardLlegadasTarde($id_periodo_academico);

        return $this->apiResponse($response);
    }

    public function eliminarLlegadaTarde(Request $request, ?int $id = null){
        $ids = $id !== null ? [$id] : $request->input('ids_llegadas_tarde', []);

        $response = $this->llegadas_tarde->eliminarLlegadaTarde($ids);

        return $this->apiResponse($response);
    }
}