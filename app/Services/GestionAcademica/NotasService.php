<?php

namespace App\Services\GestionAcademica;

use App\Models\AnioEscolar\PeriodoAcademico;
use App\Models\Areas\Cursos;
use App\Models\GestionAcademica\EscalaValoracion;
use App\Models\GestionAcademica\Proposito;
use App\Models\GestionAcademica\Referente;
use App\Models\GestionAcademica\ReferentePlan;
use App\Models\GestionAcademica\ReferentePlanHistorial;
use App\Models\GestionAcademica\ValoracionReferente;
use App\Models\Usuarios\NivelAcademico;
use App\Models\Usuarios\Usuario;
use App\Services\AnioEscolar\AnioEscolarServices;
use App\Services\Service;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Notas cualitativas: escala de desempeños, propósitos (catálogo único) y referentes por grado,
 * periodo, propósito y asignatura, con subreferentes y un plan de aprobación. Ver
 * docs/contexto/modulos/notas.md.
 *
 * Los referentes de cada asignatura los define el docente con carga en ella en algún curso del
 * grado; quien gestiona (153/154 de su nivel) edita todos y envía a revisión.
 */
class NotasService extends Service
{
    public function __construct(private AnioEscolarServices $anios) {}

    // ─── Escala ─────────────────────────────────────────────────

