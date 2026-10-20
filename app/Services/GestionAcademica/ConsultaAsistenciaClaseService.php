<?php

namespace App\Services\GestionAcademica;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;
use App\Support\Permiso;

/**
 * Consulta de solo lectura de las asistencias de clase de TODOS los docentes: listado de
 * sesiones (una fila por `academico_asistencia_clase`) con sus excepciones contadas, y el
 * registro por estudiante de una sesión. Nada aquí escribe — la toma de asistencia sigue
 * siendo del docente (GestionAcademicaController).
 *
 * Recordatorio del modelo: `academico_asistencia_estudiante` solo guarda excepciones
 * (AUSENTE/TARDE/PERMISO); un estudiante sin fila en una sesión estuvo presente.
 */
class ConsultaAsistenciaClaseService
{
    private const PERFIL_ESTUDIANTE = 16;

    private const PERFILES_DOCENTE = [Permiso::PERFIL_DOCENTE, Permiso::PERFIL_DOCENTE_ICT];

    // Columnas por las que la tabla del frontend puede ordenar (sort=<clave>).
    private const ORDENES = [
        'fecha' => 'ac.fecha',
        'curso' => 'c.nombre',
        'asignatura' => 'asig.nombre',
        'docente' => 'doc.apellido',
        'estado' => 'ac.estado',
        'ausentes' => 'ausentes',
    ];

    /** Base de la consulta de sesiones con todo lo necesario para mostrarlas. */
    private function sesiones(): Builder
    {
        $excepciones = fn (string $estado) => fn ($q) => $q
            ->from('academico_asistencia_estudiante')
            ->whereColumn('id_asistencia_clase', 'ac.id')
            ->where('estado', $estado)
            ->selectRaw('count(*)');

        return DB::table('academico_asistencia_clase as ac')
            ->join('academico_horario_clase as hc', 'hc.id', '=', 'ac.id_horario_clase')
            ->join('academico_carga_academica as ca', 'ca.id', '=', 'hc.id_carga_academica')
            ->leftJoin('academico_docente_asignatura as da', 'da.id', '=', 'ca.id_docente_asignatura')
            ->leftJoin('academico_asignatura as asig', 'asig.id', '=', 'da.id_asignatura')
            // Carga "suelta" (sin asignatura) guarda el docente directo en ca.id_docente;
            // la normal, en la asignación docente-asignatura — ver CargaAcademica::id_docente_efectivo.
            ->leftJoin('usuarios as doc', 'doc.id_user', '=', DB::raw('COALESCE(ca.id_docente, da.id_docente)'))
            ->leftJoin('curso as c', 'c.id', '=', 'ca.id_curso')
            ->leftJoin('academico_franja_horaria as fh', 'fh.id', '=', 'hc.id_franja_horaria')
            ->leftJoin('dias_semana as d', 'd.id', '=', 'fh.id_dia_semana')
            ->select([
                'ac.id',
                'ac.fecha',
                'ac.estado',
                'ac.observacion',
                'hc.descripcion',
                'ca.id_curso',
                'c.nombre as curso',
                'asig.id as id_asignatura',
                'asig.nombre as asignatura',
                'doc.id_user as id_docente',
                DB::raw("TRIM(CONCAT(COALESCE(doc.nombre, ''), ' ', COALESCE(doc.apellido, ''))) as docente"),
                'fh.hora_inicio',
                'fh.hora_fin',
                'd.nombre as dia',
            ])
            ->selectSub($excepciones('AUSENTE'), 'ausentes')
            ->selectSub($excepciones('TARDE'), 'tardanzas')
            ->selectSub($excepciones('PERMISO'), 'permisos')
            // Estudiantes activos HOY en el curso (no hay foto histórica del curso por fecha).
            ->selectSub(fn ($q) => $q
                ->from('usuarios')
                ->whereColumn('id_curso', 'ca.id_curso')
                ->where('perfil', self::PERFIL_ESTUDIANTE)
                ->where('estado', 'activo')
                ->selectRaw('count(*)'), 'total_estudiantes');
    }

    /** % de asistencia de una sesión dictada (ausentes sobre el total del curso). */
    private function conPorcentaje(object $fila): object
    {
        $total = (int) $fila->total_estudiantes;
        $fila->porcentaje_asistencia = $fila->estado === 'DICTADA' && $total > 0
            ? round((1 - ((int) $fila->ausentes) / $total) * 100, 1)
            : null;

        return $fila;
    }

