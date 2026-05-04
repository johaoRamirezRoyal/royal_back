<?php
namespace App\Http\Controllers\Admisiones;

use App\Http\Controllers\Controller;
use App\Http\Requests\admisiones\RegistrarAspiranteRequest;
use App\Services\Admisiones\AdmisionesServices;
use Illuminate\Http\Request;

class AdmisionesController extends Controller
{
    protected AdmisionesServices $admisiones_services;

    public function __construct(AdmisionesServices $admisionesServices)
    {
        $this->admisiones_services = $admisionesServices;
    }

    public function registrarAspirante(RegistrarAspiranteRequest $request){
        $data = $request->validated();

        $resultado = $this->admisiones_services->registrarAspirante($data);

        return $this->apiResponse($resultado);
    }

    public function mostrarInformacionAspiranteId(Request $request){
        $id = $request->input('id');

        if(!$id){
            return response()->json([
                'error' => true,
                'message' => "Debe proporcionar un ID de aspirante válido",
                'data' => []
            ]);
        }

        $resultado = $this->admisiones_services->mostrarInformacionAspiranteId($id);

        return $this->apiResponse($resultado);
    }

    public function eliminarRegistroAspirante(int $id){
        $resultado = $this->admisiones_services->eliminarRegistroAspirante($id);

        return $this->apiResponse($resultado);
    }
}