    public function listarEscalas(): array
    {
        try {
            $escalas = EscalaValoracion::with('niveles:id')->orderBy('orden')->orderBy('id')->get()
                ->map(fn (EscalaValoracion $e) => $e->only(['id', 'nombre', 'abreviatura', 'descripcion', 'color', 'orden', 'activo'])
                    + ['niveles' => $e->niveles->pluck('id')->map(fn ($id) => (int) $id)->values()]);

            return ['error' => false, 'message' => 'Escalas de valoración', 'data' => $escalas];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function guardarEscala(array $data, ?int $id = null): array
    {
        try {
            $escala = $id ? EscalaValoracion::find($id) : new EscalaValoracion();
            if (! $escala) {
                return ['error' => true, 'message' => 'No se encontró el desempeño'];
            }
            DB::transaction(function () use ($escala, $data) {
                $escala->fill($data)->save();
                // Sin niveles = escala general.
                $escala->niveles()->sync($data['niveles'] ?? []);
            });

            return ['error' => false, 'message' => $id ? 'Desempeño actualizado' : 'Desempeño creado', 'data' => $escala];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Reordena lo arrastrado en Configuración académica (desempeños de una escala, o los
     * propósitos): cada id recibe su posición (1, 2, 3...). Los que no vienen conservan su orden.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelo  EscalaValoracion o Proposito
     * @param  array<int, int>  $ids
     */
    public function ordenar(string $modelo, array $ids): array
    {
        try {
            DB::transaction(function () use ($modelo, $ids) {
                foreach (array_values($ids) as $i => $id) {
                    $modelo::whereKey($id)->update(['orden' => $i + 1]);
                }
            });

            return ['error' => false, 'message' => 'Orden guardado'];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Propósitos y método por nivel (Configuración académica, 99) ─────────

    public function listarPropositos(): array
    {
        try {
            $data = Proposito::orderBy('orden')->orderBy('nombre')->get(['id', 'nombre', 'descripcion', 'orden', 'activo']);

            return ['error' => false, 'message' => 'Propósitos', 'data' => $data];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function guardarProposito(array $data, ?int $id = null): array
    {
        try {
            $proposito = $id ? Proposito::find($id) : new Proposito(['orden' => (int) Proposito::max('orden') + 1]);
            if (! $proposito) {
                return ['error' => true, 'message' => 'No se encontró el propósito'];
            }
            $proposito->fill($data)->save();

            return ['error' => false, 'message' => $id ? 'Propósito actualizado' : 'Propósito creado', 'data' => $proposito];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function listarMetodosNivel(): array
    {
        try {
            return ['error' => false, 'message' => 'Métodos de calificación', 'data' => NivelAcademico::orderBy('id')->get(['id', 'nombre', 'metodo_calificacion'])];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function cambiarMetodoNivel(int $idNivel, string $metodo): array
    {
        try {
            $nivel = NivelAcademico::find($idNivel);
            if (! $nivel) {
                return ['error' => true, 'message' => 'No se encontró el nivel'];
            }
            $nivel->update(['metodo_calificacion' => $metodo]);

            return ['error' => false, 'message' => "{$nivel->nombre}: calificación actualizada", 'data' => $nivel->only(['id', 'nombre', 'metodo_calificacion'])];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Periodos y acceso ──────────────────────────────────────

    /** Se califica desde que empieza el periodo hasta su fecha límite (o su fin). */
    public static function periodoEditable(PeriodoAcademico $periodo): bool
    {
        $hoy = Carbon::today()->toDateString();
        $limite = $periodo->fecha_limite_notas ?? $periodo->fecha_fin;

        return $hoy >= $periodo->fecha_inicio && $hoy <= $limite;
    }

    /**
     * Periodos del año escolar vigente. No filtra por `activo`: el cron
     * periodos:desactivar-vencidos los apaga al pasar fecha_fin, y aún pueden estar en
     * su plazo de corrección.
     */
    private function periodosDelAnio(): Collection
    {
        $anio = $this->anios->obtenerUltimoAnioEscolar()['data'] ?? null;
        if (! $anio) {
            return collect();
        }

        return PeriodoAcademico::where('id_anio_escolar', $anio->id)->orderBy('fecha_inicio')->get()
            ->map(fn (PeriodoAcademico $p) => ['id' => $p->id, 'nombre' => $p->nombre, 'editable' => self::periodoEditable($p)]);
    }

    /** Ids de las asignaturas en las que el docente tiene carga activa en ese curso (o esos cursos). */
    private function asignaturasConCarga(int $idUser, int|array $idsCurso): Collection
    {
        return DB::table('academico_carga_academica as c')
            ->join('academico_docente_asignatura as da', 'da.id', '=', 'c.id_docente_asignatura')
            ->whereIn('c.id_curso', (array) $idsCurso)->where('c.activo', 1)
            ->where('da.activo', 1)->where('da.id_docente', $idUser)
            ->distinct()->pluck('da.id_asignatura')->map(fn ($id) => (int) $id);
    }

    /** Asignaturas con carga activa en algún curso activo del grado: id → nombre, por nombre. */
    private function asignaturasDelGrado(int $idGrado): Collection
    {
        return DB::table('academico_carga_academica as c')
            ->join('academico_docente_asignatura as da', 'da.id', '=', 'c.id_docente_asignatura')
            ->join('curso as cu', 'cu.id', '=', 'c.id_curso')
            ->join('academico_asignatura as a', 'a.id', '=', 'da.id_asignatura')
            ->where('c.activo', 1)->where('da.activo', 1)->where('cu.activo', 1)->where('cu.id_grado', $idGrado)
            ->distinct()->orderBy('a.nombre')->pluck('a.nombre', 'a.id');
    }

    /** Cursos activos del grado, en orden académico, con su director de grupo. */
    private static function cursosDelGrado(int $idGrado): Collection
    {
        return Cursos::ordenarPorGrado(Cursos::with('director:id_user,nombre,apellido')
            ->where('activo', 1)->where('id_grado', $idGrado)
            ->get(['id', 'nombre', 'id_nivel', 'id_grado', 'id_director']));
    }

    private static function nombreDe(?Usuario $u): ?string
    {
        return $u ? (trim("{$u->nombre} {$u->apellido}") ?: null) : null;
    }

    /**
     * La parte cualitativa aplica a los cursos cuyo nivel académico califica CUALITATIVA o AMBAS
     * (nivel_academico.metodo_calificacion) y que tienen grado (los referentes son por grado).
     * Error listo para devolver, o null si aplica.
     */
    private function sinCualitativa(int $idCurso): ?array
    {
        $curso = Cursos::whereKey($idCurso)->with('nivel:id,metodo_calificacion')->first(['id', 'id_nivel', 'id_grado']);

        if (! NivelAcademico::califCualitativa($curso?->nivel?->metodo_calificacion)) {
            return ['error' => true, 'message' => 'El nivel de este curso se califica solo de forma cuantitativa: no tiene planilla cualitativa.'];
        }

        return $curso->id_grado === null
            ? ['error' => true, 'message' => 'Este curso no tiene grado: los referentes se definen por grado. Asígnaselo en Cursos.']
            : null;
    }

    /** Curso activo con grado cuyo nivel califica cualitativamente (para filtrar listas). */
    private static function esCursoCualitativo(Cursos $curso): bool
    {
        return $curso->activo && $curso->id_grado !== null && NivelAcademico::califCualitativa($curso->nivel?->metodo_calificacion);
    }

    /** Cursos cualitativos activos (con grado y nivel cargados), en orden académico. */
    private static function cursosCualitativos(?callable $filtro = null): Collection
    {
        $q = Cursos::with(['grado:id,nombre', 'nivel:id,nombre,metodo_calificacion', 'director:id_user,nombre,apellido'])
            ->where('activo', 1)->whereNotNull('id_grado');
        if ($filtro) {
            $filtro($q);
        }

        return Cursos::ordenarPorGrado($q->get(['id', 'nombre', 'id_nivel', 'id_grado', 'id_director', 'activo'])
            ->filter(fn (Cursos $c) => self::esCursoCualitativo($c))->values());
    }

    // ─── Plan de referentes (estado y seguimiento) ──────────────
    //
    // Los referentes son del grado (todos sus cursos los comparten). Cada docente con carga en
    // el grado define los de sus asignaturas; el coordinador / asistente del nivel (153 y 154)
    // edita todos y envía. Estados: BORRADOR → EN_REVISION → APROBADO, o DEVUELTO (vuelve a
    // editarse). Solo se califica con el plan APROBADO; para cambiarlo, quien aprueba lo reabre.
    // `$actor` lo arma NotasController::actor():
    //   ['id' => int, 'gestiona' => bool (153), 'aprueba' => bool (154), 'niveles' => ?int[] (null = todos)]

    /** Niveles académicos (nivel_academico.id) del usuario, por usuarios.id_nivel / id_nivel_2. */
    public function nivelesDeUsuario(int $idUser): array
    {
        $u = Usuario::with(['nivelRelacion', 'nivel2Relacion'])->find($idUser);

        return collect([$u?->nivelRelacion, $u?->nivel2Relacion])
            ->flatMap(fn ($nivel) => DocenteHorarioService::idsNivelAcademicoParaNivel($nivel))
            ->unique()->values()->all();
    }

    /**
     * Qué es quien pide respecto de los referentes del grado del curso. 'docente': tiene carga
     * activa en algún curso del grado ('asignaturas': en cuáles). 'gestiona' / 'aprueba': 153 / 154
     * en su nivel.
     */
    private function rol(array $actor, Cursos $curso): array
    {
        $enNivel = $actor['niveles'] === null || in_array($curso->id_nivel, $actor['niveles'], true);
        $asignaturas = $this->asignaturasConCarga($actor['id'], self::cursosDelGrado($curso->id_grado)->pluck('id')->all());

        return [
            'docente' => $asignaturas->isNotEmpty(),
            'asignaturas' => $asignaturas,
            'gestiona' => $enNivel && ($actor['gestiona'] || $actor['aprueba']),
            'aprueba' => $enNivel && $actor['aprueba'],
        ];
    }

    /** Los referentes se pueden trabajar hasta la fecha límite del periodo (también antes de que empiece). */
    private static function periodoAbiertoParaReferentes(PeriodoAcademico $periodo): bool
    {
        return Carbon::today()->toDateString() <= ($periodo->fecha_limite_notas ?? $periodo->fecha_fin);
    }

    /**
     * Acciones permitidas según el estado, el rol y el plazo. Solo quien gestiona envía a
     * revisión: así un docente no cierra el plan antes de que los demás carguen los suyos.
     */
    public static function acciones(string $estado, array $rol, bool $abierto): array
    {
        $editable = in_array($estado, [ReferentePlan::BORRADOR, ReferentePlan::DEVUELTO], true);
        $participa = $rol['docente'] || $rol['gestiona'];

        return [
            // En revisión solo quien aprueba ajusta los referentes.
            'editar' => $abierto && (($editable && $participa) || ($estado === ReferentePlan::EN_REVISION && $rol['aprueba'])),
            'enviar' => $abierto && $editable && $rol['gestiona'],
            'aprobar' => $estado === ReferentePlan::EN_REVISION && $rol['aprueba'],
            'devolver' => $estado === ReferentePlan::EN_REVISION && $rol['aprueba'],
            'reabrir' => $abierto && $estado === ReferentePlan::APROBADO && $rol['aprueba'],
        ];
    }

    private function buscarPlan(int $idGrado, int $idPeriodo): ReferentePlan
    {
        return ReferentePlan::firstOrNew(['id_grado' => $idGrado, 'id_periodo_academico' => $idPeriodo]);
    }

    /** Curso (con su grado, nivel y director) y periodo; null si alguno no existe. */
    private static function cursoYPeriodo(int $idCurso, int $idPeriodo): ?array
    {
        $curso = Cursos::with(['grado:id,nombre', 'director:id_user,nombre,apellido'])->find($idCurso, ['id', 'nombre', 'id_nivel', 'id_grado', 'id_director']);
        $periodo = PeriodoAcademico::find($idPeriodo);

        return $curso && $periodo ? [$curso, $periodo] : null;
    }

    private function registrar(ReferentePlan $plan, int $idUsuario, string $accion, ?string $detalle = null): void
    {
        ReferentePlanHistorial::create(['id_plan' => $plan->id, 'id_usuario' => $idUsuario, 'accion' => $accion, 'detalle' => $detalle, 'fecha' => now()]);
    }

    /** Referentes del grado en el periodo, en su orden, cada uno con sus subreferentes. */
    private function referentesDe(int $idGrado, int $idPeriodo): Collection
    {
        $todos = Referente::where('id_grado', $idGrado)->where('id_periodo_academico', $idPeriodo)
            ->orderBy('orden')->orderBy('id')
            ->get(['id', 'id_proposito', 'id_asignatura', 'id_padre', 'descripcion', 'orden']);
        $hijos = $todos->whereNotNull('id_padre')->groupBy('id_padre');

        return $todos->whereNull('id_padre')->map(fn (Referente $r) => $r->only(['id', 'id_proposito', 'id_asignatura', 'descripcion', 'orden']) + [
            'subreferentes' => ($hijos[$r->id] ?? collect())->map->only(['id', 'descripcion', 'orden'])->values(),
        ])->values();
    }

    /**
     * Ids de las asignaturas cuyos referentes puede editar quien pide (aparte del estado del plan,
     * que decide acciones()): quien gestiona, las del grado y las que ya tienen referentes (para
     * reubicar los de una asignatura que perdió su carga); el docente, las suyas con carga en el grado.
     */
    private static function asignaturasEditables(array $rol, Collection $delGrado, Collection $usadas): Collection
    {
        if ($rol['gestiona']) {
            return $delGrado->keys()->map(fn ($id) => (int) $id)->merge($usadas)->unique()->values();
        }

        return $rol['docente'] ? $rol['asignaturas']->values() : collect();
    }

    /**
     * Plan de referentes del grado del curso: estado, si lo comparten varios cursos, propósitos,
     * asignaturas del grado (con `editable`: si quien pide puede editar sus referentes, aparte del
     * estado del plan), referentes con sus subreferentes, historial (lo más reciente primero) y qué
     * puede hacer quien pide.
     */
    private function datosPlan(Cursos $curso, PeriodoAcademico $periodo, array $actor): array
    {
        $rol = $this->rol($actor, $curso);
        $referentes = $this->referentesDe($curso->id_grado, $periodo->id);
        $delGrado = $this->asignaturasDelGrado($curso->id_grado);
        $usadas = $referentes->pluck('id_asignatura')->map(fn ($id) => (int) $id)->unique();
        $editables = self::asignaturasEditables($rol, $delGrado, $usadas);
        // Una asignatura que ya no tiene carga en el grado pero conserva referentes también se
        // lista (por reubicar), con el nombre que tenga en el catálogo.
        $huerfanas = DB::table('academico_asignatura')->whereIn('id', $usadas->diff($delGrado->keys()->map(fn ($id) => (int) $id)))->pluck('nombre', 'id');

        $plan = $this->buscarPlan($curso->id_grado, $periodo->id);
        $historial = $plan->exists
            ? $plan->historial()->with('usuario:id_user,nombre,apellido')->orderByDesc('id')->get()
                ->map(fn ($h) => [
                    'id' => $h->id,
                    'accion' => $h->accion,
                    'detalle' => $h->detalle,
                    'fecha' => $h->fecha,
                    'usuario' => self::nombreDe($h->usuario) ?? 'Usuario',
                ])
            : collect();

        return [
            'estado' => $plan->estado,
            // El grado ("Cuarto") cuyos cursos comparten estos referentes.
            'grado' => (string) $curso->grado?->nombre,
            'compartido' => self::cursosDelGrado($curso->id_grado)->count() > 1,
            'propositos' => Proposito::where('activo', 1)
                ->orWhereIn('id', $referentes->pluck('id_proposito'))
                ->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre', 'descripcion', 'activo']),
            'asignaturas' => collect($delGrado->all() + $huerfanas->all())
                ->map(fn ($nombre, $id) => [
                    'id' => (int) $id,
                    'nombre' => $nombre,
                    'con_carga' => $delGrado->has($id),
                    'editable' => $editables->contains((int) $id),
                ])->values(),
            'referentes' => $referentes,
            'historial' => $historial,
            'puede' => self::acciones($plan->estado, $rol, self::periodoAbiertoParaReferentes($periodo)),
        ];
    }

    /** Lo que ve cualquiera con acceso al grado (docente con carga, 153/154 de su nivel, 99, 152). */
    public function verPlan(array $actor, int $idCurso, int $idPeriodo): array
    {
        if ($error = $this->sinCualitativa($idCurso)) {
            return $error;
        }

        try {
            if (! $cp = self::cursoYPeriodo($idCurso, $idPeriodo)) {
                return ['error' => true, 'message' => 'Curso o periodo no encontrado'];
            }
            [$curso, $periodo] = $cp;

            return ['error' => false, 'message' => 'Plan de referentes', 'data' => [
                'curso' => ['id' => $curso->id, 'nombre' => $curso->nombre],
                'periodo' => ['id' => $periodo->id, 'nombre' => $periodo->nombre],
            ] + $this->datosPlan($curso, $periodo, $actor)];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** ¿Participa en los referentes del grado del curso? Docente con carga en el grado, o 153/154 de su nivel. (99/152 lo decide el controller.) */
    public function participa(array $actor, int $idCurso): bool
    {
        $curso = Cursos::find($idCurso, ['id', 'id_nivel', 'id_grado']);
        if (! $curso || $curso->id_grado === null) {
            return false;
        }
        $rol = $this->rol($actor, $curso);

        return $rol['docente'] || $rol['gestiona'];
    }

    /**
     * Aplana los referentes enviados (cada uno con sus subreferentes) en una sola lista en orden.
     * Un subreferente hereda propósito y asignatura de su padre; `padre` es el índice del padre en
     * la lista e `id_padre` su id si ya existía.
     *
     * @param  array<int, array{id?: int|null, id_proposito: int, id_asignatura: int, descripcion: string, subreferentes?: array<int, array{id?: int|null, descripcion: string}>}>  $referentes
     * @return array<int, array{id: ?int, id_proposito: int, id_asignatura: int, id_padre: ?int, padre: ?int, descripcion: string}>
     */
    public static function aplanar(array $referentes): array
    {
        $planos = [];
        foreach (array_values($referentes) as $r) {
            $i = count($planos);
            $base = ['id_proposito' => (int) $r['id_proposito'], 'id_asignatura' => (int) $r['id_asignatura']];
            $planos[] = ['id' => isset($r['id']) ? (int) $r['id'] : null] + $base + ['id_padre' => null, 'padre' => null, 'descripcion' => trim((string) $r['descripcion'])];
            foreach ($r['subreferentes'] ?? [] as $s) {
                $planos[] = ['id' => isset($s['id']) ? (int) $s['id'] : null] + $base + [
                    'id_padre' => isset($r['id']) ? (int) $r['id'] : null,
                    'padre' => $i,
                    'descripcion' => trim((string) $s['descripcion']),
                ];
            }
        }

        return $planos;
    }

    /**
     * Qué hace guardarReferentes con la lista aplanada, según las asignaturas que quien guarda
     * puede editar (`$editables`). Pura (test: CambiosDeReferentesTest).
     *
     * - Un referente de una asignatura no editable que viene sin cambios (mismo propósito,
     *   asignatura, padre y texto) se ignora (el frontend manda la lista completa); si viene
     *   cambiado, movido o nuevo en esa asignatura, se rechaza.
     * - Se eliminan solo los existentes de asignaturas editables que no vienen: los de los demás
     *   docentes se conservan. Quitar un referente quita sus subreferentes (no vienen anidados).
     *
     * @param  array<int, array{id_proposito: int, id_asignatura: int, id_padre: ?int, descripcion: string}>  $existentes  por id
     * @param  array<int, array{id: ?int, id_proposito: int, id_asignatura: int, id_padre: ?int, descripcion: string}>  $enviados  de aplanar()
     * @param  array<int, int>  $editables  ids de asignatura
     * @return array{rechazada: ?int, ignorar: array<int, int>, eliminar: array<int, int>}
     *   rechazada: la primera asignatura no editable que se intentó tocar; ignorar: índices de
     *   `$enviados` que no se tocan (también ids ajenos al grado y periodo); eliminar: ids.
     */
    public static function cambiosDeReferentes(array $existentes, array $enviados, array $editables): array
    {
        $puede = fn ($idAsignatura) => in_array((int) $idAsignatura, array_map('intval', $editables), true);
        $ignorar = $vienen = [];

        foreach (array_values($enviados) as $i => $r) {
            $id = $r['id'] ?? null;
            $destino = (int) $r['id_asignatura'];
            if ($id !== null) {
                if (! isset($existentes[$id])) {
                    $ignorar[] = $i;
                    continue;
                }
                $vienen[] = (int) $id;
                $actual = $existentes[$id];
                if (! $puede($actual['id_asignatura'])) {
                    $igual = $destino === (int) $actual['id_asignatura']
                        && (int) $r['id_proposito'] === (int) $actual['id_proposito']
                        && ($r['id_padre'] ?? null) === ($actual['id_padre'] ?? null)
                        && trim((string) $r['descripcion']) === trim((string) $actual['descripcion']);
                    if ($igual) {
                        $ignorar[] = $i;
                        continue;
                    }

                    return ['rechazada' => (int) $actual['id_asignatura'], 'ignorar' => [], 'eliminar' => []];
                }
            }
            if (! $puede($destino)) {
                return ['rechazada' => $destino, 'ignorar' => [], 'eliminar' => []];
            }
        }

        $eliminar = array_values(array_filter(
            array_map('intval', array_keys($existentes)),
            fn ($id) => ! in_array($id, $vienen, true) && $puede($existentes[$id]['id_asignatura']),
        ));

        return ['rechazada' => null, 'ignorar' => $ignorar, 'eliminar' => $eliminar];
    }

    /**
     * Guarda los referentes del grado en el periodo: los que traen id se actualizan, los nuevos se
     * crean y los que no vienen se borran, pero solo en las asignaturas que quien guarda puede
     * editar (cambiosDeReferentes). No se quita un referente que ya tiene notas. Cada cambio queda
     * en el historial.
     *
     * @param  array<int, array{id?: int|null, id_proposito: int, id_asignatura: int, descripcion: string, subreferentes?: array<int, array{id?: int|null, descripcion: string}>}>  $referentes
     */
    public function guardarReferentes(array $actor, int $idCurso, int $idPeriodo, array $referentes): array
    {
        if ($error = $this->sinCualitativa($idCurso)) {
            return $error;
        }

        try {
            if (! $cp = self::cursoYPeriodo($idCurso, $idPeriodo)) {
                return ['error' => true, 'message' => 'Curso o periodo no encontrado'];
            }
            [$curso, $periodo] = $cp;
            $idGrado = $curso->id_grado;
            $plan = $this->buscarPlan($idGrado, $idPeriodo);
            $rol = $this->rol($actor, $curso);
            if (! self::acciones($plan->estado, $rol, self::periodoAbiertoParaReferentes($periodo))['editar']) {
                return ['error' => true, 'message' => $plan->estado === ReferentePlan::APROBADO
                    ? 'Los referentes están aprobados. Para cambiarlos, quien aprueba debe reabrirlos.'
                    : 'No puedes editar estos referentes en su estado actual o ya pasó el plazo del periodo.'];
            }

            $planos = self::aplanar($referentes);
            $ids = array_filter(array_column($planos, 'id'));
            if (count($ids) !== count(array_unique($ids))) {
                return ['error' => true, 'message' => 'Un referente viene repetido en la lista.'];
            }

            $existentes = Referente::where('id_grado', $idGrado)->where('id_periodo_academico', $idPeriodo)->get()->keyBy('id');
            $delGrado = $this->asignaturasDelGrado($idGrado);
            $usadas = $existentes->pluck('id_asignatura')->map(fn ($id) => (int) $id)->unique();
            // Un propósito desactivado o una asignatura sin carga valen solo si el referente ya los tenía.
            $propositos = Proposito::where('activo', 1)->orWhereIn('id', $existentes->pluck('id_proposito'))->pluck('nombre', 'id')->all();
            $nombresAsignatura = $delGrado->all() + DB::table('academico_asignatura')->whereIn('id', $usadas)->pluck('nombre', 'id')->all();
            foreach ($planos as $r) {
                if (! isset($propositos[$r['id_proposito']])) {
                    return ['error' => true, 'message' => 'Cada referente debe pertenecer a un propósito activo.'];
                }
                if (! isset($nombresAsignatura[$r['id_asignatura']])) {
                    return ['error' => true, 'message' => 'Cada referente debe pertenecer a una asignatura con carga en este grado.'];
                }
            }

            $cambios = self::cambiosDeReferentes(
                $existentes->map(fn (Referente $r) => [
                    'id_proposito' => (int) $r->id_proposito, 'id_asignatura' => (int) $r->id_asignatura,
                    'id_padre' => $r->id_padre, 'descripcion' => $r->descripcion,
                ])->all(),
                $planos,
                self::asignaturasEditables($rol, $delGrado, $usadas)->all(),
            );
            if ($cambios['rechazada'] !== null) {
                $nombre = $nombresAsignatura[$cambios['rechazada']] ?? 'esa asignatura';

                return ['error' => true, 'message' => "No puedes editar los referentes de «{$nombre}»: solo los de las asignaturas en las que tienes carga en este grado."];
            }

            // Quitar un referente con notas las perdería: se rechaza todo el guardado.
            $conNotas = ValoracionReferente::whereIn('id_referente', $cambios['eliminar'])
                ->selectRaw('id_referente, count(*) as n')->groupBy('id_referente')->pluck('n', 'id_referente');
            if ($conNotas->isNotEmpty()) {
                $idReferente = (int) $conNotas->keys()->first();
                $n = (int) $conNotas->first();

                return ['error' => true, 'message' => "No se puede quitar «{$existentes->get($idReferente)?->descripcion}»: ya tiene {$n} "
                    . ($n === 1 ? 'nota registrada' : 'notas registradas') . ' y se perderían. Edita su texto en lugar de quitarlo, o quita primero esas notas en la planilla.'];
            }

            DB::transaction(function () use ($actor, $plan, $idGrado, $idPeriodo, $planos, $existentes, $cambios, $propositos, $nombresAsignatura) {
                $plan->save();
                $idDe = []; // índice en $planos → id guardado (para colgar los subreferentes nuevos)

                foreach ($planos as $i => $r) {
                    if (in_array($i, $cambios['ignorar'], true)) {
                        $idDe[$i] = $r['id'];
                        continue;
                    }
                    $idPadre = $r['padre'] === null ? null : $idDe[$r['padre']];
                    $donde = ' (' . $propositos[$r['id_proposito']] . ' · ' . $nombresAsignatura[$r['id_asignatura']] . ')';
                    $texto = ($idPadre ? '↳ ' : '') . $r['descripcion'];
                    $referente = $r['id'] !== null ? $existentes->get($r['id']) : null;
                    if (! $referente) {
                        $referente = new Referente(['id_grado' => $idGrado, 'id_periodo_academico' => $idPeriodo, 'id_docente' => $actor['id']]);
                        $this->registrar($plan, $actor['id'], 'REFERENTE_CREADO', $texto . $donde);
                    } elseif ($referente->descripcion !== $r['descripcion'] || $referente->id_proposito !== $r['id_proposito']
                        || $referente->id_asignatura !== $r['id_asignatura'] || $referente->id_padre !== $idPadre) {
                        $this->registrar($plan, $actor['id'], 'REFERENTE_EDITADO', "«{$referente->descripcion}» → «{$r['descripcion']}»{$donde}");
                    }
                    $referente->fill([
                        'descripcion' => $r['descripcion'], 'id_proposito' => $r['id_proposito'],
                        'id_asignatura' => $r['id_asignatura'], 'id_padre' => $idPadre, 'orden' => $i,
                    ])->save();
                    $idDe[$i] = $referente->id;
                }

                foreach ($cambios['eliminar'] as $id) {
                    $this->registrar($plan, $actor['id'], 'REFERENTE_ELIMINADO', $existentes->get($id)?->descripcion);
                }
                Referente::whereIn('id', $cambios['eliminar'])->delete();
            });

            return ['error' => false, 'message' => 'Referentes guardados', 'data' => $this->referentesDe($idGrado, $idPeriodo)];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Cambia el estado del plan: enviar (a revisión), aprobar, devolver (con comentario) o
     * reabrir (un aprobado vuelve a BORRADOR; las notas ya puestas se conservan).
     */
    public function cambiarEstado(array $actor, int $idCurso, int $idPeriodo, string $accion, ?string $comentario): array
    {
        if ($error = $this->sinCualitativa($idCurso)) {
            return $error;
        }

        try {
            if (! $cp = self::cursoYPeriodo($idCurso, $idPeriodo)) {
                return ['error' => true, 'message' => 'Curso o periodo no encontrado'];
            }
            [$curso, $periodo] = $cp;
            $plan = $this->buscarPlan($curso->id_grado, $idPeriodo);
            if (! (self::acciones($plan->estado, $this->rol($actor, $curso), self::periodoAbiertoParaReferentes($periodo))[$accion] ?? false)) {
                return ['error' => true, 'message' => 'Esta acción no está disponible para ti en el estado actual de los referentes.'];
            }
            $comentario = trim((string) $comentario) ?: null;
            if ($accion === 'devolver' && $comentario === null) {
                return ['error' => true, 'message' => 'Indica qué hay que corregir al devolver los referentes.'];
            }
            if (in_array($accion, ['enviar', 'aprobar'], true)) {
                $referentes = Referente::where('id_grado', $curso->id_grado)->where('id_periodo_academico', $idPeriodo)->get(['id_proposito', 'id_asignatura']);
                if ($accion === 'enviar' && $referentes->isEmpty()) {
                    return ['error' => true, 'message' => 'Define al menos un referente antes de enviarlos a revisión.'];
                }
                // Un referente con un propósito desactivado o una asignatura sin carga en el grado no se califica.
                $activos = Proposito::where('activo', 1)->pluck('id');
                $delGrado = $this->asignaturasDelGrado($curso->id_grado)->keys()->map(fn ($id) => (int) $id);
                if ($referentes->contains(fn ($r) => ! $activos->contains($r->id_proposito) || ! $delGrado->contains($r->id_asignatura))) {
                    return ['error' => true, 'message' => 'Hay referentes por reubicar (su propósito está desactivado o su asignatura ya no tiene carga en el grado): cámbialos antes de continuar.'];
                }
            }

            [$estado, $registro, $mensaje] = match ($accion) {
                'enviar' => [ReferentePlan::EN_REVISION, 'ENVIADO', 'Referentes enviados a revisión'],
                'aprobar' => [ReferentePlan::APROBADO, 'APROBADO', 'Referentes aprobados: ya se puede calificar'],
                'devolver' => [ReferentePlan::DEVUELTO, 'DEVUELTO', 'Referentes devueltos para corregir'],
                'reabrir' => [ReferentePlan::BORRADOR, 'REABIERTO', 'Referentes reabiertos: no se califica hasta aprobarlos de nuevo'],
            };

            DB::transaction(function () use ($plan, $estado, $actor, $registro, $comentario) {
                $plan->estado = $estado;
                $plan->save();
                $this->registrar($plan, $actor['id'], $registro, $comentario);
            });

            return ['error' => false, 'message' => $mensaje];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Grados cualitativos del nivel de quien gestiona (o todos, con niveles null), con sus cursos,
     * directores de grupo, cantidad de referentes (sin contar subreferentes) y estado del plan en el
     * periodo. Para la página de Referentes (153/154).
     */
    public function gradosDelNivel(array $actor, ?int $idPeriodo): array
    {
        try {
            $periodos = $this->periodosDelAnio();
            $idPeriodo ??= ($periodos->firstWhere('editable', true) ?? $periodos->last())['id'] ?? null;

            $cursos = self::cursosCualitativos(fn ($q) => $q->when($actor['niveles'] !== null, fn ($q) => $q->whereIn('id_nivel', $actor['niveles'] ?: [0])));
            $estados = $idPeriodo ? ReferentePlan::where('id_periodo_academico', $idPeriodo)->pluck('estado', 'id_grado') : collect();
            $cantidad = $idPeriodo
                ? Referente::where('id_periodo_academico', $idPeriodo)->whereNull('id_padre')->selectRaw('id_grado, count(*) as n')->groupBy('id_grado')->pluck('n', 'id_grado')
                : collect();

            // Una fila por grado, en orden académico. id_curso es el primero del grado: con él se abre su plan.
            $data = $cursos->groupBy('id_grado')->map(fn (Collection $cs, $idGrado) => [
                'id_grado' => (int) $idGrado,
                'id_curso' => $cs->first()->id,
                'grado' => $cs->first()->grado?->nombre,
                'nivel' => $cs->first()->nivel?->nombre,
                // Para el buscador y el detalle.
                'cursos' => $cs->pluck('nombre')->values(),
                'directores' => $cs->map(fn ($c) => self::nombreDe($c->director))->filter()->unique()->values(),
                'referentes' => (int) ($cantidad[$idGrado] ?? 0),
                'estado' => $estados[$idGrado] ?? ReferentePlan::BORRADOR,
            ])->values();

            return ['error' => false, 'message' => 'Grados del nivel', 'data' => [
                'grados' => $data,
                'periodos' => $periodos,
                'id_periodo' => $idPeriodo,
                // Para que la página explique bien una lista vacía: todos los niveles (99 / Super
                // Admin), los del usuario, o ninguno (usuario sin nivel).
                'alcance' => $actor['niveles'] === null ? 'todos' : ($actor['niveles'] ? 'nivel' : 'sin_nivel'),
            ]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
