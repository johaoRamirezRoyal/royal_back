<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;
use App\Http\Requests\GestionAcademica\AsignaturaRequest;
use App\Http\Requests\GestionAcademica\AreaAcademicaRequest;
use App\Http\Requests\GestionAcademica\CargaAcademicaRequest;
use App\Http\Requests\GestionAcademica\DocenteAsignaturaRequest;
use App\Http\Requests\GestionAcademica\EsquemaHorarioRequest;
use App\Http\Requests\GestionAcademica\FranjaHorariaRequest;
use App\Http\Requests\GestionAcademica\MiHorarioRequest;
use App\Http\Requests\GestionAcademica\AsistenciaClaseRequest;
use App\Http\Requests\GestionAcademica\AsistenciaClaseLoteRequest;
use App\Http\Requests\GestionAcademica\AsistenciaEstudianteRequest;
use App\Http\Requests\GestionAcademica\HorarioClaseRequest;
use App\Services\AnioEscolar\AnioEscolarServices;
use App\Services\GestionAcademica\GestionAcademicaService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;

class GestionAcademicaController extends Controller
{
    // Opción "Gestión Académica" (99) en el frontend. Se chequea una sola vez acá en el
    // constructor (en vez de repetirlo en cada uno de los ~20 métodos del controller)
    // porque ningún endpoint de este controller —ni siquiera los de solo lectura— se
    // consume fuera de las páginas que ya gatea esta misma opción (asistencias-clases,
    // configuración académica). Antes de este chequeo, cualquier autenticado podía borrar
    // horarios de clase, reasignar carga docente o falsificar asistencia de estudiantes
    // sin tener acceso al módulo.
    private const OPCION_GESTION_ACADEMICA = 99;
    // Vicerrector/Directivo Docente: solo lectura del dashboard de métricas, no del
    // resto del controller (ver migración 2026_08_19_100000_seed_opcion_metricas_asistencia_academica).
    private const OPCION_METRICAS_ASISTENCIA = 102;

    // Perfil "Docente" en la tabla perfiles.
    private const PERFIL_DOCENTE = 3;

    // Acceso propio del docente (autoservicio, sin pasar por /permisos): tomar asistencia
    // de SUS clases, gestionar SU horario, y ver métricas (verMetricasAsistencia se
    // restringe server-side a sus propios cursos — ver
    // AsistenciaEstudianteService::metricasPorCurso). Explícitamente NO incluye nada de
    // Configuración académica (asignaturas/áreas/carga académica/franjas/esquemas/años
    // escolares/calendario) ni Llegadas tarde — esas siguen exigiendo la opción 99/101
    // otorgada desde /permisos, igual que para cualquier otro perfil.
    //
    // verFranjasHorarias es compartido con Configuración académica (el admin también lo
    // usa para armar la grilla de franjas), pero es de solo lectura de horarios/franjas —
    // no expone datos sensibles distintos de lo que ya ve en Mi horario — y sin él, el
    // sheet de "apartar horario" (useMiHorario.hook.ts::abrirSeleccion) no puede listar
    // las franjas disponibles antes de reservar.
    private const METODOS_DOCENTE = [
        'verAsistenciasClase', 'crearAsistenciaClase', 'actualizarAsistenciaClase', 'guardarAsistenciaClaseLote',
        'verAsistenciasEstudiantes', 'crearAsistenciaEstudiantes', 'eliminarAsistenciaEstudiante',
        'verMiMenuHorario', 'verMiHorario', 'reservarMiHorario', 'actualizarDescripcionMiHorario', 'eliminarMiHorario',
        'verMetricasAsistencia', 'obtenerMisCursos', 'verFranjasHorarias',
    ];

    public function __construct(
        private GestionAcademicaService $service,
        private AnioEscolarServices $anioEscolarService,
        private UsuariosServices $usuariosService,
        Request $request,
    ) {
        $tieneAccesoCompleto = $usuariosService->tienePermiso(self::OPCION_GESTION_ACADEMICA, $request->user()->perfil)['permiso'] ?? false;

        if ($tieneAccesoCompleto) {
            return;
        }

        $metodo = $request->route()?->getActionMethod();

        $esDocenteEnMetodoPropio = $request->user()->perfil === self::PERFIL_DOCENTE
            && in_array($metodo, self::METODOS_DOCENTE, true);

        if ($esDocenteEnMetodoPropio) {
            return;
        }

        $esAccionMetricas = $metodo === 'verMetricasAsistencia';
        $tieneAccesoMetricas = $esAccionMetricas
            && ($usuariosService->tienePermiso(self::OPCION_METRICAS_ASISTENCIA, $request->user()->perfil)['permiso'] ?? false);

        if (!$tieneAccesoMetricas) {
            abort($this->error('No tienes permiso para acceder a Gestión Académica', 403));
        }
    }

    public function listarAsignaturas(Request $request)
    {
        $nombre = $request->input('nombre', null);
        $codigo = $request->input('codigo', null);
        $abreviatura = $request->input('abreviatura', null);
        $estado = $request->input('estado', null);
        $idArea = $request->input('id_area', null);

        $response = $this->service->asignatura()->mostrarAsignaturasFiltradas($nombre, $codigo, $abreviatura, $estado, $idArea);

        return $this->apiResponse($response);
    }

