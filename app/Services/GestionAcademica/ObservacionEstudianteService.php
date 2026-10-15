<?php

namespace App\Services\GestionAcademica;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Observaciones de estudiantes por día (POSITIVA/NEGATIVA), organizadas por periodo
 * académico (tabla periodo_academico). El "acta" de un estudiante en un periodo = sus
 * observaciones de ese periodo.
 *
 * Reglas:
 * - El periodo de una observación sale de su fecha (periodo que la contiene; si ya terminó, se rechaza como cerrado); una
 *   fecha fuera de todo periodo se rechaza.
 * - Periodo cerrado = su fecha_fin ya pasó: su acta se consulta pero no se modifica (ni
 *   crear, ni editar, ni eliminar).
 * - Una fecha futura se permite (el frontend avisa y pide confirmar antes de enviarla).
 * - Las observaciones son de quien las registra: cada profesor ve solo las suyas, salvo la
 *   opción 134 ("Ver todas las observaciones"), que ve las de todos en todos los cursos.
 * - Solo quien registró una observación la edita o elimina.
 * - Asignatura: la del registrador en el curso del estudiante; si dicta más de una ahí,
 *   tiene que especificarla; si no dicta ninguna (psicología, coordinación…), va sin ella.
 *
 * Todo lo que depende de permisos llega en AccesoObservaciones (lo arma el controller).
 */
class ObservacionEstudianteService
{
    public const TIPOS = ['POSITIVA', 'NEGATIVA'];

    private const PERFIL_ESTUDIANTE = 16;

    private const ORDENES = [
        'fecha' => 'o.fecha',
        'estudiante' => 'u.apellido',
        'curso' => 'c.nombre',
        'tipo' => 'o.tipo',
        'asignatura' => 'asig.nombre',
        'docente' => 'doc.apellido',
        'total_rango' => 'ult.total_rango',
    ];

    /** "Nombre Apellido" de un alias de `usuarios`, como columna `$como`. */
    private static function nombre(string $alias, string $como): \Illuminate\Contracts\Database\Query\Expression
    {
        return DB::raw("TRIM(CONCAT(COALESCE({$alias}.nombre, ''), ' ', COALESCE({$alias}.apellido, ''))) as {$como}");
    }

    private static function fallo(string $mensaje, int $status = 400): array
    {
        return ['error' => true, 'status' => $status, 'message' => $mensaje, 'data' => []];
    }

    /** Estudiantes activos visibles para el usuario. */
    private function estudiantes(?array $idsCursos): Builder
    {
        return DB::table('usuarios as u')
            ->leftJoin('curso as c', 'c.id', '=', 'u.id_curso')
            ->where('u.perfil', self::PERFIL_ESTUDIANTE)
            ->where('u.estado', 'activo')
            ->when($idsCursos !== null, fn ($q) => $q->whereIn('u.id_curso', $idsCursos));
    }

    /** Observaciones con sus nombres resueltos; con `$soloDocente`, solo las que él registró. */
    private function observaciones(?int $soloDocente = null): Builder
    {
        return DB::table('academico_observacion_estudiante as o')
            ->join('usuarios as u', 'u.id_user', '=', 'o.id_estudiante')
            ->leftJoin('curso as c', 'c.id', '=', 'o.id_curso')
            ->leftJoin('academico_asignatura as asig', 'asig.id', '=', 'o.id_asignatura')
            ->leftJoin('usuarios as doc', 'doc.id_user', '=', 'o.id_docente')
            ->join('periodo_academico as p', 'p.id', '=', 'o.id_periodo_academico')
            ->when($soloDocente !== null, fn ($q) => $q->where('o.id_docente', $soloDocente))
            ->select([
                'o.id',
                'o.fecha',
                'o.tipo',
                'o.motivo',
                'o.id_estudiante',
                'u.documento',
                self::nombre('u', 'estudiante'),
                'o.id_curso',
                'c.nombre as curso',
                'o.id_asignatura',
                'asig.nombre as asignatura',
                'o.id_docente',
                self::nombre('doc', 'docente'),
                'o.id_periodo_academico',
                'p.nombre as periodo',
                // Fecha de la app (America/Bogota), no CURDATE(): si MySQL estuviera en otra
                // zona, editar/eliminar cerrarían a otra hora que registrar (preparar()).
                DB::raw("p.fecha_fin < '" . Carbon::today()->toDateString() . "' as periodo_cerrado"),
                'o.created_at',
                'o.updated_at',
            ]);
    }

