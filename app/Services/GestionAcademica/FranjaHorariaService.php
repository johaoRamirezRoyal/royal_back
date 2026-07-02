<?php

namespace App\Services\GestionAcademica;

use App\Models\GestionAcademica\FranjaHoraria;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\DB;

class FranjaHorariaService extends Service
{
    public function verFranjasHorarias(int $id_anio_escolar, ?int $id_dia_semana, ?string $tipo)
    {
        try {
            $franjaHoraria = FranjaHoraria::query()
                ->join(
                    'dias_semana',
                    'dias_semana.id',
                    '=',
                    'academico_franja_horaria.id_dia_semana'
                )
                ->select(
                    'dias_semana.nombre',
                    'dias_semana.abreviatura',
                    'dias_semana.orden as orden_dia',
                    'academico_franja_horaria.*',
                )->where('id_anio_escolar', $id_anio_escolar)
                ->when($id_dia_semana, function ($query) use ($id_dia_semana) {
                    $query->where('id_dia_semana', $id_dia_semana);
                })->when($tipo !== null, function ($query) use ($tipo) {
                    $query->where('tipo', $tipo);
                })
                ->orderBy('dias_semana.orden')
                ->orderBy('academico_franja_horaria.hora_inicio')
                ->get();

            if ($franjaHoraria->isEmpty()) {
                return [
                    'error' => true,
                    'message' => "No se encontró ninguna franja horaria",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se encontraron las franjas horarias",
                'data' => $franjaHoraria->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al obtener las franjas horarias");
            return [
                'error' => true,
                'message' => "Error en el servidor para las franjas horarias",
                'data' => []
            ];
        }
    }

    /**
     * Método para añadir una franja horaria.
     *
     * El array recibe:
     * - id_anio_escolar
     * - id_dia_semana
     * - hora_inicio
     * - hora_fin
     * - tipo (clase, receso o almuerzo)
     * - orden
     *
     * Validaciones:
     * - La hora de fin debe ser mayor que la de inicio.
     * - No puede existir otra franja con el mismo orden para el mismo año escolar y día.
     * - No puede solaparse con otra franja del mismo día.
     *
     * @param array $data
     * @return array
     */
    public function añadirFranjaHoraria(array $data): array
    {
        try {

            // Validar horas
            if (strtotime($data['hora_fin']) <= strtotime($data['hora_inicio'])) {
                return [
                    'error' => true,
                    'message' => 'La hora de fin debe ser mayor que la hora de inicio.',
                    'data' => []
                ];
            }

            // Validar orden
            $existeOrden = FranjaHoraria::where('id_anio_escolar', $data['id_anio_escolar'])
                ->where('id_dia_semana', $data['id_dia_semana'])
                ->where('orden', $data['orden'])
                ->exists();

            if ($existeOrden) {
                return [
                    'error' => true,
                    'message' => 'Ya existe una franja con ese orden para el día seleccionado.',
                    'data' => []
                ];
            }

            // Validar solapamiento de horarios
            $hayCruce = FranjaHoraria::where('id_anio_escolar', $data['id_anio_escolar'])
                ->where('id_dia_semana', $data['id_dia_semana'])
                ->where(function ($query) use ($data) {
                    $query
                        ->whereBetween('hora_inicio', [$data['hora_inicio'], $data['hora_fin']])
                        ->orWhereBetween('hora_fin', [$data['hora_inicio'], $data['hora_fin']])
                        ->orWhere(function ($q) use ($data) {
                            $q->where('hora_inicio', '<=', $data['hora_inicio'])
                                ->where('hora_fin', '>=', $data['hora_fin']);
                        });
                })
                ->exists();

            if ($hayCruce) {
                return [
                    'error' => true,
                    'message' => 'La franja horaria se cruza con otra ya existente.',
                    'data' => []
                ];
            }

            $franjaNueva = FranjaHoraria::create($data);

            return [
                'error' => false,
                'message' => 'Franja horaria creada correctamente.',
                'data' => $franjaNueva->toArray(),
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al crear la franja horaria');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear la franja horaria.',
                'data' => []
            ];
        }
    }


    /**
     * Método para actualizar las franjas horarias.
     *
     * Permite actualizar:
     * - Año escolar.
     * - Tipo.
     *
     * @param array $ids
     * @param int|null $id_anio_escolar
     * @param string|null $tipo
     * @return array
     */
    public function actualizarFranjaHoraria(
        array $ids,
        ?int $id_anio_escolar,
        ?string $tipo
    ): array {
        try {

            if (empty($ids)) {
                return [
                    'error' => true,
                    'message' => 'Debe enviar al menos una franja horaria.',
                    'data' => []
                ];
            }

            if ($id_anio_escolar === null && $tipo === null) {
                return [
                    'error' => true,
                    'message' => 'Debe enviar al menos un campo para actualizar.',
                    'data' => []
                ];
            }

            $dataActualizar = [];

            if ($id_anio_escolar !== null) {
                $dataActualizar['id_anio_escolar'] = $id_anio_escolar;
            }

            if ($tipo !== null) {
                $dataActualizar['tipo'] = $tipo;
            }

            $actualizados = FranjaHoraria::whereIn('id', $ids)
                ->update($dataActualizar);

            if ($actualizados === 0) {
                return [
                    'error' => true,
                    'message' => 'No se encontró ninguna franja horaria para actualizar.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se actualizaron {$actualizados} franja(s) horaria(s).",
                'data' => []
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al actualizar la franja horaria');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la franja horaria.',
                'data' => []
            ];
        }
    }

    /**
     * Actualiza el orden de las franjas horarias.
     * El array de $franjas debe ser -> [['id' => ?, 'orden' => ?], ['id' => ?, 'orden' => ?]]
     * @param array $franjas
     * @return array
     */
    public function actualizarOrdenFranjasHorarias(array $franjas): array
    {
        try {

            if (empty($franjas)) {
                return [
                    'error' => true,
                    'message' => 'No se recibieron franjas para actualizar.',
                    'data' => []
                ];
            }

            DB::beginTransaction();

            foreach ($franjas as $franja) {

                if (
                    !isset($franja['id']) ||
                    !isset($franja['orden'])
                ) {
                    DB::rollBack();

                    return [
                        'error' => true,
                        'message' => 'Cada franja debe contener id y orden.',
                        'data' => []
                    ];
                }

                FranjaHoraria::where('id', $franja['id'])
                    ->update([
                        'orden' => $franja['orden']
                    ]);
            }

            DB::commit();

            return [
                'error' => false,
                'message' => 'Orden de las franjas actualizado correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {

            DB::rollBack();

            $this->sendError($e, 'Error al actualizar el orden de las franjas horarias');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el orden.',
                'data' => []
            ];
        }
    }

    public function eliminarFranjaHoraria(array $ids): array
    {
        try {

            if (empty($ids)) {
                return [
                    'error' => true,
                    'message' => 'Debe enviar al menos una franja para eliminar.',
                    'data' => []
                ];
            }

            $eliminadas = FranjaHoraria::whereIn('id', $ids)->delete();

            if ($eliminadas === 0) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron franjas para eliminar.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se eliminaron {$eliminadas} franja(s) horaria(s).",
                'data' => []
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al eliminar las franjas horarias');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar las franjas horarias.',
                'data' => []
            ];
        }
    }

    /**
     * Actualiza la hora de inicio y/o fin de una franja horaria.
     *
     * @param int $id
     * @param string|null $hora_inicio Formato H:i:s
     * @param string|null $hora_fin Formato H:i:s
     * @return array
     */
    public function actualizarHorarioFranja(
        int $id,
        ?string $hora_inicio,
        ?string $hora_fin
    ): array {
        try {

            if ($hora_inicio === null && $hora_fin === null) {
                return [
                    'error' => true,
                    'message' => 'Debe indicar la hora de inicio y/o la hora de fin.',
                    'data' => []
                ];
            }

            $franja = FranjaHoraria::find($id);

            if (!$franja) {
                return [
                    'error' => true,
                    'message' => 'La franja horaria no existe.',
                    'data' => []
                ];
            }

            $nuevaHoraInicio = $hora_inicio ?? $franja->hora_inicio;
            $nuevaHoraFin = $hora_fin ?? $franja->hora_fin;

            // Validar rango horario
            if (strtotime($nuevaHoraFin) <= strtotime($nuevaHoraInicio)) {
                return [
                    'error' => true,
                    'message' => 'La hora de fin debe ser mayor que la hora de inicio.',
                    'data' => []
                ];
            }

            // Validar solapamiento con otras franjas
            $hayCruce = FranjaHoraria::where('id_anio_escolar', $franja->id_anio_escolar)
                ->where('id_dia_semana', $franja->id_dia_semana)
                ->where('id', '<>', $franja->id)
                ->where(function ($query) use ($nuevaHoraInicio, $nuevaHoraFin) {
                    $query
                        ->whereBetween('hora_inicio', [$nuevaHoraInicio, $nuevaHoraFin])
                        ->orWhereBetween('hora_fin', [$nuevaHoraInicio, $nuevaHoraFin])
                        ->orWhere(function ($q) use ($nuevaHoraInicio, $nuevaHoraFin) {
                            $q->where('hora_inicio', '<=', $nuevaHoraInicio)
                                ->where('hora_fin', '>=', $nuevaHoraFin);
                        });
                })
                ->exists();

            if ($hayCruce) {
                return [
                    'error' => true,
                    'message' => 'El horario se cruza con otra franja horaria.',
                    'data' => []
                ];
            }

            $franja->update([
                'hora_inicio' => $nuevaHoraInicio,
                'hora_fin' => $nuevaHoraFin,
            ]);

            return [
                'error' => false,
                'message' => 'Horario de la franja actualizado correctamente.',
                'data' => $franja->fresh()
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al actualizar el horario de la franja');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el horario.',
                'data' => []
            ];
        }
    }
}
