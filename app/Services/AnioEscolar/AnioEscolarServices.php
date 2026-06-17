<?php

namespace App\Services\AnioEscolar;

use App\Models\AnioEscolar\Anio;
use App\Services\Service;
use Carbon\Carbon;

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
            // Calendario B (agosto a junio): a partir de agosto, las admisiones
            // ya corresponden al año escolar que inicia el año siguiente.
            $hoy = Carbon::now();
            $anioInicioObjetivo = $hoy->month >= 8 ? $hoy->year + 1 : $hoy->year;

            $anioVigente = Anio::where('anio_inicio', $anioInicioObjetivo)->first()
                ?? Anio::latest('id')->first();

            return [
                'error' => false,
                'message' => 'Último año escolar obtenido exitosamente',
                'data' => $anioVigente,
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