    /**
     * Periodos visibles en Observaciones, del más reciente al más antiguo, con `cerrado` y
     * `actual`. Ver periodosVisibles() para cuáles.
     */
    public function periodos(): array
    {
        $hoy = Carbon::today()->toDateString();

        $periodos = DB::table('periodo_academico as p')
            ->leftJoin('anio_escolar as a', 'a.id', '=', 'p.id_anio_escolar')
            ->where(fn ($q) => self::periodosVisibles($q, $hoy, 'p.'))
            ->orderByDesc('p.fecha_inicio')
            ->get(['p.id', 'p.nombre', 'p.fecha_inicio', 'p.fecha_fin', DB::raw("CONCAT(a.anio_inicio, '-', a.anio_fin) as anio_escolar")]);

        // "Actual": el que contiene hoy; en vacaciones entre periodos, el último que ya empezó.
        $actual = $periodos->first(fn ($p) => $p->fecha_inicio <= $hoy && $p->fecha_fin >= $hoy)
            ?? $periodos->first(fn ($p) => $p->fecha_inicio <= $hoy);

        return $periodos->map(fn ($p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'anio_escolar' => $p->anio_escolar,
            'fecha_inicio' => $p->fecha_inicio,
            'fecha_fin' => $p->fecha_fin,
            'cerrado' => $p->fecha_fin < $hoy,
            'actual' => $actual?->id === $p->id,
        ])->values()->all();
    }

    /**
     * Activos + los que ya terminaron aunque estén inactivos: el comando diario
     * `periodos:desactivar-vencidos` pone activo=0 a todo periodo cuya fecha_fin pasó, y sin
     * esto sus actas desaparecían al día siguiente de cerrar. Un periodo desactivado a mano
     * que aún no termina sigue oculto (probablemente es un error de configuración).
     */
    private static function periodosVisibles($q, string $hoy, string $alias = ''): void
    {
        $q->where("{$alias}activo", 1)->orWhere("{$alias}fecha_fin", '<', $hoy);
    }

    /** Periodo que contiene la fecha. Si ya terminó, preparar() lo rechaza como cerrado. */
    private function periodoDeFecha(string $fecha): ?object
    {
        $hoy = Carbon::today()->toDateString();

        return DB::table('periodo_academico')
            ->where(fn ($q) => self::periodosVisibles($q, $hoy))
            ->where('fecha_inicio', '<=', $fecha)
            ->where('fecha_fin', '>=', $fecha)
            ->first(['id', 'nombre', 'fecha_fin']);
    }

    /** Asignaturas que `$idDocente` dicta (carga activa) en `$idCurso`. */
    public function asignaturasDelDocente(int $idDocente, ?int $idCurso): array
    {
        if ($idCurso === null) {
            return [];
        }

        return DB::table('academico_carga_academica as ca')
            ->join('academico_docente_asignatura as da', 'da.id', '=', 'ca.id_docente_asignatura')
            ->join('academico_asignatura as asig', 'asig.id', '=', 'da.id_asignatura')
            ->where('ca.id_curso', $idCurso)
            ->where('ca.activo', 1)
            ->where('da.id_docente', $idDocente)
            ->distinct()
            ->orderBy('asig.nombre')
            ->get(['asig.id', 'asig.nombre'])
            ->all();
    }

