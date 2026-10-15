<?php

namespace App\Services\GestionAcademica;

use App\Models\AnioEscolar\Anio;
use App\Models\GestionAcademica\ActaEstudiante;
use App\Services\Service;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Registro de actas de estudiantes (diálogo formativo / convivencial). El acta física la
 * hace el docente; aquí solo se registra (rector, coordinador... — opción 134) y se consulta
 * con sus estadísticas (opción 133).
 *
 * Periodo y año NO los elige el usuario: salen de la fecha del acta (periodo_academico que
 * la contiene). El curso es la foto del curso del estudiante al registrar.
 *
 * Máximo de ActaEstudiante::MAX_DIALOGO_FORMATIVO actas de diálogo formativo por estudiante
 * y periodo: se permite registrar más, pero la respuesta trae una advertencia.
 */
class ActasEstudianteService extends Service
{
    private const PERFIL_ESTUDIANTE = 16;

    // Columnas por las que la tabla del frontend puede ordenar (sort=<clave>).
    private const ORDENES = [
        'fecha' => 'a.fecha',
        'estudiante' => 'u.apellido',
        'curso' => 'c.nombre',
        'tipo' => 'a.tipo',
        'periodo' => 'p.fecha_inicio',
        'docente_solicita' => 'd.apellido',
    ];

    // Mismas claves sobre la consulta agrupada (una fila por estudiante), más el total.
    private const ORDENES_AGRUPADO = [
        'fecha' => 't.fecha',
        'estudiante' => 't.apellido_orden',
        'curso' => 't.curso',
        'tipo' => 't.tipo',
        'periodo' => 't.periodo_inicio',
        'docente_solicita' => 't.docente_solicita',
        'total_actas' => 't.total_actas',
    ];

    private const NOMBRE_ESTUDIANTE = "TRIM(CONCAT(COALESCE(u.nombre, ''), ' ', COALESCE(u.apellido, '')))";

