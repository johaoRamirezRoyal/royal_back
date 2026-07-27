<?php

namespace App\Http\Controllers\AsistenciasTrabajador;

use App\Http\Controllers\Controller;
use App\Services\AsistenciasTrabajador\AsistenciaTrabajadorService;
use Illuminate\Http\Request;

class AsistenciasTrabajadorController extends Controller
{
    private AsistenciaTrabajadorService $asistencias_trabajador;

    public function __construct(AsistenciaTrabajadorService $asistencias_trabajador)
    {
        $this->asistencias_trabajador = $asistencias_trabajador;
    }

    public function obtenerLlegadas(Request $request)
    {
        $fecha_inicio = $request->input('fecha_inicio', null);
        $fecha_fin = $request->input('fecha_fin', null);
        $id_user = $request->input('id_user', null);

        $response = $this->asistencias_trabajador->obtenerLlegadas($fecha_inicio, $fecha_fin, $id_user);

        return $this->apiResponse($response);
    }

    public function eliminarLlegada(Request $request, ?int $id = null)
    {
        $ids = $id !== null ? [$id] : $request->input('ids', []);

        $response = $this->asistencias_trabajador->eliminarLlegadas($ids);

        return $this->apiResponse($response);
    }
}
