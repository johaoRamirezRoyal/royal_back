<?php

namespace App\Services\GestionAcademica;

use App\Models\AnioEscolar\PeriodoAcademico;
use App\Models\Areas\Cursos;
use App\Models\GestionAcademica\Boletin;
use App\Models\GestionAcademica\CierrePeriodo;
use App\Models\GestionAcademica\EscalaValoracion;
use App\Models\GestionAcademica\ObservacionGeneral;
use App\Models\GestionAcademica\Proposito;
use App\Models\GestionAcademica\Referente;
use App\Models\GestionAcademica\ReferentePlan;
use App\Models\GestionAcademica\ReferentePlanHistorial;
use App\Models\GestionAcademica\ValoracionReferente;
use App\Models\Usuarios\NivelAcademico;
use App\Models\Usuarios\Usuario;
use App\Services\AnioEscolar\AnioEscolarServices;
use App\Services\Cursos\CursosServices;
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

    // ─── Calificación ───────────────────────────────────────────
    //
    // El docente califica, en los cursos donde tiene carga, los referentes y subreferentes de esa
    // asignatura (planilla). En un referente, el coordinador (154 de su nivel) puede ajustar el
    // desempeño final. El director de grupo ve todo su curso y escribe las observaciones
    // generales. Se califica en plazo y con el plan APROBADO; el coordinador ajusta hasta cerrar.
    // Cerrado el curso en el periodo, nada de eso cambia.

    /** Escala activa del nivel del curso; si el nivel no tiene desempeños propios, la general (sin niveles). */
    private function escalaDelNivel(?int $idNivel): Collection
    {
        $propia = $idNivel
            ? EscalaValoracion::where('activo', 1)->whereHas('niveles', fn ($q) => $q->where('nivel_academico.id', $idNivel))->orderBy('orden')->get()
            : collect();

        return $propia->isNotEmpty()
            ? $propia
            : EscalaValoracion::where('activo', 1)->doesntHave('niveles')->orderBy('orden')->get();
    }

    /** ¿Es hoy el director de grupo de ese curso activo? */
    public function dirige(int $idUser, int $idCurso): bool
    {
        return Cursos::whereKey($idCurso)->where('activo', 1)->where('id_director', $idUser)->exists();
    }

    /** ¿Aprueba referentes (154) en el nivel del curso? Ajusta finales y cierra el periodo. */
    private static function apruebaEnNivel(array $actor, Cursos $curso): bool
    {
        return $actor['aprueba'] && ($actor['niveles'] === null || in_array($curso->id_nivel, $actor['niveles'], true));
    }

    /** ¿Gestiona o aprueba referentes (153/154) en el nivel del curso? */
    public function gestionaCurso(array $actor, int $idCurso): bool
    {
        $curso = Cursos::find($idCurso, ['id', 'id_nivel']);

        return $curso && ($actor['gestiona'] || $actor['aprueba'])
            && ($actor['niveles'] === null || in_array($curso->id_nivel, $actor['niveles'], true));
    }

    private static function cierreDe(int $idCurso, int $idPeriodo): ?CierrePeriodo
    {
        return CierrePeriodo::with('usuario:id_user,nombre,apellido')->where('id_curso', $idCurso)->where('id_periodo_academico', $idPeriodo)->first();
    }

    /**
     * Estudiantes activos que hoy están en el curso, más los que ya tienen nota u observación en él
     * en ese periodo aunque se hayan movido (en_curso = false), por apellido.
     */
    private function estudiantesDe(int $idCurso, int $idPeriodo): Collection
    {
        $conAlgo = ValoracionReferente::where('id_curso', $idCurso)->where('id_periodo_academico', $idPeriodo)->pluck('id_estudiante')
            ->merge(ObservacionGeneral::where('id_curso', $idCurso)->where('id_periodo_academico', $idPeriodo)->pluck('id_estudiante'))
            ->unique();

        return Usuario::where('perfil', CursosServices::PERFIL_ESTUDIANTE)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('id_curso', $idCurso)->where('estado', 'activo'))
                ->orWhereIn('id_user', $conAlgo))
            ->orderBy('apellido')->orderBy('nombre')
            ->get(['id_user', 'nombre', 'apellido', 'documento', 'foto_carnet', 'id_curso', 'estado'])
            ->map(fn ($u) => [
                'id_user' => $u->id_user,
                'nombre' => $u->nombre,
                'apellido' => $u->apellido,
                'documento' => $u->documento,
                'foto_carnet' => $u->foto_carnet,
                'en_curso' => (int) $u->id_curso === $idCurso && $u->estado === 'activo',
            ]);
    }

    /** id_estudiante → id_referente → { docente, ajuste, final, ajustado_por?, ajustado_at? } (final = ajuste ?? docente). */
    private static function notasDe(Collection $idsEstudiante, Collection $idsReferente, bool $conAjustador = false): Collection
    {
        $notas = ValoracionReferente::whereIn('id_estudiante', $idsEstudiante)->whereIn('id_referente', $idsReferente)->get();
        $ajustadores = $conAjustador
            ? Usuario::whereIn('id_user', $notas->pluck('id_ajustado_por')->filter()->unique())->get(['id_user', 'nombre', 'apellido'])->keyBy('id_user')
            : collect();

        return $notas->groupBy('id_estudiante')->map(fn (Collection $suyas) => $suyas->mapWithKeys(fn (ValoracionReferente $v) => [
            $v->id_referente => [
                'docente' => $v->id_escala_docente,
                'ajuste' => $v->id_escala_ajuste,
                'final' => $v->id_escala_ajuste ?? $v->id_escala_docente,
            ] + ($conAjustador ? [
                'ajustado_por' => $v->id_ajustado_por ? self::nombreDe($ajustadores->get($v->id_ajustado_por)) : null,
                'ajustado_at' => $v->ajustado_at,
            ] : []),
        ]));
    }

    /** Ids de los referentes y subreferentes de una lista de referentesDe(). */
    private static function idsDe(Collection $referentes): Collection
    {
        return $referentes->flatMap(fn ($r) => collect($r['subreferentes'])->pluck('id')->prepend($r['id']));
    }

    /** Propósitos de esos referentes, en el orden del catálogo. */
    private static function propositosDe(Collection $referentes): Collection
    {
        return Proposito::whereIn('id', $referentes->pluck('id_proposito')->unique())->orderBy('orden')->orderBy('nombre')->get(['id', 'nombre', 'descripcion']);
    }

    /**
     * Cursos cualitativos del docente y los periodos del año: los que dirige (`es_director`) y
     * aquellos en que tiene carga activa, con las asignaturas que califica en cada uno.
     */
    public function misCursos(int $idDocente): array
    {
        try {
            $carga = DB::table('academico_carga_academica as c')
                ->join('academico_docente_asignatura as da', 'da.id', '=', 'c.id_docente_asignatura')
                ->join('academico_asignatura as a', 'a.id', '=', 'da.id_asignatura')
                ->where('c.activo', 1)->where('da.activo', 1)->where('da.id_docente', $idDocente)
                ->distinct()->orderBy('a.nombre')->get(['c.id_curso', 'a.id', 'a.nombre'])
                ->groupBy('id_curso');

            $cursos = self::cursosCualitativos(fn ($q) => $q->where(fn ($q) => $q->where('id_director', $idDocente)->orWhereIn('id', $carga->keys())))
                ->map(fn (Cursos $c) => [
                    'id_curso' => $c->id,
                    'curso' => $c->nombre,
                    'grado' => $c->grado?->nombre,
                    'es_director' => (int) $c->id_director === $idDocente,
                    'asignaturas' => ($carga[$c->id] ?? collect())->map(fn ($a) => ['id' => (int) $a->id, 'nombre' => $a->nombre])->values(),
                ])->values();

            return ['error' => false, 'message' => 'Tus cursos', 'data' => ['cursos' => $cursos, 'periodos' => $this->periodosDelAnio()]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Cursos cualitativos (todos, o los de los niveles dados) y periodos del año, para elegir un curso. */
    public function filtrosCursos(?array $niveles): array
    {
        try {
            $cursos = self::cursosCualitativos(fn ($q) => $q->when($niveles !== null, fn ($q) => $q->whereIn('id_nivel', $niveles ?: [0])))
                ->map(fn (Cursos $c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'grado' => $c->grado?->nombre,
                    'nivel' => $c->nivel ? ['id' => $c->nivel->id, 'nombre' => $c->nivel->nombre] : null,
                ])->values();

            return ['error' => false, 'message' => 'Cursos', 'data' => ['cursos' => $cursos, 'periodos' => $this->periodosDelAnio()]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Planilla del docente en un curso y una asignatura: los referentes (con subreferentes) de esa
     * asignatura y las notas de cada estudiante. `editable`: en plazo, plan aprobado y sin cierre.
     */
    public function planilla(int $idDocente, int $idCurso, int $idPeriodo, int $idAsignatura): array
    {
        if ($error = $this->sinCualitativa($idCurso)) {
            return $error;
        }

        try {
            if (! $cp = self::cursoYPeriodo($idCurso, $idPeriodo)) {
                return ['error' => true, 'message' => 'Curso o periodo no encontrado'];
            }
            [$curso, $periodo] = $cp;
            if (! $this->asignaturasConCarga($idDocente, $idCurso)->contains($idAsignatura)) {
                return ['error' => true, 'message' => 'No tienes carga en esa asignatura en este curso.'];
            }

            $estado = $this->buscarPlan($curso->id_grado, $idPeriodo)->estado;
            $cerrado = self::cierreDe($idCurso, $idPeriodo) !== null;
            $referentes = $this->referentesDe($curso->id_grado, $idPeriodo)->where('id_asignatura', $idAsignatura)->values();
            $estudiantes = $this->estudiantesDe($idCurso, $idPeriodo);
            $notas = self::notasDe($estudiantes->pluck('id_user'), self::idsDe($referentes));

            return ['error' => false, 'message' => 'Planilla', 'data' => [
                'curso' => ['id' => $curso->id, 'nombre' => $curso->nombre],
                'grado' => $curso->grado?->nombre,
                'director' => self::nombreDe($curso->director),
                'asignatura' => ['id' => $idAsignatura, 'nombre' => DB::table('academico_asignatura')->where('id', $idAsignatura)->value('nombre')],
                'periodo' => ['id' => $periodo->id, 'nombre' => $periodo->nombre, 'fecha_limite_notas' => $periodo->fecha_limite_notas ?? $periodo->fecha_fin],
                'periodo_editable' => self::periodoEditable($periodo),
                'estado_plan' => $estado,
                'cerrado' => $cerrado,
                'editable' => self::periodoEditable($periodo) && $estado === ReferentePlan::APROBADO && ! $cerrado,
                'escala' => $this->escalaDelNivel($curso->id_nivel)->map->only(['id', 'nombre', 'abreviatura', 'descripcion', 'color'])->values(),
                'propositos' => self::propositosDe($referentes),
                'referentes' => $referentes,
                'estudiantes' => $estudiantes->map(fn ($e) => $e + ['notas' => $notas->get($e['id_user'], collect())])->values(),
            ]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Error si el curso ya se cerró en el periodo, o null. */
    private static function errorSiCerrado(int $idCurso, int $idPeriodo): ?array
    {
        return self::cierreDe($idCurso, $idPeriodo)
            ? ['error' => true, 'message' => 'El coordinador ya cerró este periodo en este curso: las notas no se pueden cambiar.']
            : null;
    }

    private function validarVentana(int $idPeriodo): ?array
    {
        $periodo = PeriodoAcademico::find($idPeriodo);
        if (! $periodo) {
            return ['error' => true, 'message' => 'Periodo no encontrado'];
        }
        if (! self::periodoEditable($periodo)) {
            return ['error' => true, 'message' => 'El periodo está cerrado para notas: ya pasó su fecha límite o aún no empieza.'];
        }

        return null;
    }

    /**
     * Guarda las notas del docente en su asignatura. Por estudiante, una lista de { id_referente,
     * id_escala } (referentes o subreferentes de esa asignatura); id_escala null quita la nota del
     * docente (si el coordinador la ajustó, el ajuste se conserva). Lo que no viene no se toca.
     *
     * @param  array<int, array{id_estudiante: int, notas: array<int, array{id_referente: int, id_escala: ?int}>}>  $estudiantes
     */
    public function guardarPlanilla(int $idDocente, int $idCurso, int $idPeriodo, int $idAsignatura, array $estudiantes): array
    {
        if ($error = $this->sinCualitativa($idCurso) ?? $this->validarVentana($idPeriodo) ?? self::errorSiCerrado($idCurso, $idPeriodo)) {
            return $error;
        }
        $curso = Cursos::find($idCurso, ['id', 'id_nivel', 'id_grado']);
        if (! $this->asignaturasConCarga($idDocente, $idCurso)->contains($idAsignatura)) {
            return ['error' => true, 'message' => 'No tienes carga en esa asignatura en este curso.'];
        }
        if ($this->buscarPlan($curso->id_grado, $idPeriodo)->estado !== ReferentePlan::APROBADO) {
            return ['error' => true, 'message' => 'Los referentes de este periodo aún no están aprobados: no se puede calificar todavía.'];
        }

        try {
            $escalaValida = $this->escalaDelNivel($curso->id_nivel)->pluck('id')->flip();
            $referentes = Referente::where('id_grado', $curso->id_grado)->where('id_periodo_academico', $idPeriodo)
                ->where('id_asignatura', $idAsignatura)->pluck('id')->flip();
            $alumnos = $this->estudiantesDe($idCurso, $idPeriodo)->pluck('id_user')->flip();

            foreach ($estudiantes as $e) {
                if (! $alumnos->has($e['id_estudiante'])) {
                    return ['error' => true, 'message' => 'Uno de los estudiantes no pertenece a este curso'];
                }
                foreach ($e['notas'] as $n) {
                    if (! $referentes->has($n['id_referente'])) {
                        return ['error' => true, 'message' => 'Un referente no corresponde a esta asignatura, grado y periodo'];
                    }
                    if (($n['id_escala'] ?? null) !== null && ! $escalaValida->has($n['id_escala'])) {
                        return ['error' => true, 'message' => 'Un desempeño no corresponde a la escala de este nivel'];
                    }
                }
            }

            DB::transaction(function () use ($idDocente, $idCurso, $idPeriodo, $estudiantes) {
                foreach ($estudiantes as $e) {
                    foreach ($e['notas'] as $n) {
                        $v = ValoracionReferente::firstOrNew(['id_estudiante' => $e['id_estudiante'], 'id_referente' => $n['id_referente']]);
                        $idEscala = $n['id_escala'] ?? null;
                        if ($idEscala === null && ! $v->exists) {
                            continue;
                        }
                        if ($idEscala === null && $v->id_escala_ajuste === null) {
                            $v->delete();
                            continue;
                        }
                        $v->fill([
                            'id_periodo_academico' => $idPeriodo, 'id_curso' => $idCurso,
                            'id_escala_docente' => $idEscala, 'id_docente' => $idDocente,
                        ])->save();
                    }
                }
            });

            return ['error' => false, 'message' => 'Notas guardadas'];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Ajuste del coordinador (154 del nivel) al desempeño final de un referente (no de un
     * subreferente) de un estudiante; id_escala null quita el ajuste y vuelve a valer el del
     * docente. Con el plan aprobado y hasta cerrar el curso en el periodo (sin mirar el plazo).
     */
    public function ajustar(array $actor, int $idCurso, int $idPeriodo, int $idEstudiante, int $idReferente, ?int $idEscala): array
    {
        if ($error = $this->sinCualitativa($idCurso) ?? self::errorSiCerrado($idCurso, $idPeriodo)) {
            return $error;
        }

        try {
            if (! $cp = self::cursoYPeriodo($idCurso, $idPeriodo)) {
                return ['error' => true, 'message' => 'Curso o periodo no encontrado'];
            }
            [$curso] = $cp;
            if (! self::apruebaEnNivel($actor, $curso)) {
                return ['error' => true, 'message' => 'Solo quien aprueba los referentes del nivel ajusta el desempeño final.'];
            }
            if ($this->buscarPlan($curso->id_grado, $idPeriodo)->estado !== ReferentePlan::APROBADO) {
                return ['error' => true, 'message' => 'Los referentes de este periodo aún no están aprobados.'];
            }
            $referente = Referente::whereKey($idReferente)->where('id_grado', $curso->id_grado)->where('id_periodo_academico', $idPeriodo)->first();
            if (! $referente) {
                return ['error' => true, 'message' => 'El referente no corresponde al grado y periodo de este curso'];
            }
            if ($referente->id_padre !== null) {
                return ['error' => true, 'message' => 'Solo se ajusta el desempeño final de un referente, no de un subreferente.'];
            }
            if (! $this->estudiantesDe($idCurso, $idPeriodo)->contains('id_user', $idEstudiante)) {
                return ['error' => true, 'message' => 'El estudiante no pertenece a este curso'];
            }
            if ($idEscala !== null && ! $this->escalaDelNivel($curso->id_nivel)->contains('id', $idEscala)) {
                return ['error' => true, 'message' => 'El desempeño no corresponde a la escala de este nivel'];
            }

            $v = ValoracionReferente::firstOrNew(['id_estudiante' => $idEstudiante, 'id_referente' => $idReferente]);
            if ($idEscala === null) {
                if ($v->exists) {
                    $v->id_escala_docente === null
                        ? $v->delete()
                        : $v->fill(['id_escala_ajuste' => null, 'id_ajustado_por' => null, 'ajustado_at' => null])->save();
                }

                return ['error' => false, 'message' => 'Ajuste quitado: vale el desempeño del docente'];
            }
            $v->fill([
                'id_periodo_academico' => $idPeriodo, 'id_curso' => $idCurso,
                'id_escala_ajuste' => $idEscala, 'id_ajustado_por' => $actor['id'], 'ajustado_at' => now(),
            ])->save();

            return ['error' => false, 'message' => 'Desempeño final ajustado'];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Lo de un curso en un periodo, para la vista del director de grupo, el coordinador y la
     * consulta: todos los referentes (con subreferentes), las notas de cada estudiante (con el
     * ajuste y quién lo hizo) y las observaciones generales.
     */
    private function datosCurso(Cursos $curso, PeriodoAcademico $periodo): array
    {
        $referentes = $this->referentesDe($curso->id_grado, $periodo->id);
        $estudiantes = $this->estudiantesDe($curso->id, $periodo->id);
        $notas = self::notasDe($estudiantes->pluck('id_user'), self::idsDe($referentes), true);
        $observaciones = ObservacionGeneral::whereIn('id_estudiante', $estudiantes->pluck('id_user'))
            ->where('id_periodo_academico', $periodo->id)->pluck('observacion', 'id_estudiante');

        return [
            'propositos' => self::propositosDe($referentes),
            'asignaturas' => DB::table('academico_asignatura')->whereIn('id', $referentes->pluck('id_asignatura')->unique())
                ->orderBy('nombre')->get(['id', 'nombre']),
            'referentes' => $referentes,
            'estudiantes' => $estudiantes->map(fn ($e) => $e + [
                'notas' => $notas->get($e['id_user'], collect()),
                'observacion' => $observaciones[$e['id_user']] ?? null,
            ])->values(),
        ];
    }

    /**
     * Vista de un curso en un periodo (director de grupo, 153/154 del nivel, 99, 152: el
     * controller decide el acceso). `puede`: observar (director, en plazo y sin cierre), ajustar y
     * cerrar (154 del nivel, plan aprobado y sin cierre), reabrir_cierre (154 del nivel, cerrado).
     */
    public function vistaCurso(array $actor, int $idCurso, int $idPeriodo): array
    {
        if ($error = $this->sinCualitativa($idCurso)) {
            return $error;
        }

        try {
            if (! $cp = self::cursoYPeriodo($idCurso, $idPeriodo)) {
                return ['error' => true, 'message' => 'Curso o periodo no encontrado'];
            }
            [$curso, $periodo] = $cp;
            $estado = $this->buscarPlan($curso->id_grado, $idPeriodo)->estado;
            $cierre = self::cierreDe($idCurso, $idPeriodo);
            $aprueba = self::apruebaEnNivel($actor, $curso);
            $esDirector = $curso->id_director !== null && (int) $curso->id_director === $actor['id'];

            return ['error' => false, 'message' => 'Notas del curso', 'data' => [
                'curso' => ['id' => $curso->id, 'nombre' => $curso->nombre],
                'grado' => $curso->grado?->nombre,
                'director' => self::nombreDe($curso->director),
                'periodo' => ['id' => $periodo->id, 'nombre' => $periodo->nombre, 'fecha_limite_notas' => $periodo->fecha_limite_notas ?? $periodo->fecha_fin],
                'periodo_editable' => self::periodoEditable($periodo),
                'estado_plan' => $estado,
                'cierre' => $cierre ? ['fecha' => $cierre->fecha, 'usuario' => self::nombreDe($cierre->usuario)] : null,
                'es_director' => $esDirector,
                'puede' => [
                    'observar' => $esDirector && self::periodoEditable($periodo) && ! $cierre,
                    'ajustar' => $aprueba && $estado === ReferentePlan::APROBADO && ! $cierre,
                    'cerrar' => $aprueba && $estado === ReferentePlan::APROBADO && ! $cierre,
                    'reabrir_cierre' => $aprueba && $cierre !== null,
                ],
                // Toda la escala (también inactiva): una nota vieja puede usar un desempeño que ya se desactivó.
                'escala' => EscalaValoracion::orderBy('orden')->get(['id', 'nombre', 'abreviatura', 'descripcion', 'color', 'activo']),
            ] + $this->datosCurso($curso, $periodo)];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * "Observaciones Generales del Director de Grupo": solo el director del curso, en plazo y sin
     * cierre. Una observación vacía se borra; los estudiantes que no vienen no se tocan.
     *
     * @param  array<int, array{id_estudiante: int, observacion: ?string}>  $observaciones
     */
    public function guardarObservaciones(int $idDocente, int $idCurso, int $idPeriodo, array $observaciones): array
    {
        if ($error = $this->sinCualitativa($idCurso) ?? $this->validarVentana($idPeriodo) ?? self::errorSiCerrado($idCurso, $idPeriodo)) {
            return $error;
        }
        if (! $this->dirige($idDocente, $idCurso)) {
            return ['error' => true, 'message' => 'Solo el director de grupo del curso escribe las observaciones generales.'];
        }

        try {
            $alumnos = $this->estudiantesDe($idCurso, $idPeriodo)->pluck('id_user')->flip();
            foreach ($observaciones as $o) {
                if (! $alumnos->has($o['id_estudiante'])) {
                    return ['error' => true, 'message' => 'Uno de los estudiantes no pertenece a este curso'];
                }
            }

            DB::transaction(function () use ($idDocente, $idCurso, $idPeriodo, $observaciones) {
                foreach ($observaciones as $o) {
                    $clave = ['id_estudiante' => $o['id_estudiante'], 'id_periodo_academico' => $idPeriodo];
                    $texto = trim((string) ($o['observacion'] ?? ''));
                    $texto === ''
                        ? ObservacionGeneral::where($clave)->delete()
                        : ObservacionGeneral::updateOrCreate($clave, ['id_curso' => $idCurso, 'observacion' => $texto, 'id_docente' => $idDocente]);
                }
            });

            return ['error' => false, 'message' => 'Observaciones guardadas'];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Foto del boletín de un estudiante: solo referentes (sin subreferentes) con su desempeño final,
     * agrupados por propósito, y la observación general. Es lo que guarda academico_boletin.datos y
     * de donde sale el PDF.
     *
     * @param  array  $base  curso, grado, periodo, director y fecha del cierre
     * @param  array  $datos  de datosCurso()
     * @param  array  $estudiante  uno de $datos['estudiantes']
     */
    public static function fotoBoletin(array $base, array $datos, array $estudiante, Collection $escala): array
    {
        $notas = collect($estudiante['notas']);
        $asignaturas = collect($datos['asignaturas'])->pluck('nombre', 'id');
        $desempeno = function ($idEscala) use ($escala) {
            $e = $idEscala ? $escala->get($idEscala) : null;

            return $e ? ['nombre' => $e->nombre, 'abreviatura' => $e->abreviatura, 'color' => $e->color] : null;
        };

        return $base + [
            'estudiante' => collect($estudiante)->only(['id_user', 'nombre', 'apellido', 'documento'])->all(),
            'propositos' => collect($datos['propositos'])->map(fn ($p) => [
                'nombre' => $p['nombre'],
                'descripcion' => $p['descripcion'],
                'referentes' => collect($datos['referentes'])->where('id_proposito', $p['id'])->map(fn ($r) => [
                    'descripcion' => $r['descripcion'],
                    'asignatura' => $asignaturas[$r['id_asignatura']] ?? null,
                    'desempeno' => $desempeno($notas->get($r['id'])['final'] ?? null),
                ])->values()->all(),
            ])->filter(fn ($p) => $p['referentes'])->values()->all(),
            'observacion_general' => $estudiante['observacion'],
        ];
    }

    /**
     * Cierra el curso en el periodo (154 del nivel, con el plan aprobado): guarda la foto del
     * boletín de cada estudiante. Desde ahí no se califica, ajusta ni observa. Avisa cuántos
     * referentes quedaron sin desempeño, pero no lo impide.
     */
    public function cerrar(array $actor, int $idCurso, int $idPeriodo): array
    {
        if ($error = $this->sinCualitativa($idCurso) ?? self::errorSiCerrado($idCurso, $idPeriodo)) {
            return $error;
        }

        try {
            if (! $cp = self::cursoYPeriodo($idCurso, $idPeriodo)) {
                return ['error' => true, 'message' => 'Curso o periodo no encontrado'];
            }
            [$curso, $periodo] = $cp;
            if (! self::apruebaEnNivel($actor, $curso)) {
                return ['error' => true, 'message' => 'Solo quien aprueba los referentes del nivel cierra el periodo.'];
            }
            if ($this->buscarPlan($curso->id_grado, $idPeriodo)->estado !== ReferentePlan::APROBADO) {
                return ['error' => true, 'message' => 'Los referentes de este periodo aún no están aprobados: no hay nada que cerrar.'];
            }

            $datos = $this->datosCurso($curso, $periodo);
            $datos['propositos'] = $datos['propositos']->toArray();
            $datos['asignaturas'] = $datos['asignaturas']->map(fn ($a) => (array) $a)->all();
            $escala = EscalaValoracion::all()->keyBy('id');
            // ponytail: un estudiante que se cambió de curso a mitad de periodo puede salir en los
            // dos cursos; su boletín es el del primero que se cierre (unique estudiante + periodo).
            $yaTienen = Boletin::where('id_periodo_academico', $idPeriodo)->pluck('id_estudiante')->flip();
            $estudiantes = $datos['estudiantes']->reject(fn ($e) => $yaTienen->has($e['id_user']));
            $principales = collect($datos['referentes'])->pluck('id');
            $sinNota = $estudiantes->sum(fn ($e) => $principales->filter(fn ($id) => ($e['notas']->get($id)['final'] ?? null) === null)->count());

            DB::transaction(function () use ($actor, $curso, $periodo, $datos, $escala, $estudiantes) {
                $cierre = CierrePeriodo::create(['id_curso' => $curso->id, 'id_periodo_academico' => $periodo->id, 'id_usuario' => $actor['id'], 'fecha' => now()]);
                $base = [
                    'curso' => $curso->nombre,
                    'grado' => $curso->grado?->nombre,
                    'periodo' => ['id' => $periodo->id, 'nombre' => $periodo->nombre],
                    'director' => self::nombreDe($curso->director),
                    'cerrado_at' => $cierre->fecha->toIso8601String(),
                ];
                foreach ($estudiantes as $e) {
                    Boletin::create([
                        'id_cierre' => $cierre->id, 'id_estudiante' => $e['id_user'], 'id_periodo_academico' => $periodo->id,
                        'id_curso' => $curso->id, 'datos' => self::fotoBoletin($base, $datos, $e, $escala),
                    ]);
                }
            });

            $mensaje = "Periodo cerrado: {$estudiantes->count()} boletines guardados";

            return ['error' => false, 'message' => $sinNota ? "{$mensaje}. Quedaron {$sinNota} referentes sin desempeño." : $mensaje];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Reabre un curso cerrado (154 del nivel): borra el cierre y sus boletines, y se vuelve a poder corregir. */
    public function reabrirCierre(array $actor, int $idCurso, int $idPeriodo): array
    {
        try {
            $curso = Cursos::find($idCurso, ['id', 'id_nivel']);
            $cierre = self::cierreDe($idCurso, $idPeriodo);
            if (! $curso || ! $cierre) {
                return ['error' => true, 'message' => 'Este curso no está cerrado en ese periodo'];
            }
            if (! self::apruebaEnNivel($actor, $curso)) {
                return ['error' => true, 'message' => 'Solo quien aprueba los referentes del nivel reabre el periodo.'];
            }
            $cierre->delete(); // los boletines caen en cascada

            return ['error' => false, 'message' => 'Periodo reabierto: los boletines se generan de nuevo al cerrar'];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
