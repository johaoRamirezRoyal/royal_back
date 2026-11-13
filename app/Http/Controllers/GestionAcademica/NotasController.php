<?php

namespace App\Http\Controllers\GestionAcademica;

use App\Enums\TipoPerfil;
use App\Http\Controllers\Controller;
use App\Models\GestionAcademica\EscalaValoracion;
use App\Models\GestionAcademica\Proposito;
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
 * GestionAcademicaController: calificar es autoservicio del docente y la consulta (152) no
 * debe abrir nada más del módulo.
 *
 * - Escala, propósitos y método por nivel: opción 99.
 * - Referentes y su estado (guardar referentes, ver plan, cambiar estado): los docentes con carga
 *   en el grado (cada uno en sus asignaturas) y quien tenga la 153 (gestionar) o la 154 (aprobar)
 *   en su nivel. El service decide qué acción cabe en cada estado y qué asignaturas edita cada uno.
 */
class NotasController extends Controller implements HasMiddleware
{
    private const REFERENTES = [Permiso::REFERENTES_GESTIONAR, Permiso::REFERENTES_APROBAR];

    public static function middleware(): array
    {
        return [
            new Middleware(Permiso::ruta([Permiso::GESTION_ACADEMICA]), only: [
                'listarEscalas', 'crearEscala', 'actualizarEscala', 'ordenarEscalas',
                'listarPropositos', 'crearProposito', 'actualizarProposito', 'ordenarPropositos',
                'listarMetodosNivel', 'cambiarMetodoNivel',
            ]),
            new Middleware(Permiso::ruta(self::REFERENTES, [TipoPerfil::DOCENTE]), only: ['guardarReferentes', 'cambiarEstadoPlan']),
            new Middleware(Permiso::ruta(self::REFERENTES), only: ['gradosReferentes']),
            new Middleware(Permiso::ruta([Permiso::GESTION_ACADEMICA, Permiso::NOTAS_CONSULTAR, ...self::REFERENTES], [TipoPerfil::DOCENTE]), only: ['verPlan', 'vistaCurso']),
            new Middleware(Permiso::ruta([], [TipoPerfil::DOCENTE]), only: ['misCursos', 'planilla', 'guardarPlanilla', 'guardarObservaciones']),
            new Middleware(Permiso::ruta([Permiso::REFERENTES_APROBAR]), only: ['ajustar', 'cerrar', 'reabrirCierre']),
            new Middleware(Permiso::ruta([Permiso::GESTION_ACADEMICA, Permiso::NOTAS_CONSULTAR, ...self::REFERENTES]), only: ['filtrosCursos']),
        ];
    }

    public function __construct(private NotasService $service) {}

    /**
     * Quién pide, para NotasService: si gestiona (153) o aprueba (154) referentes y en qué niveles
     * académicos. Con la 99 (o Super Admin, que la tiene) vale para todos los niveles.
     */
    private function actor(Request $request): array
    {
        $id = $request->user()->id_user;

        return [
            'id' => $id,
            'gestiona' => PermisosPerfil::tiene($request, Permiso::REFERENTES_GESTIONAR),
            'aprueba' => PermisosPerfil::tiene($request, Permiso::REFERENTES_APROBAR),
            'niveles' => PermisosPerfil::tiene($request, Permiso::GESTION_ACADEMICA) ? null : $this->service->nivelesDeUsuario($id),
        ];
    }

    /** ¿Puede ver el curso? Lectura global con 99/152; si no, docente con carga en el grado o 153/154 de su nivel. */
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

