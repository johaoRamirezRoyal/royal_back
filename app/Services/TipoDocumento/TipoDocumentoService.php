<?php

namespace App\Services\TipoDocumento;

use App\Models\TipoDocumento\TipoDocumento;
use App\Services\Service;
use Exception;

class TipoDocumentoService extends Service
{
    /**
     * Obtener todos los tipos de documentos.
     * @return array{data: array, error: bool, message: string}
     */
    public function obtenerTiposDocumentos(): array
    {
        try {
            $documentos = TipoDocumento::where('activo', 1)->get();

            if($documentos->isEmpty()){
                return [
                    'error' => true,
                    'message' => "No se encontraron tipos de documentos activos.",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Tipos de documentos obtenidos exitosamente.",
                'data' => $documentos->toArray()
            ];
        }catch(Exception $e){
            $this->sendError($e, "Error al obtener los tipos de documentos.");

            return [
                'error' => true,
                'message' => "Error en el servidor al tratar de listar los tipos de documentos.",
                'data' => []
            ];
        }
    }

    /**
     * Obtener un tipo d edocumento en base al ID
     * @param int $id
     * @return array{data: array, error: bool, message: string}
     */
    public function obtenerTipoDocumentoPorId(array $ids) : array
    {
        $ids_to_string = implode(", ", $ids);
        try {
            $documento = TipoDocumento::whereIn('id', $ids)->where('activo', 1)->first();

            if(!$documento){
                return [
                    'error' => true,
                    'message' => "No se encontró el tipo de documento con ID: $ids_to_string.",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Tipo de documento obtenido.",
                'data' => $documento->toArray()
            ];
        }catch(Exception $e){
            $this->sendError($e, "Error al obtener el tipo de documento por ID: $ids_to_string.");

            return [
                'error' => true,
                'message' => "Error en el servidor al tratar de obtener el tipo de documento.",
                'data' => []
             ];
        }
    }
}