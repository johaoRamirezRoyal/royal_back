<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;
use App\Services\GestionAcademica\AccesoObservaciones;
use App\Services\GestionAcademica\CargaAcademicaService;
use App\Services\GestionAcademica\ObservacionEstudianteService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gestión Convivencial — observaciones de estudiantes por día, en actas por periodo
 * académico (reglas en ObservacionEstudianteService).
 *
 * Acceso (se resuelve una vez en el constructor, ver AccesoObservaciones):
 * - Registrar: docentes (estudiantes de sus cursos con carga activa), opción 133
 *   "Observaciones de estudiantes" u opción 99 (cualquier estudiante).
 * - Ver: cada quien solo lo que registró; la opción 134 "Ver todas las observaciones" ve
 *   las de todos los profesores en todos los cursos (sola, es de solo lectura).
 * - Opción 135 "Observaciones — solo consulta": ve todas y NO actúa sobre ninguna, aunque
 *   también sea docente o tenga la 133/99 (gana sobre ellas). Para quien debe auditar sin
 *   registrar (rectoría, por ejemplo).
 * Controller aparte de GestionAcademicaController por el mismo motivo que
 * ConsultaAsistenciaClaseController: su constructor exige la 99.
 */
class ObservacionEstudianteController extends Controller
{
    private const OPCION_OBSERVACIONES = 133;

    private const OPCION_VER_TODAS = 134;

    private const OPCION_GESTION_ACADEMICA = 99;

    private const OPCION_SOLO_CONSULTA = 135;

    private const PERFILES_DOCENTE = [3, 14];

    private AccesoObservaciones $acceso;

    public function __construct(
        private ObservacionEstudianteService $service,
        CargaAcademicaService $cargaAcademica,
        UsuariosServices $usuariosService,
        Request $request,
    ) {
        $usuario = $request->user();
        $tiene = fn (int $opcion) => (bool) ($usuariosService->tienePermiso($opcion, $usuario->perfil)['permiso'] ?? false);

        $soloConsulta = $tiene(self::OPCION_SOLO_CONSULTA);
        $registraTodos = ! $soloConsulta && ($tiene(self::OPCION_OBSERVACIONES) || $tiene(self::OPCION_GESTION_ACADEMICA));
        $esDocente = ! $soloConsulta && in_array($usuario->perfil, self::PERFILES_DOCENTE, true);

        $this->acceso = new AccesoObservaciones(
            idUsuario: $usuario->id_user,
            verTodas: $soloConsulta || $tiene(self::OPCION_VER_TODAS),
            puedeRegistrar: $registraTodos || $esDocente,
            idsCursosRegistro: $registraTodos
                ? null
                : collect($esDocente ? ($cargaAcademica->obtenerCursosDocente($usuario->id_user)['data'] ?? []) : [])->pluck('id')->all(),
        );

        if (! $this->acceso->puedeRegistrar && ! $this->acceso->verTodas) {
            abort($this->error('No tienes permiso para ver las observaciones de estudiantes', 403));
        }
    }

    public function index(Request $request)
    {
        return $this->apiResponse($this->service->listar(
            acceso: $this->acceso,
            filtros: $request->only(['id_periodo', 'fecha_inicio', 'fecha_fin', 'id_curso', 'tipo', 'id_estudiante']),
            pagina: (int) $request->input('page', 1),
            porPagina: (int) $request->input('per-page', $request->input('per_page', 20)),
            busqueda: $request->input('s'),
            orden: $request->input('sort'),
            direccion: $request->input('dir'),
        ));
    }

    /** Catálogos de filtros (cursos visibles y periodos) + qué puede hacer el usuario. */
    public function catalogos()
    {
        $idsCursos = $this->acceso->idsCursosEstudiantes();
        $cursos = DB::table('curso')
            ->where('activo', 1)
            ->when($idsCursos !== null, fn ($q) => $q->whereIn('id', $idsCursos))
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return $this->apiResponse([
            'error' => false,
            'message' => 'Catálogos obtenidos',
            'data' => [
                'cursos' => $cursos,
                'periodos' => $this->service->periodos(),
                'ver_todas' => $this->acceso->verTodas,
                'puede_registrar' => $this->acceso->puedeRegistrar,
            ],
        ]);
    }

    public function estudiantes(Request $request)
    {
        return $this->apiResponse($this->service->buscarEstudiantes($this->acceso, $request->input('s')));
    }

    /** Acta del estudiante en `?id_periodo=` (por defecto el periodo actual). */
    public function acta(Request $request, int $idEstudiante)
    {
        $idPeriodo = $request->integer('id_periodo') ?: null;

        return $this->apiResponse($this->service->acta($this->acceso, $idEstudiante, $idPeriodo));
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request, conEstudiante: true);

        return $this->apiResponse($this->service->crear($this->acceso, $datos['id_estudiante'], $datos));
    }

    public function update(Request $request, int $id)
    {
        return $this->apiResponse($this->service->actualizar($this->acceso, $id, $this->validar($request)));
    }

    public function destroy(int $id)
    {
        return $this->apiResponse($this->service->eliminar($this->acceso, $id));
    }

    private function validar(Request $request, bool $conEstudiante = false): array
    {
        return $request->validate([
            ...($conEstudiante ? ['id_estudiante' => ['required', 'integer']] : []),
            'fecha' => ['required', 'date_format:Y-m-d'],
            'tipo' => ['required', Rule::in(ObservacionEstudianteService::TIPOS)],
            'motivo' => ['required', 'string', 'max:5000'],
            'id_asignatura' => ['nullable', 'integer'],
        ], [
            'fecha.required' => 'La fecha es obligatoria.',
            'tipo.in' => 'El tipo debe ser positiva o negativa.',
            'motivo.required' => 'El motivo de la observación es obligatorio.',
        ]);
    }
}