    /**
     * Listado DataTable remoto: UNA fila por estudiante con observaciones en el rango filtrado
     * (semana o periodo), con su ÚLTIMA observación y cuántas tiene en ese rango
     * (total/positivas/negativas). El acta completa se abre aparte.
     * Filtros: id_periodo, fecha_inicio, fecha_fin, id_curso, tipo, id_estudiante.
     */
    public function listar(AccesoObservaciones $acceso, array $filtros, int $pagina, int $porPagina, ?string $busqueda, ?string $orden, ?string $direccion): array
    {
        try {
            $filtradas = $this->observaciones($acceso->soloDocente())
                ->when(filled($filtros['id_periodo'] ?? null), fn ($q) => $q->where('o.id_periodo_academico', $filtros['id_periodo']))
                ->when(filled($filtros['fecha_inicio'] ?? null), fn ($q) => $q->where('o.fecha', '>=', $filtros['fecha_inicio']))
                ->when(filled($filtros['fecha_fin'] ?? null), fn ($q) => $q->where('o.fecha', '<=', $filtros['fecha_fin']))
                ->when(filled($filtros['id_curso'] ?? null), fn ($q) => $q->where('o.id_curso', $filtros['id_curso']))
                ->when(in_array($filtros['tipo'] ?? null, self::TIPOS, true), fn ($q) => $q->where('o.tipo', $filtros['tipo']))
                ->when(filled($filtros['id_estudiante'] ?? null), fn ($q) => $q->where('o.id_estudiante', $filtros['id_estudiante']));

            // Por estudiante: la más reciente (rn = 1) y los conteos del rango filtrado.
            $porEstudiante = 'PARTITION BY o.id_estudiante';
            $ultimas = DB::query()
                ->fromSub((clone $filtradas)->select([
                    'o.id',
                    DB::raw("ROW_NUMBER() OVER ({$porEstudiante} ORDER BY o.fecha DESC, o.id DESC) as rn"),
                    DB::raw("COUNT(*) OVER ({$porEstudiante}) as total_rango"),
                    DB::raw("SUM(o.tipo = 'POSITIVA') OVER ({$porEstudiante}) as positivas_rango"),
                    DB::raw("SUM(o.tipo = 'NEGATIVA') OVER ({$porEstudiante}) as negativas_rango"),
                ]), 'r')
                ->where('r.rn', 1);

            $query = $filtradas
                ->joinSub($ultimas, 'ult', 'ult.id', '=', 'o.id')
                ->addSelect(['ult.total_rango', 'ult.positivas_rango', 'ult.negativas_rango'])
                ->when(filled($busqueda), function ($q) use ($busqueda) {
                    $like = '%' . $busqueda . '%';
                    $q->where(fn ($w) => $w
                        ->where('u.nombre', 'like', $like)
                        ->orWhere('u.apellido', 'like', $like)
                        ->orWhere('u.documento', 'like', $like)
                        ->orWhere('c.nombre', 'like', $like)
                        ->orWhere('o.motivo', 'like', $like));
                });

            $columnaOrden = self::ORDENES[$orden ?? ''] ?? null;
            $dir = strtolower((string) $direccion) === 'asc' ? 'asc' : 'desc';

            // Por defecto, el estudiante con la observación más reciente primero.
            $columnaOrden ? $query->orderBy($columnaOrden, $dir) : $query->orderByDesc('o.fecha');
            $query->orderByDesc('o.id');

            $paginado = $query->paginate(max(1, min($porPagina, 100)), ['*'], 'page', max(1, $pagina));

            return ['error' => false, 'message' => 'Observaciones obtenidas', 'data' => $paginado];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al consultar las observaciones: ' . $e->getMessage(), 'data' => []];
        }
    }

