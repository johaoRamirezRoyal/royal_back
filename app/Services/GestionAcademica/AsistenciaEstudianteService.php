<?php

namespace App\Services\GestionAcademica;

use App\Models\GestionAcademica\AsistenciaEstudiante;
use App\Services\Service;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;


/**
 * Método para ver la asistencia de los estudiantes, en base a los filtros.
 * @param int $id_estudiante
 * @param int $id_curso
 * @param string $fecha
 * @param int $id_clase
 * @param int $id_horario_clase
 * @return array [error, message, data]
 */
class AsistenciaEstudianteService extends Service
{
    public function verAsistenciaEstudiantesFiltrada(
        ?int $id_estudiante = null,
        ?int $id_curso = null,
        ?string $fecha = null,
        ?int $id_clase = null,
        ?int $id_horario_clase = null
    ): array {
        try {
            $todosNulos = is_null($id_estudiante) && is_null($id_curso)
                && is_null($fecha) && is_null($id_clase) && is_null($id_horario_clase);

            if ($todosNulos) {
                return [
                    'error'   => true,
                    'message' => 'Debe proporcionar al menos un filtro o un horario de clase.',
                    'data'    => []
                ];
            }

            if (!is_null($fecha)) {
                Carbon::createFromFormat('Y-m-d', $fecha);
            }

            $asistencias = AsistenciaEstudiante::with([
                'asistenciaClase.horarioClase.cargaAcademica.curso',
                'asistenciaClase.horarioClase.franjaHoraria',
                'alumno',
            ])
                ->when(filled($id_estudiante), fn($q) => $q->where('id_alumno', $id_estudiante))
                ->when(filled($id_clase), fn($q) => $q->where('id_asistencia_clase', $id_clase))
                ->when(filled($id_horario_clase), fn($q) => $q->whereHas('asistenciaClase', fn($q2) => $q2->where('id_horario_clase', $id_horario_clase)))
                ->when(filled($fecha), fn($q) => $q->whereHas('asistenciaClase', fn($q2) => $q2->whereDate('fecha', $fecha)))
                ->when(filled($id_curso), fn($q) => $q->whereHas('asistenciaClase.horarioClase.cargaAcademica', fn($q2) => $q2->where('id_curso', $id_curso)))
                ->get();

            if ($asistencias->isEmpty()) {
                return [
                    'error'   => true,
                    'message' => 'No se encontraron asistencias.',
                    'data'    => []
                ];
            }

            return [
                'error'   => false,
                'message' => 'Asistencias obtenidas.',
                'data'    => $asistencias->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al filtrar asistencias del estudiante');

            return [
                'error'   => true,
                'message' => 'Error en el servidor al filtrar asistencias.',
                'data'    => []
            ];
        }
    }

    public function agregarAsistenciaEstudiantes(array $estudiantes, int $id_asistencia_clase): array
    {
        $estadosValidos = ['AUSENTE', 'TARDE', 'PERMISO'];
        $insert = [];

        try {

            foreach ($estudiantes as $estudiante) {

                if (!in_array($estudiante['estado'], $estadosValidos, true)) {
                    return [
                        'error' => true,
                        'message' => 'Solo puede estar la asistencia en los estados: ' . implode(', ', $estadosValidos),
                        'data' => $estudiante
                    ];
                }

                $insert[] = [
                    'id_alumno' => $estudiante['id_alumno'],
                    'id_asistencia_clase' => $id_asistencia_clase,
                    'estado' => $estudiante['estado'],
                    'observacion' => $estudiante['observacion'] ?? null,
                ];
            }

            AsistenciaEstudiante::insert($insert);

            return [
                'error' => false,
                'message' => 'Se registraron las asistencias.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al registrar las asistencias');

            return [
                'error' => true,
                'message' => 'Error en el servidor al registrar las asistencias',
                'data' => []
            ];
        }
    }

    /**
     * Métricas de asistencia por curso, para el dashboard de solo lectura de
     * Vicerrectoría/Directivo Docente (opción 102). Solo cuenta clases ya DICTADAs —
     * la asistencia de estudiantes solo registra excepciones (AUSENTE/TARDE/PERMISO,
     * ver agregarAsistenciaEstudiantes), no hay fila por cada presente.
     */
    public function metricasPorCurso(?string $fecha_inicio = null, ?string $fecha_fin = null, ?int $id_curso = null): array
    {
        try {
            $totalEstudiantes = DB::table('usuarios')
                ->where('perfil', 16) // Estudiante
                ->where('estado', 'activo')
                ->when(filled($id_curso), fn($q) => $q->where('id_curso', $id_curso))
                ->groupBy('id_curso')
                ->select('id_curso', DB::raw('count(*) as total'))
                ->pluck('total', 'id_curso');

            $totalClases = DB::table('academico_asistencia_clase as ac')
                ->join('academico_horario_clase as hc', 'hc.id', '=', 'ac.id_horario_clase')
                ->join('academico_carga_academica as ca', 'ca.id', '=', 'hc.id_carga_academica')
                ->where('ac.estado', 'DICTADA')
                ->when(filled($fecha_inicio), fn($q) => $q->whereDate('ac.fecha', '>=', $fecha_inicio))
                ->when(filled($fecha_fin), fn($q) => $q->whereDate('ac.fecha', '<=', $fecha_fin))
                ->when(filled($id_curso), fn($q) => $q->where('ca.id_curso', $id_curso))
                ->groupBy('ca.id_curso')
                ->select('ca.id_curso', DB::raw('count(*) as total'))
                ->pluck('total', 'id_curso');

            $estadosPorCurso = DB::table('academico_asistencia_estudiante as ae')
                ->join('academico_asistencia_clase as ac', 'ac.id', '=', 'ae.id_asistencia_clase')
                ->join('academico_horario_clase as hc', 'hc.id', '=', 'ac.id_horario_clase')
                ->join('academico_carga_academica as ca', 'ca.id', '=', 'hc.id_carga_academica')
                ->where('ac.estado', 'DICTADA')
                ->when(filled($fecha_inicio), fn($q) => $q->whereDate('ac.fecha', '>=', $fecha_inicio))
                ->when(filled($fecha_fin), fn($q) => $q->whereDate('ac.fecha', '<=', $fecha_fin))
                ->when(filled($id_curso), fn($q) => $q->where('ca.id_curso', $id_curso))
                ->groupBy('ca.id_curso', 'ae.estado')
                ->select('ca.id_curso', 'ae.estado', DB::raw('count(*) as total'))
                ->get()
                ->groupBy('id_curso');

            $cursos = DB::table('curso')
                ->when(filled($id_curso), fn($q) => $q->where('id', $id_curso))
                ->pluck('nombre', 'id');

            $porCurso = [];
            foreach ($cursos as $idCurso => $nombreCurso) {
                $estudiantes = (int) ($totalEstudiantes[$idCurso] ?? 0);
                $clases = (int) ($totalClases[$idCurso] ?? 0);
                $estados = collect($estadosPorCurso[$idCurso] ?? [])->pluck('total', 'estado');
                $ausencias = (int) ($estados['AUSENTE'] ?? 0);
                $tardanzas = (int) ($estados['TARDE'] ?? 0);
                $permisos = (int) ($estados['PERMISO'] ?? 0);
                $totalPosible = $estudiantes * $clases;

                $porCurso[] = [
                    'id_curso' => $idCurso,
                    'curso' => $nombreCurso,
                    'total_estudiantes' => $estudiantes,
                    'total_clases_dictadas' => $clases,
                    'ausencias' => $ausencias,
                    'tardanzas' => $tardanzas,
                    'permisos' => $permisos,
                    'porcentaje_asistencia' => $totalPosible > 0
                        ? round((1 - $ausencias / $totalPosible) * 100, 1)
                        : null,
                ];
            }

            $topAusentismo = DB::table('academico_asistencia_estudiante as ae')
                ->join('academico_asistencia_clase as ac', 'ac.id', '=', 'ae.id_asistencia_clase')
                ->join('academico_horario_clase as hc', 'hc.id', '=', 'ac.id_horario_clase')
                ->join('academico_carga_academica as ca', 'ca.id', '=', 'hc.id_carga_academica')
                ->join('usuarios as u', 'u.id_user', '=', 'ae.id_alumno')
                ->join('curso as c', 'c.id', '=', 'ca.id_curso')
                ->where('ac.estado', 'DICTADA')
                ->where('ae.estado', 'AUSENTE')
                ->when(filled($fecha_inicio), fn($q) => $q->whereDate('ac.fecha', '>=', $fecha_inicio))
                ->when(filled($fecha_fin), fn($q) => $q->whereDate('ac.fecha', '<=', $fecha_fin))
                ->when(filled($id_curso), fn($q) => $q->where('ca.id_curso', $id_curso))
                ->groupBy('ae.id_alumno', 'u.nombre', 'u.apellido', 'c.nombre')
                ->select(
                    'ae.id_alumno',
                    'u.nombre',
                    'u.apellido',
                    'c.nombre as curso',
                    DB::raw('count(*) as total_ausencias')
                )
                ->orderByDesc('total_ausencias')
                ->limit(10)
                ->get();

            return [
                'error' => false,
                'message' => 'Métricas obtenidas.',
                'data' => [
                    'por_curso' => $porCurso,
                    'top_ausentismo' => $topAusentismo,
                ],
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al calcular métricas de asistencia');

            return [
                'error' => true,
                'message' => 'Error en el servidor al calcular métricas de asistencia.',
                'data' => []
            ];
        }
    }

    public function eliminarAsistenciaEstudiante(array $ids): array
    {
        if (empty($ids)) {
            return [
                'error' => true,
                'message' => 'Debe enviar al menos un ID de asistencia.',
                'data' => []
            ];
        }

        try {
            $eliminados = AsistenciaEstudiante::whereIn('id', $ids)->delete();

            if ($eliminados === 0) {
                return [
                    'error' => true,
                    'message' => 'No se eliminó ninguna asistencia.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se eliminaron {$eliminados} asistencias.",
                'data' => [
                    'eliminados' => $eliminados
                ]
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar las asistencias');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar las asistencias.',
                'data' => []
            ];
        }
    }
}