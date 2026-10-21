<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;
use App\Services\GestionAcademica\AccesoObservaciones;
use App\Services\GestionAcademica\CargaAcademicaService;
use App\Services\GestionAcademica\ObservacionEstudianteService;
use App\Services\Usuarios\PermisosPerfil;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Support\Permiso;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

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
class ObservacionEstudianteController extends Controller implements HasMiddleware
{
    /** Entra quien registra o consulta; qué puede hacer adentro lo decide acceso(). Mismo permiso que su ruta: el controller no depende de que la ruta lo tenga. */
    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([self::OPCION_OBSERVACIONES, self::OPCION_VER_TODAS, self::OPCION_SOLO_CONSULTA, self::OPCION_GESTION_ACADEMICA], self::PERFILES_DOCENTE))];
    }

    private const OPCION_OBSERVACIONES = Permiso::OBSERVACIONES;

    private const OPCION_VER_TODAS = Permiso::OBSERVACIONES_VER_TODAS;

    private const OPCION_GESTION_ACADEMICA = Permiso::GESTION_ACADEMICA;

    private const OPCION_SOLO_CONSULTA = Permiso::OBSERVACIONES_SOLO_CONSULTA;

    // "Elegir docente": al registrar, elige a qué docente corresponde la observación. Sin
    // ella queda a nombre de quien la registra, aunque tenga la 133/99.
    private const OPCION_ELEGIR_DOCENTE = Permiso::OBSERVACIONES_ELEGIR_DOCENTE;

    private const PERFILES_DOCENTE = [Permiso::PERFIL_DOCENTE, Permiso::PERFIL_DOCENTE_ICT];

    private ?AccesoObservaciones $acceso = null;

    public function __construct(
        private ObservacionEstudianteService $service,
        private CargaAcademicaService $cargaAcademica,
    ) {
    }

    /**
     * Qué puede hacer el usuario, armado en la acción (no en el constructor: Laravel crea el
     * controller antes de correr el middleware de la ruta). Quién entra lo decide el
     * middleware `permiso` de la ruta; las opciones se leen de la lista que él ya cargó.
     */
    private function acceso(): AccesoObservaciones
    {
        if ($this->acceso !== null) {
            return $this->acceso;
        }

        $request = request();
        $usuario = $request->user();
        $tiene = fn (int $opcion) => PermisosPerfil::tiene($request, $opcion);

        $soloConsulta = $tiene(self::OPCION_SOLO_CONSULTA);
        $registraTodos = ! $soloConsulta && ($tiene(self::OPCION_OBSERVACIONES) || $tiene(self::OPCION_GESTION_ACADEMICA));
        $esDocente = ! $soloConsulta && in_array((int) $usuario->perfil, self::PERFILES_DOCENTE, true);

        return $this->acceso = new AccesoObservaciones(
            idUsuario: $usuario->id_user,
            verTodas: $soloConsulta || $tiene(self::OPCION_VER_TODAS),
            puedeRegistrar: $registraTodos || $esDocente,
            idsCursosRegistro: $registraTodos
                ? null
                : collect($esDocente ? ($this->cargaAcademica->obtenerCursosDocente($usuario->id_user)['data'] ?? []) : [])->pluck('id')->all(),
            tieneElegirDocente: $tiene(self::OPCION_ELEGIR_DOCENTE),
        );
    }

    public function index(Request $request)
    {
        return $this->apiResponse($this->service->listar(
            acceso: $this->acceso(),
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
        $idsCursos = $this->acceso()->idsCursosEstudiantes();
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
                'ver_todas' => $this->acceso()->verTodas,
                'puede_registrar' => $this->acceso()->puedeRegistrar,
                'elige_docente' => $this->acceso()->eligeDocente(),
            ],
        ]);
    }

    public function estudiantes(Request $request)
    {
        return $this->apiResponse($this->service->buscarEstudiantes($this->acceso(), $request->input('s')));
    }

    /** Acta del estudiante en `?id_periodo=` (por defecto el periodo actual). */
    public function acta(Request $request, int $idEstudiante)
    {
        $idPeriodo = $request->integer('id_periodo') ?: null;

        return $this->apiResponse($this->service->acta($this->acceso(), $idEstudiante, $idPeriodo));
    }

    /** Observaciones de otros docentes al estudiante en `?fecha=` (Y-m-d). */
    public function delDia(Request $request, int $idEstudiante)
    {
        $fecha = $request->validate(['fecha' => 'required|date_format:Y-m-d'])['fecha'];

        return $this->apiResponse($this->service->delDia($this->acceso(), $idEstudiante, $fecha));
    }

    public function store(Request $request)
    {
        $datos = $this->validar($request, conEstudiante: true);

        return $this->apiResponse($this->service->crear($this->acceso(), $datos['id_estudiante'], $datos));
    }

    public function update(Request $request, int $id)
    {
        return $this->apiResponse($this->service->actualizar($this->acceso(), $id, $this->validar($request)));
    }

    public function destroy(int $id)
    {
        return $this->apiResponse($this->service->eliminar($this->acceso(), $id));
    }

    private function validar(Request $request, bool $conEstudiante = false): array
    {
        return $request->validate([
            ...($conEstudiante ? ['id_estudiante' => ['required', 'integer']] : []),
            'fecha' => ['required', 'date_format:Y-m-d'],
            'tipo' => ['required', Rule::in(ObservacionEstudianteService::TIPOS)],
            'motivo' => ['required', 'string', 'max:5000'],
            'id_asignatura' => ['nullable', 'integer'],
            // Obligatorio con la 133/99 (lo exige el service); para el docente se ignora.
            'id_docente' => ['nullable', 'integer'],
        ], [
            'fecha.required' => 'La fecha es obligatoria.',
            'tipo.in' => 'El tipo debe ser positiva o negativa.',
            'motivo.required' => 'El motivo de la observación es obligatorio.',
        ]);
    }
}