    public function listar(array $filtros, int $pagina = 1, int $porPagina = 20, ?string $busqueda = null, ?string $orden = null, ?string $direccion = null): array
    {
        try {
            foreach (['fecha_inicio', 'fecha_fin'] as $campo) {
                if (filled($filtros[$campo] ?? null)) {
                    Carbon::createFromFormat('Y-m-d', $filtros[$campo]);
                }
            }

            $query = $this->sesiones()
                ->when(filled($filtros['fecha_inicio'] ?? null), fn ($q) => $q->whereDate('ac.fecha', '>=', $filtros['fecha_inicio']))
                ->when(filled($filtros['fecha_fin'] ?? null), fn ($q) => $q->whereDate('ac.fecha', '<=', $filtros['fecha_fin']))
                ->when(filled($filtros['id_curso'] ?? null), fn ($q) => $q->where('ca.id_curso', $filtros['id_curso']))
                ->when(filled($filtros['id_asignatura'] ?? null), fn ($q) => $q->where('da.id_asignatura', $filtros['id_asignatura']))
                ->when(filled($filtros['id_docente'] ?? null), fn ($q) => $q->where(DB::raw('COALESCE(ca.id_docente, da.id_docente)'), $filtros['id_docente']))
                ->when(filled($filtros['estado'] ?? null), fn ($q) => $q->where('ac.estado', $filtros['estado']))
                ->when(filled($busqueda), function ($q) use ($busqueda) {
                    $like = '%' . $busqueda . '%';
                    $q->where(fn ($w) => $w
                        ->where('c.nombre', 'like', $like)
                        ->orWhere('asig.nombre', 'like', $like)
                        ->orWhere('doc.nombre', 'like', $like)
                        ->orWhere('doc.apellido', 'like', $like)
                        ->orWhere('ac.observacion', 'like', $like));
                });

            $columnaOrden = self::ORDENES[$orden ?? ''] ?? null;
            $dir = strtolower((string) $direccion) === 'asc' ? 'asc' : 'desc';

            if ($columnaOrden) {
                $query->orderBy($columnaOrden, $dir);
            } else {
                $query->orderByDesc('ac.fecha')->orderByDesc('fh.hora_inicio');
            }

            $paginado = $query->paginate(max(1, min($porPagina, 100)), ['*'], 'page', max(1, $pagina));
            $paginado->getCollection()->transform(fn ($fila) => $this->conPorcentaje($fila));

            return ['error' => false, 'message' => 'Asistencias de clases obtenidas', 'data' => $paginado];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al consultar las asistencias de clases: ' . $e->getMessage(), 'data' => []];
        }
    }

    public function detalle(int $idAsistenciaClase): array
    {
        try {
            $sesion = $this->sesiones()->where('ac.id', $idAsistenciaClase)->first();

            if (! $sesion) {
                return ['error' => true, 'message' => 'Clase no encontrada.', 'data' => []];
            }

            $excepciones = DB::table('academico_asistencia_estudiante')
                ->where('id_asistencia_clase', $idAsistenciaClase)
                ->get(['id_alumno', 'estado', 'observacion'])
                ->keyBy('id_alumno');

            // Estudiantes activos del curso + cualquiera con excepción registrada que ya no
            // esté en el curso (para no perder el registro histórico de la sesión).
            $estudiantes = DB::table('usuarios')
                ->where('perfil', self::PERFIL_ESTUDIANTE)
                ->where(fn ($q) => $q
                    ->where(fn ($w) => $w->where('id_curso', $sesion->id_curso)->where('estado', 'activo'))
                    ->orWhereIn('id_user', $excepciones->keys()))
                ->orderBy('apellido')
                ->orderBy('nombre')
                ->get(['id_user', 'documento', 'nombre', 'apellido'])
                ->map(function ($e) use ($excepciones) {
                    $excepcion = $excepciones->get($e->id_user);

                    return [
                        'id_estudiante' => $e->id_user,
                        'documento' => $e->documento,
                        'nombre' => trim(($e->nombre ?? '') . ' ' . ($e->apellido ?? '')),
                        'estado' => $excepcion->estado ?? 'PRESENTE',
                        'observacion' => $excepcion->observacion ?? null,
                    ];
                })
                ->values();

            return [
                'error' => false,
                'message' => 'Registro de la clase obtenido',
                'data' => ['clase' => $this->conPorcentaje($sesion), 'estudiantes' => $estudiantes],
            ];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al consultar el registro de la clase: ' . $e->getMessage(), 'data' => []];
        }
    }

    /** Catálogos para los filtros de la consulta (no dependen de los permisos de la 99). */
    public function filtros(): array
    {
        try {
            return [
                'error' => false,
                'message' => 'Filtros obtenidos',
                'data' => [
                    'cursos' => DB::table('curso')->where('activo', 1)->orderBy('nombre')->get(['id', 'nombre']),
                    'asignaturas' => DB::table('academico_asignatura')->where('activo', 1)->orderBy('nombre')->get(['id', 'nombre']),
                    'docentes' => DB::table('usuarios')
                        ->whereIn('perfil', self::PERFILES_DOCENTE)
                        ->where('estado', 'activo')
                        ->orderBy('apellido')
                        ->orderBy('nombre')
                        ->get([DB::raw('id_user as id'), DB::raw("TRIM(CONCAT(COALESCE(nombre, ''), ' ', COALESCE(apellido, ''))) as nombre")]),
                ],
            ];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => 'Error al obtener los filtros: ' . $e->getMessage(), 'data' => []];
        }
    }
}
