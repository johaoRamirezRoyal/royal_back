<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Http\Controllers\Controller;
use App\Models\GestionAcademica\Dimension;
use App\Models\Usuarios\NivelAcademico;
use App\Services\GestionAcademica\NotasService;
use App\Services\Usuarios\PermisosPerfil;
use App\Support\Permiso;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * Notas cualitativas (ver docs/contexto/modulos/notas.md). Controller aparte de
 * GestionAcademicaController: calificar es autoservicio del director de grupo y la consulta
 * (149) no debe abrir nada más del módulo. Los logros son por grado (no por asignatura).
 *
 * - Escala, dimensiones y método por nivel: opción 99.
 * - Calificar (mis-cursos, guardar planilla): perfiles docente; el service exige además ser el
 *   director de grupo del curso y que los logros del grado estén APROBADOS.
 * - Logros y su estado (guardar logros, ver plan, cambiar estado): los directores de grupo del
 *   grado y quien tenga la 150 (gestionar) o la 151 (aprobar) en su nivel. El service decide qué
 *   acción cabe en cada estado.
 * - Ver planilla: director del grado, 150/151 de su nivel, 99 o 149 (solo lectura).
 * - Consulta por curso: 99 o 149.
 */
class NotasController extends Controller implements HasMiddleware
{
    private const LOGROS = [Permiso::LOGROS_GESTIONAR, Permiso::LOGROS_APROBAR];

    public static function middleware(): array
    {
        return [
            new Middleware(Permiso::ruta([Permiso::GESTION_ACADEMICA]), only: [
                'listarEscalas', 'crearEscala', 'actualizarEscala', 'ordenarEscalas',
                'listarDimensiones', 'crearDimension', 'actualizarDimension', 'ordenarDimensiones', 'asignarGrados',
                'listarMetodosNivel', 'cambiarMetodoNivel', 'sincronizarUnidades',
            ]),
            new Middleware(Permiso::ruta([], Permiso::PERFILES_DOCENTE), only: ['misCursos', 'guardarPlanilla']),
            new Middleware(Permiso::ruta(self::LOGROS, Permiso::PERFILES_DOCENTE), only: ['guardarLogros', 'cambiarEstadoPlan']),
            new Middleware(Permiso::ruta(self::LOGROS), only: ['gradosLogros']),
            new Middleware(Permiso::ruta([Permiso::GESTION_ACADEMICA, Permiso::NOTAS_CONSULTAR, ...self::LOGROS], Permiso::PERFILES_DOCENTE), only: ['planilla', 'verPlan']),
            new Middleware(Permiso::ruta([Permiso::GESTION_ACADEMICA, Permiso::NOTAS_CONSULTAR]), only: ['filtrosConsulta', 'consulta']),
        ];
    }

    public function __construct(private NotasService $service) {}

    /**
     * Quién pide, para NotasService: si gestiona (150) o aprueba (151) logros y en qué niveles
     * académicos. Con la 99 (o Super Admin, que la tiene) vale para todos los niveles.
     */
    private function actor(Request $request): array
    {
        $id = $request->user()->id_user;

        return [
            'id' => $id,
            'gestiona' => PermisosPerfil::tiene($request, Permiso::LOGROS_GESTIONAR),
            'aprueba' => PermisosPerfil::tiene($request, Permiso::LOGROS_APROBAR),
            'niveles' => PermisosPerfil::tiene($request, Permiso::GESTION_ACADEMICA) ? null : $this->service->nivelesDeUsuario($id),
        ];
    }

    /** ¿Puede ver el curso? Lectura global con 99/149; si no, ser director del grado o 150/151 de su nivel. */
    private function veCurso(Request $request, array $actor, int $idCurso): bool
    {
        return PermisosPerfil::tiene($request, Permiso::GESTION_ACADEMICA, Permiso::NOTAS_CONSULTAR)
            || $this->service->participa($actor, $idCurso);
    }

