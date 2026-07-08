<?php

namespace App\Services\GestionAcademica;

use App\Models\GestionAcademica\AsistenciaEstudiante;
use App\Services\Service;
use Carbon\Carbon;
use Exception;


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