        return $this->apiResponse($this->service->ordenar(EscalaValoracion::class, $body['ids']));
    }

    // ─── Propósitos y método por nivel ──────────────────────────

    private function reglasProposito(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'descripcion' => ['nullable', 'string', 'max:500'],
            'activo' => ['nullable', 'boolean'],
        ];
    }

    public function listarPropositos()
    {
        return $this->apiResponse($this->service->listarPropositos());
    }

    public function crearProposito(Request $request)
    {
        return $this->apiResponse($this->service->guardarProposito($request->validate($this->reglasProposito())));
    }

    public function actualizarProposito(Request $request, int $id)
    {
        return $this->apiResponse($this->service->guardarProposito($request->validate($this->reglasProposito()), $id));
    }

    /** { ids: [id...] } propósitos en el orden nuevo: cada uno queda con su posición como orden. */
    public function ordenarPropositos(Request $request)
    {
        $body = $request->validate([
            'ids' => ['required', 'array', 'max:50'],
            'ids.*' => ['integer', 'distinct', 'exists:academico_proposito,id'],
        ]);

        return $this->apiResponse($this->service->ordenar(Proposito::class, $body['ids']));
    }

    public function listarMetodosNivel()
    {
        return $this->apiResponse($this->service->listarMetodosNivel());
    }

    /** { metodo: CUALITATIVA | CUANTITATIVA | AMBAS } */
    public function cambiarMetodoNivel(Request $request, int $id)
    {
        $body = $request->validate(['metodo' => ['required', Rule::in(NivelAcademico::METODOS)]]);

        return $this->apiResponse($this->service->cambiarMetodoNivel($id, $body['metodo']));
    }

    // ─── Referentes ─────────────────────────────────────────────

    private function reglasCursoPeriodo(): array
    {
        return [
            'id_curso' => ['required', 'integer'],
            'id_periodo' => ['required', 'integer'],
        ];
    }

    public function verPlan(Request $request)
    {
        $q = $request->validate($this->reglasCursoPeriodo());
        $actor = $this->actor($request);

        if (! $this->veCurso($request, $actor, $q['id_curso'])) {
            return $this->error('No tienes acceso a los referentes de este grado', 403);
        }

        return $this->apiResponse($this->service->verPlan($actor, $q['id_curso'], $q['id_periodo']));
    }

    /** { id_curso, id_periodo, accion: enviar|aprobar|devolver|reabrir, comentario? } */
    public function cambiarEstadoPlan(Request $request)
    {
        $body = $request->validate($this->reglasCursoPeriodo() + [
            'accion' => ['required', 'in:enviar,aprobar,devolver,reabrir'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->apiResponse($this->service->cambiarEstado(
            $this->actor($request), $body['id_curso'], $body['id_periodo'], $body['accion'], $body['comentario'] ?? null,
        ));
    }

    /** Grados del nivel de quien gestiona referentes, con el estado de cada plan. ?id_periodo= */
    public function gradosReferentes(Request $request)
    {
        $q = $request->validate(['id_periodo' => ['nullable', 'integer']]);

        return $this->apiResponse($this->service->gradosDelNivel($this->actor($request), $q['id_periodo'] ?? null));
    }

    /**
     * { id_curso (cualquiera del grado), id_periodo,
     *   referentes: [{ id?, id_proposito, id_asignatura, descripcion, subreferentes?: [{ id?, descripcion }] }] }
     */
    public function guardarReferentes(Request $request)
    {
        $body = $request->validate($this->reglasCursoPeriodo() + [
            'referentes' => ['present', 'array', 'max:100'],
            'referentes.*.id' => ['nullable', 'integer'],
            // Propósito y asignatura (obligatorios; los valida el service contra el grado).
            'referentes.*.id_proposito' => ['required', 'integer'],
            'referentes.*.id_asignatura' => ['required', 'integer'],
            'referentes.*.descripcion' => ['required', 'string', 'max:1000'],
            'referentes.*.subreferentes' => ['nullable', 'array', 'max:30'],
            'referentes.*.subreferentes.*.id' => ['nullable', 'integer'],
            'referentes.*.subreferentes.*.descripcion' => ['required', 'string', 'max:1000'],
        ], [
            'referentes.*.id_proposito.required' => 'Cada referente debe pertenecer a un propósito.',
            'referentes.*.id_asignatura.required' => 'Cada referente debe pertenecer a una asignatura.',
            'referentes.*.descripcion.required' => 'Cada referente necesita una descripción.',
            'referentes.*.subreferentes.*.descripcion.required' => 'Cada subreferente necesita una descripción.',
            'referentes.*.descripcion.max' => 'Un referente no puede superar los 1000 caracteres.',
            'referentes.*.subreferentes.*.descripcion.max' => 'Un subreferente no puede superar los 1000 caracteres.',
        ]);

        // Docentes con carga en el grado o 153/154 del nivel; el service decide según el estado.
        return $this->apiResponse($this->service->guardarReferentes($this->actor($request), $body['id_curso'], $body['id_periodo'], $body['referentes']));
    }

    // ─── Calificación, vista del curso y cierre ─────────────────

    public function misCursos(Request $request)
    {
        return $this->apiResponse($this->service->misCursos($request->user()->id_user));
    }

    /** Cursos para elegir en la vista de curso: todos con 99/152; si no, los del nivel de quien gestiona. */
    public function filtrosCursos(Request $request)
    {
        $actor = $this->actor($request);
        $todos = PermisosPerfil::tiene($request, Permiso::GESTION_ACADEMICA, Permiso::NOTAS_CONSULTAR);

        return $this->apiResponse($this->service->filtrosCursos($todos ? null : $actor['niveles']));
    }

    /** ?id_curso&id_periodo&id_asignatura: la planilla del docente con carga en esa asignatura y curso. */
    public function planilla(Request $request)
    {
        $q = $request->validate($this->reglasCursoPeriodo() + ['id_asignatura' => ['required', 'integer']]);

        return $this->apiResponse($this->service->planilla($request->user()->id_user, $q['id_curso'], $q['id_periodo'], $q['id_asignatura']));
    }

    /** { id_curso, id_periodo, id_asignatura, estudiantes: [{ id_estudiante, notas: [{ id_referente, id_escala|null }] }] } */
    public function guardarPlanilla(Request $request)
    {
        $body = $request->validate($this->reglasCursoPeriodo() + [
            'id_asignatura' => ['required', 'integer'],
            'estudiantes' => ['required', 'array'],
            'estudiantes.*.id_estudiante' => ['required', 'integer', 'distinct'],
            'estudiantes.*.notas' => ['present', 'array'],
            'estudiantes.*.notas.*.id_referente' => ['required', 'integer'],
            'estudiantes.*.notas.*.id_escala' => ['present', 'nullable', 'integer'],
        ]);

        return $this->apiResponse($this->service->guardarPlanilla(
            $request->user()->id_user, $body['id_curso'], $body['id_periodo'], $body['id_asignatura'], $body['estudiantes'],
        ));
    }

    /** ?id_curso&id_periodo: todo lo calificado en el curso. Director del curso, 153/154 de su nivel, 99 o 152. */
    public function vistaCurso(Request $request)
    {
        $q = $request->validate($this->reglasCursoPeriodo());
        $actor = $this->actor($request);
        $ve = PermisosPerfil::tiene($request, Permiso::GESTION_ACADEMICA, Permiso::NOTAS_CONSULTAR)
            || $this->service->gestionaCurso($actor, $q['id_curso'])
            || $this->service->dirige($actor['id'], $q['id_curso']);

        if (! $ve) {
            return $this->error('No tienes acceso a las notas de este curso', 403);
        }

        return $this->apiResponse($this->service->vistaCurso($actor, $q['id_curso'], $q['id_periodo']));
    }

    /** { id_curso, id_periodo, observaciones: [{ id_estudiante, observacion|null }] } (director del curso). */
    public function guardarObservaciones(Request $request)
    {
        $body = $request->validate($this->reglasCursoPeriodo() + [
            'observaciones' => ['required', 'array'],
            'observaciones.*.id_estudiante' => ['required', 'integer', 'distinct'],
            'observaciones.*.observacion' => ['present', 'nullable', 'string', 'max:3000'],
        ], [
            'observaciones.*.observacion.max' => 'Una observación no puede superar los 3000 caracteres.',
        ]);

        return $this->apiResponse($this->service->guardarObservaciones($request->user()->id_user, $body['id_curso'], $body['id_periodo'], $body['observaciones']));
    }

    /** { id_curso, id_periodo, id_estudiante, id_referente, id_escala|null } (154 del nivel). */
    public function ajustar(Request $request)
    {
        $body = $request->validate($this->reglasCursoPeriodo() + [
            'id_estudiante' => ['required', 'integer'],
            'id_referente' => ['required', 'integer'],
            'id_escala' => ['present', 'nullable', 'integer'],
        ]);

        return $this->apiResponse($this->service->ajustar(
            $this->actor($request), $body['id_curso'], $body['id_periodo'], $body['id_estudiante'], $body['id_referente'], $body['id_escala'],
        ));
    }

    /** { id_curso, id_periodo }: cierra el periodo en el curso y guarda los boletines (154 del nivel). */
    public function cerrar(Request $request)
    {
        $body = $request->validate($this->reglasCursoPeriodo());

        return $this->apiResponse($this->service->cerrar($this->actor($request), $body['id_curso'], $body['id_periodo']));
    }

    /** { id_curso, id_periodo }: reabre el periodo en el curso; borra sus boletines (154 del nivel). */
    public function reabrirCierre(Request $request)
    {
        $body = $request->validate($this->reglasCursoPeriodo());

        return $this->apiResponse($this->service->reabrirCierre($this->actor($request), $body['id_curso'], $body['id_periodo']));
    }
}
