<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Models\Reservas\Horas;
use App\Models\Reservas\Salones;

class SalonesController extends Controller
{
    public function listarSalones()
    {
        return $this->apiResponse([
            'error' => false,
            'message' => 'Salones obtenidos correctamente.',
            'data' => Salones::activo()->get(['id', 'nombre', 'portatil', 'sonido'])
        ]);
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
