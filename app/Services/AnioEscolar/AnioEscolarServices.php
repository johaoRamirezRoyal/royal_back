<?php
namespace App\Services\AnioEscolar;

use App\Models\AnioEscolar\Anio;

class AnioEscolarServices
{
    public function obtenerAniosEscolares()
    {
        try {
            $anios = Anio::get();
            return [
                'error' => false,
                'message' => 'Años escolares obtenidos exitosamente',
                'data' => $anios
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'data' => [],
                'message' => $e->getMessage()
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
                'data' => $ultimoAnio
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }
}