    private function reglasEscala(): array
    {
        return [
            // Niveles a los que aplica; vacío = escala general.
            'niveles' => ['present', 'array'],
            'niveles.*' => ['integer', 'distinct', 'exists:nivel_academico,id'],
            'nombre' => ['required', 'string', 'max:50'],
            'abreviatura' => ['nullable', 'string', 'max:10'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:20'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    public function listarEscalas()
    {
        return $this->apiResponse($this->service->listarEscalas());
    }

    public function crearEscala(Request $request)
    {
        return $this->apiResponse($this->service->guardarEscala($request->validate($this->reglasEscala())));
    }

    public function actualizarEscala(Request $request, int $id)
    {
        return $this->apiResponse($this->service->guardarEscala($request->validate($this->reglasEscala()), $id));
    }

    /** { ids: [id...] } en el orden nuevo: cada uno queda con su posición como orden. */
    public function ordenarEscalas(Request $request)
    {
        $body = $request->validate([
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['integer', 'distinct', 'exists:academico_escala_valoracion,id'],
        ]);

        return $this->apiResponse($this->service->ordenar(\App\Models\GestionAcademica\EscalaValoracion::class, $body['ids']));
    }

    /** { ids: [id...] } dimensiones en el orden nuevo: cada una queda con su posición como orden. */
    public function ordenarDimensiones(Request $request)
    {
        $body = $request->validate([
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['integer', 'distinct', 'exists:academico_dimension,id'],
        ]);

        return $this->apiResponse($this->service->ordenar(\App\Models\GestionAcademica\Dimension::class, $body['ids']));
    }

    // ─── Dimensiones y método por nivel ─────────────────────────

    private function reglasDimension(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:80'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'orden' => ['nullable', 'integer', 'min:0'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    /** ?tipo=DIMENSION|AREA|ASIGNATURA (por defecto DIMENSION). */
    public function listarDimensiones(Request $request)
    {
        $q = $request->validate(['tipo' => ['nullable', Rule::in([Dimension::DIMENSION, Dimension::AREA, Dimension::ASIGNATURA])]]);

        return $this->apiResponse($this->service->listarDimensiones($q['tipo'] ?? null));
    }

    /** Crea / actualiza una unidad ASIGNATURA por asignatura y la vincula a los grados donde tiene carga. */
    public function sincronizarUnidades()
    {
        return $this->apiResponse($this->service->sincronizarUnidadesAsignatura());
    }

    public function crearDimension(Request $request)
    {
        return $this->apiResponse($this->service->guardarDimension($request->validate($this->reglasDimension())));
    }

    public function actualizarDimension(Request $request, int $id)
    {
        return $this->apiResponse($this->service->guardarDimension($request->validate($this->reglasDimension()), $id));
    }

    /** { grados: [id...] } reemplaza los grados que se evalúan con la dimensión. */
    public function asignarGrados(Request $request, int $id)
    {
        $body = $request->validate([
            'grados' => ['present', 'array'],
            'grados.*' => ['integer', 'distinct', 'exists:academico_grado,id'],
        ]);

        return $this->apiResponse($this->service->asignarGrados($id, $body['grados']));
    }

    public function listarMetodosNivel()
    {
        return $this->apiResponse($this->service->listarMetodosNivel());
    }

    /**
     * { metodo?: CUALITATIVA | CUANTITATIVA | AMBAS, tipo_unidad?: DIMENSION | ASIGNATURA,
     *   calificador?: DIRECTOR_GRUPO | DOCENTE_ASIGNATURA }, al menos uno. Las combinaciones las
     * valida el service (NotasService::errorDeModo) con mensajes en español.
     */
    public function cambiarMetodoNivel(Request $request, int $id)
    {
        $body = $request->validate([
            'metodo' => ['required_without_all:tipo_unidad,calificador', Rule::in(NivelAcademico::METODOS)],
            'tipo_unidad' => ['sometimes', 'string', 'max:12'],
            'calificador' => ['sometimes', 'string', 'max:20'],
        ]);

        return $this->apiResponse($this->service->cambiarMetodoNivel($id, $body));
    }

    public function misCursos(Request $request)
    {
        return $this->apiResponse($this->service->misCursos($request->user()->id_user));
    }

    private function reglasPlanilla(): array
    {
        return [
            'id_curso' => ['required', 'integer'],
            'id_periodo' => ['required', 'integer'],
        ];
    }

    public function planilla(Request $request)
    {
        $q = $request->validate($this->reglasPlanilla());
        $actor = $this->actor($request);

        if (! $this->veCurso($request, $actor, $q['id_curso'])) {
            return $this->error('No tienes acceso a las notas de este curso', 403);
        }

        return $this->apiResponse($this->service->planilla($actor, $q['id_curso'], $q['id_periodo']));
    }

    public function verPlan(Request $request)
    {
        $q = $request->validate($this->reglasPlanilla());
        $actor = $this->actor($request);

        if (! $this->veCurso($request, $actor, $q['id_curso'])) {
            return $this->error('No tienes acceso a los logros de este grado', 403);
        }

        return $this->apiResponse($this->service->verPlan($actor, $q['id_curso'], $q['id_periodo']));
    }

    /** { id_curso, id_periodo, accion: enviar|aprobar|devolver|reabrir, comentario? } */
    public function cambiarEstadoPlan(Request $request)
    {
        $body = $request->validate($this->reglasPlanilla() + [
            'accion' => ['required', 'in:enviar,aprobar,devolver,reabrir'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->apiResponse($this->service->cambiarEstado(
            $this->actor($request), $body['id_curso'], $body['id_periodo'], $body['accion'], $body['comentario'] ?? null,
        ));
    }

    /** Grados del nivel de quien gestiona logros, con el estado de cada plan. ?id_periodo= */
    public function gradosLogros(Request $request)
    {
        $q = $request->validate(['id_periodo' => ['nullable', 'integer']]);

        return $this->apiResponse($this->service->gradosDelNivel($this->actor($request), $q['id_periodo'] ?? null));
    }

    /** { id_curso (cualquiera del grado), id_periodo, logros: [{ id?, id_dimension, descripcion }] } */
    public function guardarLogros(Request $request)
    {
        $body = $request->validate($this->reglasPlanilla() + [
            'logros' => ['present', 'array', 'max:60'],
            'logros.*.id' => ['nullable', 'integer'],
            // La dimensión del grado a la que pertenece (obligatoria; la valida el service).
            'logros.*.id_dimension' => ['required', 'integer'],
            'logros.*.descripcion' => ['required', 'string', 'max:1000'],
        ], [
            'logros.*.id_dimension.required' => 'Cada logro debe pertenecer a una dimensión.',
            'logros.*.descripcion.required' => 'Cada logro necesita una descripción.',
            'logros.*.descripcion.max' => 'Un logro no puede superar los 1000 caracteres.',
        ]);

        // Directores del grado o 150/151 del nivel; el service decide según el estado.
        return $this->apiResponse($this->service->guardarLogros($this->actor($request), $body['id_curso'], $body['id_periodo'], $body['logros']));
    }

    /** { id_curso, id_periodo, estudiantes: [{ id_estudiante, dimensiones: [{ id_dimension, id_escala?, observacion?, logros?: [{ id_logro, id_escala }] }] }] } */
    public function guardarPlanilla(Request $request)
    {
        $body = $request->validate($this->reglasPlanilla() + [
            'estudiantes' => ['required', 'array'],
            'estudiantes.*.id_estudiante' => ['required', 'integer', 'distinct'],
            'estudiantes.*.dimensiones' => ['present', 'array'],
            'estudiantes.*.dimensiones.*.id_dimension' => ['required', 'integer'],
            'estudiantes.*.dimensiones.*.id_escala' => ['nullable', 'integer'],
            'estudiantes.*.dimensiones.*.observacion' => ['nullable', 'string', 'max:2000'],
            'estudiantes.*.dimensiones.*.logros' => ['nullable', 'array'],
            'estudiantes.*.dimensiones.*.logros.*.id_logro' => ['required', 'integer'],
            'estudiantes.*.dimensiones.*.logros.*.id_escala' => ['required', 'integer'],
        ], [
            'estudiantes.*.dimensiones.*.observacion.max' => 'Una observación no puede superar los 2000 caracteres.',
        ]);

        $idDocente = $request->user()->id_user;
        if (! $this->service->dirige($idDocente, $body['id_curso'])) {
            return $this->error('Solo el director de grupo del curso califica', 403);
        }

        return $this->apiResponse($this->service->guardarPlanilla($idDocente, $body['id_curso'], $body['id_periodo'], $body['estudiantes']));
    }

    public function filtrosConsulta()
    {
        return $this->apiResponse($this->service->filtrosConsulta());
    }

    public function consulta(Request $request)
    {
        $q = $request->validate(['id_curso' => ['required', 'integer'], 'id_periodo' => ['required', 'integer']]);

        return $this->apiResponse($this->service->consulta($q['id_curso'], $q['id_periodo']));
    }
}
