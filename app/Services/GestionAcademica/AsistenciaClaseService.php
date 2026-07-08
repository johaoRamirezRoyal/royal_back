<?php

namespace App\Services\GestionAcademica;

use App\Models\GestionAcademica\AsistenciaClase;
use App\Services\Service;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class AsistenciaClaseService extends Service
{
    public function agregarAsistenciaClase(array $data): array
    {
        try {
            $asistencia_clase = AsistenciaClase::create($data);
            if (!$asistencia_clase) {
                return [
                    'error' => true,
                    'message' => 'No se ha podido crear la asistencia',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Asistencia creada correctamente',
                'data' => $asistencia_clase->toArray()
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al crear la asistencia');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear la asistencia',
                'data' => []
            ];
        }
    }

    public function verAsistenciaClase(
        int|array $id_horario_clase,
        ?string $fecha = null,
        ?string $fecha_inicio = null,
        ?string $fecha_fin = null
    ): array {
        try {

            if (!is_null($fecha)) {
                Carbon::createFromFormat('Y-m-d', $fecha);
            }

            if (!is_null($fecha_inicio)) {
                Carbon::createFromFormat('Y-m-d', $fecha_inicio);
            }

            if (!is_null($fecha_fin)) {
                Carbon::createFromFormat('Y-m-d', $fecha_fin);

                if (is_null($fecha_inicio)) {
                    return [
                        'error' => true,
                        'message' => 'fecha_fin requiere fecha_inicio.',
                        'data' => []
                    ];
                }

                if ($fecha_fin < $fecha_inicio) {
                    return [
                        'error' => true,
                        'message' => 'fecha_fin debe ser mayor o igual a fecha_inicio.',
                        'data' => []
                    ];
                }
            }

            $asistencias = AsistenciaClase::with('horarioClase')
                ->when(is_array($id_horario_clase), function ($query) use ($id_horario_clase) {
                    $query->whereIn('id_horario_clase', $id_horario_clase);
                }, function ($query) use ($id_horario_clase) {
                    $query->where('id_horario_clase', $id_horario_clase);
                })
                ->when(filled($fecha), function ($query) use ($fecha) {
                    $query->whereDate('fecha', $fecha);
                })
                ->when(filled($fecha_inicio) && filled($fecha_fin), function ($query) use ($fecha_inicio, $fecha_fin) {
                    $query->whereBetween('fecha', [$fecha_inicio, $fecha_fin]);
                })
                ->get();

            if ($asistencias->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron asistencias',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Asistencias obtenidas.',
                'data' => $asistencias->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener las asistencias: ');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener las asistencias',
                'data' => []
            ];
        }
    }

    public function actualizarAsistenciaClase(array $data): array
    {
        DB::beginTransaction();

        try {
            $asistencia = AsistenciaClase::find($data['id']);

            if (!$asistencia) {
                DB::rollBack();

                return [
                    'error' => true,
                    'message' => 'No se encontró la asistencia.',
                    'data' => []
                ];
            }

            $asistencia->update([
                'estado'      => $data['estado'] ?? $asistencia->estado,
                'observacion' => $data['observacion'] ?? $asistencia->observacion,
            ]);

            DB::commit();

            return [
                'error' => false,
                'message' => 'Asistencia actualizada correctamente.',
                'data' => $asistencia->fresh()
            ];
        } catch (Exception $e) {
            DB::rollBack();

            $this->sendError($e, 'Error al actualizar la asistencia');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la asistencia.',
                'data' => []
            ];
        }
    }
}
