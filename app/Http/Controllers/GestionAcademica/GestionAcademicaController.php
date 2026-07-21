<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;
use App\Http\Requests\GestionAcademica\AsignaturaRequest;
use App\Http\Requests\GestionAcademica\CargaAcademicaRequest;
use App\Http\Requests\GestionAcademica\DocenteAsignaturaRequest;
use App\Http\Requests\GestionAcademica\FranjaHorariaRequest;
use App\Http\Requests\GestionAcademica\AsistenciaClaseRequest;
use App\Http\Requests\GestionAcademica\AsistenciaEstudianteRequest;
use App\Http\Requests\GestionAcademica\HorarioClaseRequest;
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
        $ids = $request->input('ids', []);

        return $this->apiResponse($this->service->asignatura()->eliminar($ids));
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
        $disponible = $request->boolean('disponible');
        $id_carga_academica = $request->input('id_carga_academica');

        return $this->apiResponse($this->service->franjaHoraria()->verFranjasHorarias($id_anio_escolar, $id_dia_semana, $disponible, $id_carga_academica));
    }

    public function crearFranjaHoraria(FranjaHorariaRequest $request)
    {
        return $this->apiResponse($this->service->franjaHoraria()->añadirFranjaHoraria($request->all()));
    }

    public function actualizarTipoFranjaHoraria(FranjaHorariaRequest $request)
    {
        $body = $request->validated();
        return $this->apiResponse($this->service->franjaHoraria()->actualizarFranjaHoraria($body['ids'], $body['id_anio_escolar'] ?? null));
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

    public function verHorario(Request $request)
    {
        $id_docente = $request->input('id_docente');
        $id_curso = $request->input('id_curso');
        $id_asignatura = $request->input('id_asignatura');
        $id_dia_semana = $request->input('id_dia_semana');

        return $this->apiResponse($this->service->horarioClase()->verHorario($id_docente, $id_curso, $id_asignatura, $id_dia_semana));
    }

    public function crearHorarioClase(HorarioClaseRequest $request)
    {
        return $this->apiResponse($this->service->horarioClase()->añadirHorarioClase($request->all()));
    }

    public function eliminarHorarios(HorarioClaseRequest $request)
    {
        return $this->apiResponse($this->service->horarioClase()->eliminarHorarios($request->input('ids')));
    }

    public function verAsistenciasClase(Request $request)
    {
        $id_horario_clase = $request->input('id_horario_clase');
        $fecha = $request->input('fecha');
        $fecha_inicio = $request->input('fecha_inicio');
        $fecha_fin = $request->input('fecha_fin');

        return $this->apiResponse($this->service->asistenciaClase()->verAsistenciaClase(
            $id_horario_clase,
            $fecha,
            $fecha_inicio,
            $fecha_fin
        ));
    }

    public function crearAsistenciaClase(AsistenciaClaseRequest $request)
    {
        return $this->apiResponse($this->service->asistenciaClase()->agregarAsistenciaClase($request->all()));
    }

    public function actualizarAsistenciaClase(AsistenciaClaseRequest $request)
    {
        return $this->apiResponse($this->service->asistenciaClase()->actualizarAsistenciaClase($request->all()));
    }

    public function verAsistenciasEstudiantes(Request $request)
    {
        return $this->apiResponse($this->service->asistenciaEstudiante()->verAsistenciaEstudiantesFiltrada(
            id_estudiante:    $request->input('id_estudiante'),
            id_curso:         $request->input('id_curso'),
            fecha:            $request->input('fecha'),
            id_clase:         $request->input('id_clase'),
            id_horario_clase: $request->input('id_horario_clase'),
        ));
    }

    public function crearAsistenciaEstudiantes(AsistenciaEstudianteRequest $request)
    {
        $validated = $request->validated();

        return $this->apiResponse($this->service->asistenciaEstudiante()->agregarAsistenciaEstudiantes(
            $validated['estudiantes'],
            $validated['id_asistencia_clase'],
        ));
    }

    public function eliminarAsistenciaEstudiante(Request $request)
    {
        return $this->apiResponse($this->service->asistenciaEstudiante()->eliminarAsistenciaEstudiante(
            $request->input('ids', []),
        ));
    }
}