    /** Búsqueda de estudiantes (para abrir el acta de uno que aún no tiene observaciones). */
    public function buscarEstudiantes(AccesoObservaciones $acceso, ?string $busqueda): array
    {
        try {
            $like = '%' . trim((string) $busqueda) . '%';

            $estudiantes = $this->estudiantes($acceso->idsCursosEstudiantes())
                ->when(filled($busqueda), fn ($q) => $q->where(fn ($w) => $w
                    ->where('u.nombre', 'like', $like)
                    ->orWhere('u.apellido', 'like', $like)
                    ->orWhere('u.documento', 'like', $like)))
                ->orderBy('u.apellido')
                ->orderBy('u.nombre')
                ->limit(20)
                ->get(['u.id_user as id_estudiante', 'u.documento', self::nombre('u', 'estudiante'), 'c.nombre as curso']);

            return ['error' => false, 'message' => 'Estudiantes obtenidos', 'data' => $estudiantes];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al buscar estudiantes: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Acta de un estudiante en un periodo (por defecto el actual): sus observaciones, el
     * resumen positivas/negativas, y lo que el usuario necesita para registrar una nueva
     * (sus asignaturas en el curso del estudiante).
     */
    public function acta(AccesoObservaciones $acceso, int $idEstudiante, ?int $idPeriodo): array
    {
        try {
            $idUsuario = $acceso->idUsuario;
            $estudiante = $this->estudiantes($acceso->idsCursosEstudiantes())
                ->where('u.id_user', $idEstudiante)
                ->first(['u.id_user as id_estudiante', 'u.documento', self::nombre('u', 'estudiante'), 'u.id_curso', 'c.nombre as curso']);

            if (! $estudiante) {
                return self::fallo('Estudiante no encontrado o fuera de tus cursos.', 404);
            }

            $periodos = collect($this->periodos());
            $periodo = $idPeriodo ? $periodos->firstWhere('id', $idPeriodo) : $periodos->firstWhere('actual', true);

            $observaciones = $periodo
                ? $this->observaciones($acceso->soloDocente())
                    ->where('o.id_estudiante', $idEstudiante)
                    ->where('o.id_periodo_academico', $periodo['id'])
                    ->orderByDesc('o.fecha')
                    ->orderByDesc('o.id')
                    ->get()
                    ->map(fn ($o) => ['periodo_cerrado' => (bool) $o->periodo_cerrado, 'editable' => $acceso->puedeRegistrar && ! $o->periodo_cerrado && (int) $o->id_docente === $idUsuario] + (array) $o)
                : collect();

            return [
                'error' => false,
                'message' => 'Acta obtenida',
                'data' => [
                    'estudiante' => $estudiante,
                    'periodo' => $periodo,
                    'periodos' => $periodos->values(),
                    'observaciones' => $observaciones->values(),
                    'resumen' => [
                        'positivas' => $observaciones->where('tipo', 'POSITIVA')->count(),
                        'negativas' => $observaciones->where('tipo', 'NEGATIVA')->count(),
                    ],
                    'mis_asignaturas' => $this->asignaturasDelDocente($idUsuario, $estudiante->id_curso),
                    // Puede registrar a ESTE estudiante (ver todo no implica poder registrar).
                    'puede_registrar' => $acceso->puedeRegistrar
                        && ($acceso->idsCursosRegistro === null || in_array($estudiante->id_curso, $acceso->idsCursosRegistro)),
                    'ver_todas' => $acceso->verTodas,
                ],
            ];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al consultar el acta: ' . $e->getMessage(), 'data' => []];
        }
    }

    /**
     * Valida fecha/periodo/asignatura comunes a crear y actualizar. Devuelve [error|null, columnas].
     *
     * @param  array{fecha: string, tipo: string, motivo: string, id_asignatura?: int|null}  $datos
     */
    private function preparar(object $estudiante, array $datos, int $idUsuario): array
    {
        $periodo = $this->periodoDeFecha($datos['fecha']);

        if (! $periodo) {
            return [self::fallo('La fecha no pertenece a ningún periodo académico. Revisa los periodos en Configuración académica.'), []];
        }

        if ($periodo->fecha_fin < Carbon::today()->toDateString()) {
            return [self::fallo("El periodo \"{$periodo->nombre}\" ya cerró: su acta no se puede modificar.", 403), []];
        }

        $asignaturas = collect($this->asignaturasDelDocente($idUsuario, $estudiante->id_curso));
        $idAsignatura = $datos['id_asignatura'] ?? null;

        if ($idAsignatura !== null && ! $asignaturas->contains('id', $idAsignatura)) {
            return [self::fallo('No dictas esa asignatura en el curso del estudiante.', 422), []];
        }

        if ($idAsignatura === null && $asignaturas->count() > 1) {
            return [self::fallo('Dictas varias asignaturas en este curso: especifica a cuál corresponde la observación.', 422), []];
        }

        return [null, [
            'id_periodo_academico' => $periodo->id,
            'id_asignatura' => $idAsignatura ?? $asignaturas->first()?->id,
            'fecha' => $datos['fecha'],
            'tipo' => $datos['tipo'],
            'motivo' => trim($datos['motivo']),
        ]];
    }

    public function crear(AccesoObservaciones $acceso, int $idEstudiante, array $datos): array
    {
        try {
            if (! $acceso->puedeRegistrar) {
                return self::fallo('Tu permiso solo permite consultar observaciones, no registrarlas.', 403);
            }

            $idUsuario = $acceso->idUsuario;
            $estudiante = $this->estudiantes($acceso->idsCursosRegistro)->where('u.id_user', $idEstudiante)->first(['u.id_user', 'u.id_curso']);

            if (! $estudiante) {
                return self::fallo('Estudiante no encontrado o fuera de tus cursos.', 404);
            }

            [$error, $columnas] = $this->preparar($estudiante, $datos, $idUsuario);
            if ($error) {
                return $error;
            }

            $id = DB::table('academico_observacion_estudiante')->insertGetId($columnas + [
                'id_estudiante' => $idEstudiante,
                'id_docente' => $idUsuario,
                'id_curso' => $estudiante->id_curso,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return ['error' => false, 'message' => 'Observación registrada', 'data' => $this->observaciones()->where('o.id', $id)->first()];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al registrar la observación: ' . $e->getMessage(), 'data' => []];
        }
    }

    /** Observación propia, visible y de un periodo abierto — o el error de por qué no. */
    private function propiaEditable(AccesoObservaciones $acceso, int $id): array
    {
        // Sin permiso de registrar (p. ej. opción 135 "solo consulta") tampoco se tocan las
        // propias que registró antes.
        if (! $acceso->puedeRegistrar) {
            return [self::fallo('Tu permiso solo permite consultar observaciones, no modificarlas.', 403), null];
        }

        $obs = $this->observaciones($acceso->soloDocente())->where('o.id', $id)->first();

        if (! $obs) {
            return [self::fallo('Observación no encontrada.', 404), null];
        }
        $idUsuario = $acceso->idUsuario;
        if ((int) $obs->id_docente !== $idUsuario) {
            return [self::fallo('Solo quien registró la observación puede modificarla.', 403), null];
        }
        if ($obs->periodo_cerrado) {
            return [self::fallo("El periodo \"{$obs->periodo}\" ya cerró: su acta no se puede modificar.", 403), null];
        }

        return [null, $obs];
    }

    public function actualizar(AccesoObservaciones $acceso, int $id, array $datos): array
    {
        try {
            [$error, $obs] = $this->propiaEditable($acceso, $id);
            if ($error) {
                return $error;
            }

            // Asignaturas según el curso que tenía al registrarla, no el actual.
            [$error, $columnas] = $this->preparar((object) ['id_curso' => $obs->id_curso], $datos, $acceso->idUsuario);
            if ($error) {
                return $error;
            }

            DB::table('academico_observacion_estudiante')->where('id', $id)->update($columnas + ['updated_at' => now()]);

            return ['error' => false, 'message' => 'Observación actualizada', 'data' => $this->observaciones()->where('o.id', $id)->first()];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al actualizar la observación: ' . $e->getMessage(), 'data' => []];
        }
    }

    public function eliminar(AccesoObservaciones $acceso, int $id): array
    {
        try {
            [$error] = $this->propiaEditable($acceso, $id);
            if ($error) {
                return $error;
            }

            DB::table('academico_observacion_estudiante')->where('id', $id)->delete();

            return ['error' => false, 'message' => 'Observación eliminada', 'data' => []];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al eliminar la observación: ' . $e->getMessage(), 'data' => []];
        }
    }
}
