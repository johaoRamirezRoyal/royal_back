<?php

namespace App\Services\AnioEscolar;

use App\Models\AnioEscolar\Anio;
use App\Services\Service;

class AnioEscolarServices extends Service
{
    public function obtenerAniosEscolares()
    {
        try {
            $anios = Anio::all();

            return [
                'error' => false,
                'message' => 'Años escolares obtenidos exitosamente',
                'data' => $anios,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener años escolares');
            return [
                'error' => true,
                'message' => 'Error al obtener años escolares: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    public function obtenerUltimoAnioEscolar()
    {
        try {
            $ultimoAnio = Anio::latest('id')->first();

            return [
                'error' => false,
                'message' => 'Último año escolar obtenido exitosamente',
                'data' => $ultimoAnio,
            ];
        } catch (\Exception $e) {
            $this->sendError($e, 'Error al obtener el último año escolar');
            return [
                'error' => true,
                'message' => 'Error al obtener el último año escolar: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }
}
