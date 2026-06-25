<?php

namespace App\Http\Controllers\AnioAcademico;

use App\Http\Controllers\Controller;
use App\Http\Requests\PeriodoAcademico\PeriodoAcademicoRequest;
use App\Models\AnioEscolar\PeriodoAcademico;
use App\Services\AnioEscolar\PeriodoAcademicoServices;
use Illuminate\Http\Request;

class PeriodoAcademicoController extends Controller
{
    private PeriodoAcademicoServices $periodo_academico;

    public function __construct(PeriodoAcademicoServices $periodoAcademico)
    {
        $this->periodo_academico = $periodoAcademico;
    }

    public function agregarPeriodoAcademico(PeriodoAcademicoRequest $request)
    {
        $body = $request->validated();

        $response = $this->periodo_academico->agregarPeriodoAcademico($body);

        return $this->apiResponse($response);
    }

    public function obtenerPeriodoAcademicoPorId(Request $request)
    {
        $id_periodo_academico = $request->input('id_anio_escolar');

        $response = $this->periodo_academico->obtenerPeriodoAcademicoPorId($id_periodo_academico);

        return $this->apiResponse($response);
    }

    public function obtenerPeriodoAcademico(Request $request)
    {
        $id_anio_escolar = $request->input('id_anio_escolar', null);
        $estado = $request->input('estado', null);

        $response = $this->periodo_academico->obtenerPeriodoAcademico($id_anio_escolar, $estado);

        return $this->apiResponse($response);
    }

    public function editarPeriodoAcademico(PeriodoAcademicoRequest $request)
    {
        $body = $request->validated();
        $id_periodo_academico = $request->input('id_periodo_academico');

        $response = $this->periodo_academico->editarPeriodoAcademico($id_periodo_academico, $body);

        return $this->apiResponse($response);
    }

    public function eliminarPeriodoAcademico(Request $request)
    {
        $ids_periodo_academico = $request->input('ids_periodo_academico');
        $response = $this->periodo_academico->eliminarPeriodoAcademico($ids_periodo_academico);
        return $this->apiResponse($response);
    }

    public function desactivarPeriodosAcademicos(Request $request)
    {
        $ids_periodos_academico = $request->input('ids_periodos_academicos', null);
        $estado = $request->input("estado", 0);

        $response = $this->periodo_academico->desactivarPeriodosAcademicos($ids_periodos_academico, $estado);

        return $this->apiResponse($response);
    }
}
