<?php
namespace App\Http\Controllers\TipoDocumento;

use App\Http\Controllers\Controller;
use App\Services\TipoDocumento\TipoDocumentoService;
use Illuminate\Http\Request;

class TipoDocumentoController extends Controller
{

    protected TipoDocumentoService $tipoDocumentoService;

    public function __construct(TipoDocumentoService $tipoDocumentoService){
        $this->tipoDocumentoService = $tipoDocumentoService;
    }

    public function obtenerTiposDocumentos(){
        $response = $this->tipoDocumentoService->obtenerTiposDocumentos();

        return $this->apiResponse($response);
    }

    public function obtenerTipoDocumentoPorId(Request $request){
        $ids = $request->input('ids');

        if(empty($ids)){
            return $this->apiResponse([
                'error' => true,
                'message' => "El parámetro 'ids' es requerido para obtener un tipo de documento por ID.",
                'data' => []
            ]);
        }

        if (!is_array($ids)) {
            return $this->apiResponse([
                'error' => true,
                'message' => "El parámetro 'ids' debe ser un array de IDs para obtener un tipo de documento por ID.",
                'data' => []
            ]);
        }

        $response = $this->tipoDocumentoService->obtenerTipoDocumentoPorId($ids);

        return $this->apiResponse($response);
    }
}