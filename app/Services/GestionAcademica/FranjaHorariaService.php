<?php

namespace App\Services\GestionAcademica;

use App\Models\Areas\Cursos;
use App\Models\GestionAcademica\CargaAcademica;
use App\Models\GestionAcademica\EsquemaHorario;
use App\Models\GestionAcademica\FranjaHoraria;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\DB;

class FranjaHorariaService extends Service
{
    /**
     * Resuelve el id_esquema a consultar: directo si se envía, o derivado del nivel del
     * curso + año escolar (usado por la página de autoservicio del docente y por la
     * pestaña "Horario", que solo conocen el curso, no el esquema).
     */
    private function resolverIdEsquema(?int $id_esquema, ?int $id_curso, ?int $id_anio_escolar): ?int
    {
        if ($id_esquema) {
            return $id_esquema;
        }

        if (!$id_curso || !$id_anio_escolar) {
            return null;
        }

        $curso = Cursos::find($id_curso);

        if (!$curso || !$curso->id_nivel) {
            return null;
        }

        return EsquemaHorario::where('id_nivel', $curso->id_nivel)
            ->where('id_anio_escolar', $id_anio_escolar)
            ->value('id');
    }

    public function verFranjasHorarias(
        ?int $id_esquema,
        ?int $id_curso = null,
        ?int $id_anio_escolar = null,
        ?int $id_dia_semana = null,
        ?bool $disponible = null,
        ?int $id_carga_academica = null
    ) {
        try {
            $idEsquemaResuelto = $this->resolverIdEsquema($id_esquema, $id_curso, $id_anio_escolar);

            if (!$idEsquemaResuelto) {
                return [
                    'error' => true,
                    'message' => 'No se encontró ninguna franja horaria',
                    'data' => []
                ];
            }

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
                )->where('id_esquema', $idEsquemaResuelto)
                ->when($id_dia_semana, function ($query) use ($id_dia_semana) {
                    $query->where('id_dia_semana', $id_dia_semana);
                })->when($disponible, function ($query) use ($id_carga_academica) {

                    // Sin id_carga_academica: excluye franjas ocupadas por cualquier clase.
                    // Con id_carga_academica: excluye las franjas donde YA hay clase para
                    // ese CURSO (con cualquier docente) o para ese DOCENTE (en cualquier
                    // curso), ya que ninguno de los dos puede estar en dos clases a la vez.
                    // Ocupado por otro curso con otro docente sigue apareciendo como libre.
                    $carga = $id_carga_academica
                        ? CargaAcademica::with('docenteAsignatura')->find($id_carga_academica)
                        : null;

                    $query->whereDoesntHave('horarioClase', function ($q) use ($carga) {
                        $q->when($carga, function ($q) use ($carga) {
                            $q->whereHas('cargaAcademica', function ($q2) use ($carga) {
                                $q2->where('id_curso', $carga->id_curso)
                                    ->orWhereHas('docenteAsignatura', function ($q3) use ($carga) {
                                        $q3->where('id_docente', $carga->docenteAsignatura?->id_docente);
                                    });
                            });
                        });
                    });
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
     * - id_esquema
     * - id_dia_semana
     * - hora_inicio
     * - hora_fin
     * - orden
     *
     * Validaciones:
     * - La hora de fin debe ser mayor que la de inicio.
     * - No puede existir otra franja con el mismo orden para el mismo esquema y día.
     * - No puede solaparse con otra franja del mismo día.
     *
     * @param array $data
     * @return array
     */
    public function añadirFranjaHoraria(array $data): array
    {
        try {

            $esquema = EsquemaHorario::find($data['id_esquema']);

            if (!$esquema) {
                return [
                    'error' => true,
                    'message' => 'El esquema de horario no existe.',
                    'data' => []
                ];
            }

            // id_anio_escolar queda deprecado (ver 2026_08_21_130100_add_id_esquema_...)
            // pero la columna sigue siendo NOT NULL en la tabla real — se deriva del
            // esquema para no requerir una migración destructiva sobre esa columna.
            $data['id_anio_escolar'] = $esquema->id_anio_escolar;

            // Validar horas
            if (strtotime($data['hora_fin']) <= strtotime($data['hora_inicio'])) {
                return [
                    'error' => true,
                    'message' => 'La hora de fin debe ser mayor que la hora de inicio.',
                    'data' => []
                ];
            }

            // Validar orden
            $existeOrden = FranjaHoraria::where('id_esquema', $data['id_esquema'])
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
            $hayCruce = FranjaHoraria::where('id_esquema', $data['id_esquema'])
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
     * - Esquema de horario.
     *
     * @param array $ids
     * @param int|null $id_esquema
     * @return array
     */
    public function actualizarFranjaHoraria(
        array $ids,
        ?int $id_esquema
    ): array {
        try {

            if (empty($ids)) {
                return [
                    'error' => true,
                    'message' => 'Debe enviar al menos una franja horaria.',
                    'data' => []
                ];
            }

            if ($id_esquema === null) {
                return [
                    'error' => true,
                    'message' => 'Debe enviar al menos un campo para actualizar.',
                    'data' => []
                ];
            }

            $dataActualizar = [
                'id_esquema' => $id_esquema,
            ];

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

            foreach ($franjas as $franja) {
                if (!isset($franja['id']) || !isset($franja['orden'])) {
                    return [
                        'error' => true,
                        'message' => 'Cada franja debe contener id y orden.',
                        'data' => []
                    ];
                }
            }

            DB::beginTransaction();

            // Dos pasadas: primero a valores temporales negativos (únicos por id) para
            // no chocar con el uq_franja_horaria (id_anio_escolar, id_dia_semana, orden)
            // mientras otras filas del mismo grupo aún tienen su orden viejo.
            foreach ($franjas as $franja) {
                FranjaHoraria::where('id', $franja['id'])
                    ->update(['orden' => -$franja['id']]);
            }

            foreach ($franjas as $franja) {
                FranjaHoraria::where('id', $franja['id'])
                    ->update(['orden' => $franja['orden']]);
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
