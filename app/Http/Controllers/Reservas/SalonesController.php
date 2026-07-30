<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservas\SalonRequest;
use App\Models\Reservas\Horas;
use App\Models\Reservas\Salones;
use App\Services\Reservas\ReservasServices;

class SalonesController extends Controller
{
    public function __construct(
        private ReservasServices $reservasServices
    ) {}

    public function listarSalones()
    {
        return $this->apiResponse([
            'error' => false,
            'message' => 'Salones obtenidos correctamente.',
            'data' => Salones::activo()->get(['id', 'nombre', 'portatil', 'sonido'])
        ]);
    }

    public function crearSalon(SalonRequest $request)
    {
        return $this->apiResponse(
            $this->reservasServices->crearSalon($request->validated())
        );
    }

    public function actualizarSalon(SalonRequest $request, int $id)
    {
        return $this->apiResponse(
            $this->reservasServices->actualizarSalon($request->validated(), $id)
        );
    }

    public function eliminarSalon(int $id)
    {
        return $this->apiResponse(
            $this->reservasServices->eliminarSalon($id)
        );
    }

    public function listarHoras()
    {
        return $this->apiResponse([
            'error' => false,
            'message' => 'Horas obtenidas correctamente.',
            'data' => Horas::where('activo', 1)->get(['id', 'horas'])
        ]);
    }
}