    public function listarAreasAcademicas(Request $request)
    {
        $nombre = $request->input('nombre', null);
        $estado = $request->input('estado', null);

        $response = $this->service->areaAcademica()->mostrarAreasFiltradas($nombre, $estado);

        return $this->apiResponse($response);
    }

    public function obtenerAreaAcademica(int $id)
    {
        return $this->apiResponse($this->service->areaAcademica()->obtenerPorId($id));
    }

    public function crearAreaAcademica(AreaAcademicaRequest $request)
    {
        $body = $request->validated();

        $response = $this->service->areaAcademica()->crear($body);

        return $this->apiResponse($response);
    }

    public function actualizarAreaAcademica(AreaAcademicaRequest $request)
    {
        $body = $request->validated();
        $id = $request->input('id');

        $response = $this->service->areaAcademica()->actualizar($id, $body);

        return $this->apiResponse($response);
    }

    public function eliminarAreaAcademica(Request $request)
    {
        $ids = $request->input('ids', []);

        return $this->apiResponse($this->service->areaAcademica()->eliminar($ids));
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
        $id_esquema = $request->input('id_esquema');
        $id_curso = $request->input('id_curso');
        $id_anio_escolar = $request->input('id_anio_escolar');
        $id_dia_semana = $request->input('id_dia_semana');
        $disponible = $request->boolean('disponible');
        $id_carga_academica = $request->input('id_carga_academica');

        return $this->apiResponse($this->service->franjaHoraria()->verFranjasHorarias($id_esquema, $id_curso, $id_anio_escolar, $id_dia_semana, $disponible, $id_carga_academica));
    }

    public function listarEsquemasHorario(Request $request)
    {
        $id_anio_escolar = $request->input('id_anio_escolar');
        $id_nivel = $request->input('id_nivel');

        return $this->apiResponse($this->service->esquemaHorario()->listarEsquemas($id_anio_escolar, $id_nivel));
    }

    public function crearEsquemaHorario(EsquemaHorarioRequest $request)
    {
        return $this->apiResponse($this->service->esquemaHorario()->crearEsquema($request->validated()));
    }

    public function actualizarEsquemaHorario(EsquemaHorarioRequest $request)
    {
        $body = $request->validated();
        $id = $body['id'];
        unset($body['id']);

        return $this->apiResponse($this->service->esquemaHorario()->actualizarEsquema($id, $body));
    }

    public function eliminarEsquemaHorario(Request $request)
    {
        return $this->apiResponse($this->service->esquemaHorario()->eliminarEsquema($request->input('ids', [])));
    }

    public function verMiMenuHorario(Request $request)
    {
        $id_anio_escolar = $request->input('id_anio_escolar');

        return $this->apiResponse($this->service->docenteHorario()->verMenu($request->user()->id_user, $id_anio_escolar));
    }

    public function verMiHorario(Request $request)
    {
        return $this->apiResponse($this->service->docenteHorario()->misHorarios($request->user()->id_user));
    }

    public function reservarMiHorario(MiHorarioRequest $request)
    {
        $body = $request->validated();

        return $this->apiResponse($this->service->docenteHorario()->reservar(
            $request->user()->id_user,
            $body['id_curso'],
            $body['id_asignatura'],
            $body['id_franja_horaria'],
            $body['id_anio_escolar'],
            $body['descripcion'] ?? null,
        ));
    }

    public function actualizarDescripcionMiHorario(MiHorarioRequest $request)
    {
        $body = $request->validated();

        return $this->apiResponse($this->service->docenteHorario()->actualizarDescripcion(
            $request->user()->id_user,
            $body['id'],
            $body['descripcion'] ?? null,
        ));
    }

    public function eliminarMiHorario(MiHorarioRequest $request)
    {
        return $this->apiResponse($this->service->docenteHorario()->eliminar($request->user()->id_user, $request->input('ids', [])));
    }

    public function crearFranjaHoraria(FranjaHorariaRequest $request)
    {
        return $this->apiResponse($this->service->franjaHoraria()->añadirFranjaHoraria($request->all()));
    }

    public function crearFranjasHorariasEnLote(FranjaHorariaRequest $request)
    {
        return $this->apiResponse($this->service->franjaHoraria()->añadirFranjasHorariasEnLote($request->all()));
    }

    public function copiarFranjasDia(FranjaHorariaRequest $request)
    {
        $body = $request->validated();
        return $this->apiResponse($this->service->franjaHoraria()->copiarFranjasDeDia(
            $body['id_esquema'],
            $body['id_dia_origen'],
            $body['ids_dias_destino'],
        ));
    }

    public function actualizarTipoFranjaHoraria(FranjaHorariaRequest $request)
    {
        $body = $request->validated();
        return $this->apiResponse($this->service->franjaHoraria()->actualizarFranjaHoraria($body['ids'], $body['id_esquema'] ?? null));
    }

