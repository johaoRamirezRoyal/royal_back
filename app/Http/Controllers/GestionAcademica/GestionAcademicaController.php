<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;
use App\Http\Requests\GestionAcademica\AsignaturaRequest;
use App\Http\Requests\GestionAcademica\CargaAcademicaRequest;
use App\Http\Requests\GestionAcademica\DocenteAsignaturaRequest;
use App\Http\Requests\GestionAcademica\FranjaHorariaRequest;
use App\Services\GestionAcademica\GestionAcademicaService;
use Illuminate\Http\Request;

class GestionAcademicaController extends Controller
{
    public function __construct(
        private GestionAcademicaService $service
    ) {}

    public function listarAsignaturas(Request $request)
    {
        $nombre = $request->input('nombre', null);
        $codigo = $request->input('codigo', null);
        $abreviatura = $request->input('abreviatura', null);
        $estado = $request->input('estado', null);

        $response = $this->service->asignatura()->mostrarAsignaturasFiltradas($nombre, $codigo, $abreviatura, $estado);

        return $this->apiResponse($response);
    }

    public function obtenerAsignatura(int $id)
    {
        return $this->apiResponse($this->service->asignatura()->obtenerPorId($id));
    }

    public function crearAsignatura(AsignaturaRequest $request)
    {
        $body = $request->validated();

        $response = $this->service->asignatura()->crear($body);

        return $this->apiResponse($response);
    }

    public function actualizarAsignatura(AsignaturaRequest $request)
    {
        $body = $request->validated();
        $id = $request->input('id');

        $response = $this->service->asignatura()->actualizar($id, $body);

        return $this->apiResponse($response);
    }

    public function eliminarAsignatura(Request $request)
    {
        $id = $request->input('id');
        return $this->apiResponse($this->service->asignatura()->eliminar($id));
    }

    public function listarDocentesAsignaturas(Request $request)
    {
        $usuario = $request->input('usuario', null);
        $asignatura = $request->input('asignatura', null);
        $search = $request->input('s', null);
        $perpage = $request->input('per-page', 10);

        $response = $this->service->docenteAsignatura()->listarDocentesAsignaturas($usuario, $asignatura, $search, $perpage);

        return $this->paginatedResponse($response);
    }

    public function asignarAsignaturasDocente(DocenteAsignaturaRequest $request)
    {
        $body = $request->validated();

        $response = $this->service->docenteAsignatura()->asignarAsignaturasDocente($body['id_user'], $body['asignaturas']);

        return $this->apiResponse($response);
    }

    public function eliminarAsignaturasDocente(Request $request)
    {
        $ids = $request->input('ids');

        $response = $this->service->docenteAsignatura()->eliminarAsignaturasDocente($ids);

        return $this->apiResponse($response);
    }

    public function listarCargaAcademica(Request $request)
    {
        $id_docente = $request->input('id_docente');
        $id_curso = $request->input('id_curso', null);
        $id_asignatura = $request->input('id_asignatura', null);
        $estado = $request->input('estado', 1);

        $response = $this->service->cargaAcademica()->listarCargaAcademicaDocente($id_docente, $estado, $id_curso, $id_asignatura);

        return $this->apiResponse($response);
    }

    public function crearCargaAcademica(CargaAcademicaRequest $request)
    {
        $body = $request->validated();
        $response = $this->service->cargaAcademica()->añadirCargaAcademicaDocente($body['id_curso'], $body['id_docente_asignatura']);
        return $this->apiResponse($response);
    }

    public function cambiarEstadoCargaAcademica(Request $request)
    {
        $ids = $request->input('ids');
        $estado = $request->input('estado', 0);

        $response = $this->service->cargaAcademica()->cambiarEstadoCargaAcademica($ids, $estado);

        return $this->apiResponse($response);
    }

    public function verFranjasHorarias(Request $request)
    {
        $id_anio_escolar = $request->input('id_anio_escolar');
        $id_dia_semana = $request->input('id_dia_semana');
        $tipo = $request->input('tipo');

        return $this->apiResponse($this->service->franjaHoraria()->verFranjasHorarias($id_anio_escolar, $id_dia_semana, $tipo));
    }

    public function crearFranjaHoraria(FranjaHorariaRequest $request)
    {
        return $this->apiResponse($this->service->franjaHoraria()->añadirFranjaHoraria($request->all()));
    }

    public function actualizarTipoFranjaHoraria(FranjaHorariaRequest $request)
    {
        $body = $request->validated();
        return $this->apiResponse($this->service->franjaHoraria()->actualizarFranjaHoraria($body['ids'], $body['id_anio_escolar'] ?? null, $body['tipo'] ?? null));
    }

    public function actualizarOrdenFranjasHorarias(FranjaHorariaRequest $request)
    {
        return $this->apiResponse($this->service->franjaHoraria()->actualizarOrdenFranjasHorarias($request->input('franjas')));
    }

    public function actualizarHorarioFranja(FranjaHorariaRequest $request)
    {
        $body = $request->validated();
        return $this->apiResponse($this->service->franjaHoraria()->actualizarHorarioFranja($body['id'], $body['hora_inicio'] ?? null, $body['hora_fin'] ?? null));
    }

    public function eliminarFranjaHoraria(FranjaHorariaRequest $request)
    {
        return $this->apiResponse($this->service->franjaHoraria()->eliminarFranjaHoraria($request->input('ids')));
    }
}