    /**
     * El docente que solicita tiene carga académica activa en el curso del acta (el del
     * estudiante al registrarla). Carga por asignatura (da.id_docente) o suelta (ca.id_docente),
     * igual que CargaAcademicaService::obtenerCursosDocente.
     */
    private const DOCENTE_ASOCIADO_SQL = "EXISTS (SELECT 1 FROM academico_carga_academica ca
        LEFT JOIN academico_docente_asignatura da ON da.id = ca.id_docente_asignatura
        WHERE ca.id_curso = a.id_curso AND ca.activo = 1
          AND (ca.id_docente = a.id_docente_solicita OR da.id_docente = a.id_docente_solicita))";

    private const NOMBRE_DOCENTE = "NULLIF(TRIM(CONCAT(COALESCE(d.nombre, ''), ' ', COALESCE(d.apellido, ''))), '')";

    private function base(): Builder
    {
        return DB::table('actas_estudiante as a')
            ->join('usuarios as u', 'u.id_user', '=', 'a.id_estudiante')
            ->join('curso as c', 'c.id', '=', 'a.id_curso')
            ->leftJoin('nivel_academico as n', 'n.id', '=', 'c.id_nivel')
            ->leftJoin('periodo_academico as p', 'p.id', '=', 'a.id_periodo_academico')
            ->leftJoin('anio_escolar as y', 'y.id', '=', 'a.id_anio_escolar')
            ->leftJoin('usuarios as d', 'd.id_user', '=', 'a.id_docente_solicita');
    }

    /** Año por defecto cuando el frontend no envía id_anio_escolar: el habilitado. */
    private function anioPorDefecto(): ?int
    {
        return Anio::where('activo', 1)->latest('id')->value('id');
    }

    /**
     * Alcance de la opción 138 "Ver actas de mis estudiantes": los estudiantes que HOY están en
     * los cursos con carga académica activa del docente, más las actas que él mismo solicitó
     * (aunque el estudiante no esté en sus cursos). null = sin límite. Lo fija el controller
     * antes de cada lectura; [] de cursos = solo las que solicitó.
     */
    private ?array $idsCursos = null;

    private ?int $idDocenteAlcance = null;

    public function limitarACursos(?array $idsCursos, ?int $idDocente = null): void
    {
        $this->idsCursos = $idsCursos === null ? null : array_values(array_map('intval', $idsCursos));
        $this->idDocenteAlcance = $idsCursos === null ? null : $idDocente;
    }

    /** IDs de estudiantes con alguna acta solicitada por el docente del alcance. */
    private function estudiantesSolicitados(): \Illuminate\Support\Collection
    {
        return $this->idDocenteAlcance === null
            ? collect()
            : DB::table('actas_estudiante')->where('id_docente_solicita', $this->idDocenteAlcance)->distinct()->pluck('id_estudiante');
    }

    private function filtrar(Builder $q, array $f, bool $conAnio = true): Builder
    {
        $anio = $conAnio ? ($f['id_anio_escolar'] ?? null) ?: $this->anioPorDefecto() : null;

        return $q
            // 138: estudiantes que HOY están en sus cursos, o actas que él solicitó.
            ->when($this->idsCursos !== null, fn ($q) => $q->where(fn ($w) => $w
                ->whereIn('u.id_curso', $this->idsCursos)
                ->when($this->idDocenteAlcance !== null, fn ($w) => $w->orWhere('a.id_docente_solicita', $this->idDocenteAlcance))))
            ->when(filled($f['id_docente_solicita'] ?? null), fn ($q) => $q->where('a.id_docente_solicita', $f['id_docente_solicita']))
            // asociacion: "no_asociado" = tiene docente y no dicta en el curso; "asociado" = sí dicta.
            ->when(($f['asociacion'] ?? '') === 'no_asociado', fn ($q) => $q->whereNotNull('a.id_docente_solicita')->whereRaw('NOT ' . self::DOCENTE_ASOCIADO_SQL))
            ->when(($f['asociacion'] ?? '') === 'asociado', fn ($q) => $q->whereRaw(self::DOCENTE_ASOCIADO_SQL))
            ->when($anio, fn ($q) => $q->where('a.id_anio_escolar', $anio))
            ->when($conAnio && filled($f['id_periodo_academico'] ?? null), fn ($q) => $q->where('a.id_periodo_academico', $f['id_periodo_academico']))
            ->when(filled($f['id_nivel'] ?? null), fn ($q) => $q->where('c.id_nivel', $f['id_nivel']))
            ->when(filled($f['id_curso'] ?? null), fn ($q) => $q->where('a.id_curso', $f['id_curso']))
            ->when(filled($f['id_estudiante'] ?? null), fn ($q) => $q->where('a.id_estudiante', $f['id_estudiante']))
            ->when(filled($f['tipo'] ?? null), fn ($q) => $q->where('a.tipo', $f['tipo']));
    }

    /**
     * Listado con los filtros. Con `$agrupar`, una fila por estudiante (como Llegadas tarde):
     * su acta más reciente + `total_actas`/`dialogo_total`/`convivencial_total` de las actas
     * que cumplen los filtros; el detalle se pide luego con `id_estudiante`.
     */
    public function listar(array $filtros, int $pagina = 1, int $porPagina = 20, ?string $busqueda = null, ?string $orden = null, ?string $direccion = null, bool $agrupar = false): array
    {
        try {
            $query = $this->filtrar($this->base(), $filtros)
                ->select([
                    'a.id',
                    'a.fecha',
                    'a.tipo',
                    'a.asunto',
                    'a.observacion',
                    'a.id_estudiante',
                    'u.documento',
                    DB::raw(self::NOMBRE_ESTUDIANTE . ' as estudiante'),
                    'a.id_curso',
                    'c.nombre as curso',
                    'n.nombre as nivel',
                    'a.id_periodo_academico',
                    'p.nombre as periodo',
                    'a.id_anio_escolar',
                    DB::raw("CONCAT(y.anio_inicio, '-', y.anio_fin) as anio"),
                    'a.id_docente_solicita',
                    DB::raw(self::NOMBRE_DOCENTE . ' as docente_solicita'),
                    // null = sin docente registrado; 0 = el docente no dicta en el curso del acta.
                    DB::raw('CASE WHEN a.id_docente_solicita IS NULL THEN NULL WHEN ' . self::DOCENTE_ASOCIADO_SQL . ' THEN 1 ELSE 0 END as docente_asociado'),
                ])
                // Actas de diálogo formativo del estudiante en el periodo de esta acta.
                ->selectSub(fn ($q) => $q
                    ->from('actas_estudiante as a2')
                    ->whereColumn('a2.id_estudiante', 'a.id_estudiante')
                    ->whereColumn('a2.id_periodo_academico', 'a.id_periodo_academico')
                    ->where('a2.tipo', ActaEstudiante::TIPO_DIALOGO_FORMATIVO)
                    ->selectRaw('count(*)'), 'conteo_dialogo')
                ->when(filled($busqueda), function ($q) use ($busqueda) {
                    $like = '%' . $busqueda . '%';
                    $q->where(fn ($w) => $w
                        ->where('u.nombre', 'like', $like)
                        ->orWhere('u.apellido', 'like', $like)
                        ->orWhere('u.documento', 'like', $like)
                        ->orWhere('c.nombre', 'like', $like)
                        ->orWhere('d.nombre', 'like', $like)
                        ->orWhere('d.apellido', 'like', $like)
                        ->orWhere('a.asunto', 'like', $like)
                        ->orWhere('a.observacion', 'like', $like));
                });

            $dir = strtolower((string) $direccion) === 'asc' ? 'asc' : 'desc';

            if ($agrupar) {
                // Funciones de ventana (MySQL 8 / MariaDB 10.2+): la última acta de cada
                // estudiante y sus totales, sin perder los filtros ni la búsqueda.
                $porEstudiante = 'PARTITION BY a.id_estudiante';
                $dialogo = ActaEstudiante::TIPO_DIALOGO_FORMATIVO;
                $convivencial = ActaEstudiante::TIPO_CONVIVENCIAL;
                $query->addSelect([
                    'u.apellido as apellido_orden',
                    'p.fecha_inicio as periodo_inicio',
                    DB::raw("ROW_NUMBER() OVER ({$porEstudiante} ORDER BY a.fecha DESC, a.id DESC) as rn"),
                    DB::raw("COUNT(*) OVER ({$porEstudiante}) as total_actas"),
                    DB::raw("SUM(a.tipo = '{$dialogo}') OVER ({$porEstudiante}) as dialogo_total"),
                    DB::raw("SUM(a.tipo = '{$convivencial}') OVER ({$porEstudiante}) as convivencial_total"),
                    DB::raw('SUM(CASE WHEN a.id_docente_solicita IS NOT NULL AND NOT ' . self::DOCENTE_ASOCIADO_SQL . " THEN 1 ELSE 0 END) OVER ({$porEstudiante}) as no_asociadas_total"),
                ]);
                $query = DB::query()->fromSub($query, 't')->where('t.rn', 1);
                $columnaOrden = self::ORDENES_AGRUPADO[$orden ?? ''] ?? null;
                $columnaOrden
                    ? $query->orderBy($columnaOrden, $dir)
                    : $query->orderByDesc('t.fecha')->orderByDesc('t.id');
            } else {
                $columnaOrden = self::ORDENES[$orden ?? ''] ?? null;
                $columnaOrden
                    ? $query->orderBy($columnaOrden, $dir)
                    : $query->orderByDesc('a.fecha')->orderByDesc('a.id');
            }

            $paginado = $query->paginate(max(1, min($porPagina, 100)), ['*'], 'page', max(1, $pagina));

            return ['error' => false, 'message' => 'Actas obtenidas', 'data' => $paginado];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al consultar las actas: ' . $e->getMessage(), 'data' => []];
        }
    }

    /** Periodo académico que contiene la fecha (de él sale también el año escolar). */
    private function periodoDeFecha(string $fecha): ?object
    {
        return DB::table('periodo_academico')
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->whereDate('fecha_fin', '>=', $fecha)
            ->orderByDesc('activo')
            ->orderByDesc('id')
            ->first(['id', 'id_anio_escolar', 'nombre']);
    }

    private function conteoDialogo(int $idEstudiante, int $idPeriodo, ?int $excluirId = null): int
    {
        return ActaEstudiante::where('id_estudiante', $idEstudiante)
            ->where('id_periodo_academico', $idPeriodo)
            ->where('tipo', ActaEstudiante::TIPO_DIALOGO_FORMATIVO)
            ->when($excluirId, fn ($q) => $q->where('id', '!=', $excluirId))
            ->count();
    }

    private function advertencia(int $conteo): ?string
    {
        $max = ActaEstudiante::MAX_DIALOGO_FORMATIVO;

        return match (true) {
            $conteo > $max => "El estudiante supera el máximo de {$max} actas de diálogo formativo en este periodo ({$conteo}).",
            $conteo === $max => "El estudiante alcanzó el máximo de {$max} actas de diálogo formativo en este periodo.",
            $conteo === $max - 1 => "El estudiante se acerca al máximo: lleva {$conteo} de {$max} actas de diálogo formativo en este periodo.",
            default => null,
        };
    }

    /**
     * Conteo de diálogo formativo del estudiante en el periodo de la fecha dada — para que el
     * formulario avise ANTES de guardar. excluirId = acta que se está editando.
     */
    public function conteo(int $idEstudiante, string $fecha, ?int $excluirId = null): array
    {
        try {
            if ($this->idsCursos !== null
                && ! DB::table('usuarios')->where('id_user', $idEstudiante)->whereIn('id_curso', $this->idsCursos)->exists()
                && ! $this->estudiantesSolicitados()->contains($idEstudiante)) {
                return ['error' => true, 'message' => 'El estudiante no está en tus cursos.', 'data' => [], 'status' => 403];
            }

            $periodo = $this->periodoDeFecha($fecha);

            if (! $periodo) {
                return ['error' => true, 'message' => 'La fecha no pertenece a ningún periodo académico.', 'data' => [], 'status' => 422];
            }

            $conteo = $this->conteoDialogo($idEstudiante, $periodo->id, $excluirId);

            return ['error' => false, 'message' => 'Conteo obtenido', 'data' => [
                'periodo' => $periodo,
                'conteo_dialogo' => $conteo,
                'conteo_convivencial' => ActaEstudiante::where('id_estudiante', $idEstudiante)
                    ->where('id_periodo_academico', $periodo->id)
                    ->where('tipo', ActaEstudiante::TIPO_CONVIVENCIAL)
                    ->when($excluirId, fn ($q) => $q->where('id', '!=', $excluirId))
                    ->count(),
                'max_dialogo' => ActaEstudiante::MAX_DIALOGO_FORMATIVO,
            ]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al obtener el conteo de actas: ' . $e->getMessage(), 'data' => []];
        }
    }

    /** Resuelve curso (foto actual del estudiante), periodo y año a partir de los datos enviados. */
    private function resolver(array $datos): array
    {
        $estudiante = DB::table('usuarios')
            ->where('id_user', $datos['id_estudiante'])
            ->where('perfil', self::PERFIL_ESTUDIANTE)
            ->first(['id_user', 'id_curso']);

        if (! $estudiante) {
            return ['error' => 'El usuario seleccionado no es un estudiante.'];
        }

        if (! $estudiante->id_curso) {
            return ['error' => 'El estudiante no tiene un curso asignado.'];
        }

        $periodo = $this->periodoDeFecha($datos['fecha']);

        if (! $periodo) {
            return ['error' => 'La fecha no pertenece a ningún periodo académico.'];
        }

        $idDocente = $datos['id_docente_solicita'] ?? null;

        if ($idDocente !== null && ! DB::table('usuarios')
            ->where('id_user', $idDocente)
            ->whereIn('perfil', ActaEstudiante::PERFILES_DOCENTE)
            ->where('estado', 'activo')
            ->exists()) {
            return ['error' => 'El docente que solicita el acta no es un docente activo.'];
        }

        return ['datos' => [
            'id_estudiante' => $estudiante->id_user,
            'id_curso' => $estudiante->id_curso,
            'id_periodo_academico' => $periodo->id,
            'id_anio_escolar' => $periodo->id_anio_escolar,
            'tipo' => $datos['tipo'],
            'asunto' => trim($datos['asunto']),
            'fecha' => $datos['fecha'],
            'observacion' => $datos['observacion'] ?? null,
            'id_docente_solicita' => $idDocente,
        ]];
    }

    private function guardado(ActaEstudiante $acta, string $mensaje): array
    {
        $conteo = $this->conteoDialogo($acta->id_estudiante, $acta->id_periodo_academico);

        return ['error' => false, 'message' => $mensaje, 'data' => [
            'acta' => $acta,
            'conteo_dialogo' => $conteo,
            'max_dialogo' => ActaEstudiante::MAX_DIALOGO_FORMATIVO,
            'advertencia' => $this->advertencia($conteo),
        ]];
    }

    /**
     * Registra varias actas a la vez (todo o nada). Si una falla, no se guarda ninguna y el
     * mensaje dice cuál. Las advertencias se calculan después de insertar todo el lote, así
     * que cuentan también las actas del mismo estudiante que vienen juntas.
     */
    public function crear(array $actas, int $idUsuario): array
    {
        try {
            $filas = [];

            foreach (array_values($actas) as $i => $datos) {
                $resuelto = $this->resolver($datos);

                if (isset($resuelto['error'])) {
                    $prefijo = count($actas) > 1 ? 'Acta ' . ($i + 1) . ': ' : '';

                    return ['error' => true, 'message' => $prefijo . $resuelto['error'], 'data' => [], 'status' => 422];
                }

                $filas[] = $resuelto['datos'] + ['id_usuario_registra' => $idUsuario];
            }

            $creadas = DB::transaction(fn () => collect($filas)->map(fn ($f) => ActaEstudiante::create($f)));

            $advertencias = $creadas
                ->unique(fn ($a) => $a->id_estudiante . '-' . $a->id_periodo_academico)
                ->map(function ($a) {
                    $aviso = $this->advertencia($this->conteoDialogo($a->id_estudiante, $a->id_periodo_academico));
                    $nombre = DB::table('usuarios')->where('id_user', $a->id_estudiante)
                        ->value(DB::raw("TRIM(CONCAT(COALESCE(nombre, ''), ' ', COALESCE(apellido, '')))"));

                    return $aviso ? "{$nombre}: {$aviso}" : null;
                })
                ->filter()
                ->values();

            return ['error' => false, 'message' => $creadas->count() === 1 ? 'Acta registrada' : $creadas->count() . ' actas registradas', 'data' => [
                'actas' => $creadas,
                'advertencias' => $advertencias,
            ]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al registrar las actas: ' . $e->getMessage(), 'data' => []];
        }
    }

    public function actualizar(int $id, array $datos): array
    {
        try {
            $acta = ActaEstudiante::find($id);

            if (! $acta) {
                return ['error' => true, 'message' => 'Acta no encontrada.', 'data' => [], 'status' => 404];
            }

            $resuelto = $this->resolver($datos);

            if (isset($resuelto['error'])) {
                return ['error' => true, 'message' => $resuelto['error'], 'data' => [], 'status' => 422];
            }

            // El curso es la foto del momento del registro: solo se recalcula si cambia el estudiante.
            if ((int) $acta->id_estudiante === (int) $resuelto['datos']['id_estudiante']) {
                unset($resuelto['datos']['id_curso']);
            }

            $acta->update($resuelto['datos']);

            return $this->guardado($acta, 'Acta actualizada');
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al actualizar el acta: ' . $e->getMessage(), 'data' => []];
        }
    }

    public function eliminar(int $id): array
    {
        try {
            $acta = ActaEstudiante::find($id);

            if (! $acta) {
                return ['error' => true, 'message' => 'Acta no encontrada.', 'data' => [], 'status' => 404];
            }

            $acta->delete();

            return ['error' => false, 'message' => 'Acta eliminada', 'data' => []];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al eliminar el acta: ' . $e->getMessage(), 'data' => []];
        }
    }

    /** Catálogos para filtros y formulario. Los estudiantes traen id_curso para filtrar en el cliente. */
    public function filtros(): array
    {
        try {
            return ['error' => false, 'message' => 'Filtros obtenidos', 'data' => [
                'id_anio_actual' => $this->anioPorDefecto(),
                'anios' => DB::table('anio_escolar')
                    ->orderByDesc('anio_inicio')
                    ->get(['id', DB::raw("CONCAT(anio_inicio, '-', anio_fin) as nombre")]),
                'periodos' => DB::table('periodo_academico')
                    ->orderBy('fecha_inicio')
                    ->get(['id', 'id_anio_escolar', 'nombre', 'fecha_inicio', 'fecha_fin']),
                'niveles' => DB::table('nivel_academico')->orderBy('id')->get(['id', 'nombre']),
                'cursos' => DB::table('curso')
                    ->where('activo', 1)
                    ->when($this->idsCursos !== null, fn ($q) => $q->where(fn ($w) => $w
                        ->whereIn('id', $this->idsCursos)
                        ->orWhereIn('id', DB::table('usuarios')->whereIn('id_user', $this->estudiantesSolicitados())->pluck('id_curso'))))
                    ->orderBy('nombre')
                    ->get(['id', 'nombre', 'id_nivel']),
                'estudiantes' => DB::table('usuarios as u')
                    ->leftJoin('curso as c', 'c.id', '=', 'u.id_curso')
                    ->leftJoin('nivel_academico as n', 'n.id', '=', 'c.id_nivel')
                    ->where('u.perfil', self::PERFIL_ESTUDIANTE)
                    ->where('u.estado', 'activo')
                    ->when($this->idsCursos !== null, fn ($q) => $q->where(fn ($w) => $w
                        ->whereIn('u.id_curso', $this->idsCursos)
                        ->orWhereIn('u.id_user', $this->estudiantesSolicitados())))
                    ->orderBy('u.apellido')
                    ->orderBy('u.nombre')
                    ->get([
                        'u.id_user as id',
                        DB::raw(self::NOMBRE_ESTUDIANTE . ' as nombre'),
                        'u.documento',
                        'u.id_curso',
                        'c.nombre as curso',
                        'c.id_nivel',
                        'n.nombre as nivel',
                        // Nombre de archivo; el frontend arma la URL con publicFileUrl.
                        'u.foto_carnet',
                    ]),
                // Para "Docente que solicita" del formulario.
                'docentes' => DB::table('usuarios')
                    ->whereIn('perfil', ActaEstudiante::PERFILES_DOCENTE)
                    ->where('estado', 'activo')
                    ->orderBy('apellido')
                    ->orderBy('nombre')
                    ->get(['id_user as id', DB::raw("TRIM(CONCAT(COALESCE(nombre, ''), ' ', COALESCE(apellido, ''))) as nombre"), 'documento']),
            ]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al obtener los filtros: ' . $e->getMessage(), 'data' => []];
        }
    }

    /** SUM por tipo para pivotear cada agrupación en una sola consulta. */
    private function conteosPorTipo(Builder $q): Builder
    {
        return $q->selectRaw(
            "SUM(a.tipo = ?) as dialogo_formativo, SUM(a.tipo = ?) as convivencial, COUNT(*) as total",
            [ActaEstudiante::TIPO_DIALOGO_FORMATIVO, ActaEstudiante::TIPO_CONVIVENCIAL]
        );
    }

    /**
     * Agregados para la página de gráficas, con los mismos filtros del listado. "por_anio" es
     * histórico: ignora el filtro de año/periodo para poder comparar años.
     */
    public function estadisticas(array $filtros): array
    {
        try {
            $filtrada = fn () => $this->filtrar($this->base(), $filtros);
            $enteros = fn ($filas) => collect($filas)->map(function ($f) {
                foreach (['dialogo_formativo', 'convivencial', 'total'] as $k) {
                    $f->$k = (int) $f->$k;
                }

                return $f;
            })->values();

            $resumen = $this->conteosPorTipo($filtrada())
                ->selectRaw('COUNT(DISTINCT a.id_estudiante) as estudiantes')
                ->first();

            // Estudiantes cerca del máximo / en el máximo de diálogo formativo, por periodo.
            $porEstudiantePeriodo = $filtrada()
                ->where('a.tipo', ActaEstudiante::TIPO_DIALOGO_FORMATIVO)
                ->groupBy('a.id_estudiante', 'a.id_periodo_academico')
                ->selectRaw('a.id_estudiante, COUNT(*) as n')
                ->get()
                ->groupBy('id_estudiante')
                ->map(fn ($filas) => $filas->max('n'));
            $max = ActaEstudiante::MAX_DIALOGO_FORMATIVO;

            return ['error' => false, 'message' => 'Estadísticas obtenidas', 'data' => [
                'resumen' => [
                    'total' => (int) $resumen->total,
                    'dialogo_formativo' => (int) $resumen->dialogo_formativo,
                    'convivencial' => (int) $resumen->convivencial,
                    'estudiantes' => (int) $resumen->estudiantes,
                    'estudiantes_cerca_maximo' => $porEstudiantePeriodo->filter(fn ($n) => $n === $max - 1)->count(),
                    'estudiantes_en_maximo' => $porEstudiantePeriodo->filter(fn ($n) => $n >= $max)->count(),
                    'max_dialogo' => $max,
                ],
                'por_nivel' => $enteros($this->conteosPorTipo($filtrada())
                    ->addSelect('c.id_nivel as id', DB::raw("COALESCE(n.nombre, 'Sin nivel') as nombre"))
                    ->groupBy('c.id_nivel', 'n.nombre')
                    ->orderBy('c.id_nivel')
                    ->get()),
                'por_curso' => $enteros($this->conteosPorTipo($filtrada())
                    ->addSelect('a.id_curso as id', 'c.nombre')
                    ->groupBy('a.id_curso', 'c.nombre')
                    ->orderBy('c.nombre')
                    ->get()),
                'por_periodo' => $enteros($this->conteosPorTipo($filtrada())
                    ->addSelect('a.id_periodo_academico as id', 'p.nombre', 'p.fecha_inicio')
                    ->groupBy('a.id_periodo_academico', 'p.nombre', 'p.fecha_inicio')
                    ->orderBy('p.fecha_inicio')
                    ->get()),
                'por_anio' => $enteros($this->conteosPorTipo($this->filtrar($this->base(), $filtros, conAnio: false))
                    ->addSelect('a.id_anio_escolar as id', DB::raw("CONCAT(y.anio_inicio, '-', y.anio_fin) as nombre"), 'y.anio_inicio')
                    ->groupBy('a.id_anio_escolar', 'y.anio_inicio', 'y.anio_fin')
                    ->orderBy('y.anio_inicio')
                    ->get()),
                'top_estudiantes' => $enteros($this->conteosPorTipo($filtrada())
                    // MAX(curso): si el estudiante cambió de curso, una sola fila por estudiante.
                    ->addSelect('a.id_estudiante as id', DB::raw(self::NOMBRE_ESTUDIANTE . ' as nombre'), DB::raw('MAX(c.nombre) as curso'))
                    ->groupBy('a.id_estudiante', 'u.nombre', 'u.apellido')
                    ->orderByDesc('total')
                    ->limit(10)
                    ->get()),
            ]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al obtener las estadísticas de actas: ' . $e->getMessage(), 'data' => []];
        }
    }
}