    public function actualizarOrdenFranjasHorarias(FranjaHorariaRequest $request)
    {
        return $this->apiResponse($this->service->franjaHoraria()->actualizarOrdenFranjasHorarias($request->input('franjas')));
    }

    public function actualizarHorarioFranja(FranjaHorariaRequest $request)
    {
        $body = $request->validated();
        return $this->apiResponse($this->service->franjaHoraria()->actualizarHorarioFranja(
            $body['id'],
            $body['hora_inicio'] ?? null,
            $body['hora_fin'] ?? null,
            $body['asignable'] ?? null,
            $body['color'] ?? null,
            $body['etiqueta'] ?? null,
            $body['aplicar_todos_dias'] ?? null,
        ));
    }

    public function eliminarFranjaHoraria(FranjaHorariaRequest $request)
    {
        return $this->apiResponse($this->service->franjaHoraria()->eliminarFranjaHoraria($request->input('ids')));
    }

    public function moverFranjaADia(FranjaHorariaRequest $request)
    {
        $body = $request->validated();
        return $this->apiResponse($this->service->franjaHoraria()->moverFranjaADia($body['id'], $body['id_dia_semana']));
    }

    public function quitarNoAsignableDeOtrosDias(FranjaHorariaRequest $request)
    {
        $body = $request->validated();
        return $this->apiResponse($this->service->franjaHoraria()->quitarNoAsignableDeOtrosDias($body['id']));
    }

    public function verHorario(Request $request)
    {
        $id_docente = $request->input('id_docente');
        $id_curso = $request->input('id_curso');
        $id_asignatura = $request->input('id_asignatura');
        $id_dia_semana = $request->input('id_dia_semana');
        $incluir_no_asignables = $request->boolean('incluir_no_asignables');

        return $this->apiResponse($this->service->horarioClase()->verHorario($id_docente, $id_curso, $id_asignatura, $id_dia_semana, $incluir_no_asignables));
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

    public function guardarAsistenciaClaseLote(AsistenciaClaseLoteRequest $request)
    {
        $validated = $request->validated();

        return $this->apiResponse($this->service->asistenciaClase()->guardarLote(
            $validated['ids_horario_clase'],
            $validated['fecha'],
            $validated['estado'],
            $validated['observacion'] ?? null,
            $validated['estudiantes'] ?? [],
        ));
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

    /**
     * Cursos donde el docente autenticado tiene carga académica — para el filtro de curso
     * del dashboard de Métricas de Asistencia (ver AttendanceMetrics en el frontend), que
     * de otra forma lista TODOS los cursos vía /api/cursos/all.
     */
    public function obtenerMisCursos(Request $request)
    {
        return $this->apiResponse($this->service->cargaAcademica()->obtenerCursosDocente($request->user()->id_user));
    }

    public function verMetricasAsistencia(Request $request)
    {
        // Docente sin acceso completo (opción 99): restringido server-side a sus propios
        // cursos (ver AsistenciaEstudianteService::metricasPorCurso) — no basta con
        // ocultar el selector de curso en el frontend, cualquiera podría pedir otro
        // id_curso directo contra la API.
        $tieneAccesoCompleto = $this->usuariosService
            ->tienePermiso(self::OPCION_GESTION_ACADEMICA, $request->user()->perfil)['permiso'] ?? false;
        $idDocenteScope = (!$tieneAccesoCompleto && $request->user()->perfil === self::PERFIL_DOCENTE)
            ? $request->user()->id_user
            : null;

        return $this->apiResponse($this->service->asistenciaEstudiante()->metricasPorCurso(
            fecha_inicio: $request->input('fecha_inicio'),
            fecha_fin:    $request->input('fecha_fin'),
            id_curso:     $request->input('id_curso'),
            id_docente_scope: $idDocenteScope,
        ));
    }

    public function eliminarAsistenciaEstudiante(Request $request)
    {
        return $this->apiResponse($this->service->asistenciaEstudiante()->eliminarAsistenciaEstudiante(
            $request->input('ids', []),
        ));
    }

    public function obtenerConfiguracionCalendario()
    {
        return $this->apiResponse($this->anioEscolarService->obtenerConfiguracionCalendario());
    }

    public function actualizarConfiguracionCalendario(Request $request)
    {
        $request->validate([
            'tipo_calendario' => 'required|in:A,B',
        ]);

        return $this->apiResponse($this->anioEscolarService->actualizarConfiguracionCalendario($request->input('tipo_calendario')));
    }

    public function crearAnioEscolarManual(Request $request)
    {
        $request->validate([
            'anio_inicio' => 'required|integer|min:2000|max:2100',
        ]);

        return $this->apiResponse($this->anioEscolarService->crearAnioEscolarManual((int) $request->input('anio_inicio')));
    }

    public function actualizarEstadoAnioEscolar(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:anio_escolar,id',
            'activo' => 'required|boolean',
        ]);

        return $this->apiResponse($this->anioEscolarService->actualizarEstadoAnioEscolar(
            (int) $request->input('id'),
            (bool) $request->input('activo'),
        ));
    }
}
