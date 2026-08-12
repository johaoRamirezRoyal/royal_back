<?php

namespace App\Services\AnioEscolar;

use App\Models\AnioEscolar\PeriodoAcademico;
use App\Services\Service;
use Exception;

class PeriodoAcademicoServices extends Service
{
    public function __construct(private AnioEscolarServices $anioEscolarServices) {}

    public function agregarPeriodoAcademico(array $data): array
    {
        try {
            $anioEscolar = $this->anioEscolarServices->obtenerAnioEscolarPorFecha($data['fecha_inicio']);

            if (!$anioEscolar) {
                return [
                    'error' => true,
                    'message' => 'No existe un año escolar registrado para la fecha de inicio indicada',
                    'data' => []
                ];
            }

            if ($data['fecha_fin'] > "{$anioEscolar->anio_fin}-06-30") {
                return [
                    'error' => true,
                    'message' => "La fecha de finalización debe estar dentro del año escolar {$anioEscolar->anio_inicio}-{$anioEscolar->anio_fin} (hasta el 30 de junio de {$anioEscolar->anio_fin})",
                    'data' => []
                ];
            }

            $data['id_anio_escolar'] = $anioEscolar->id;

            $periodo_academico = PeriodoAcademico::create($data);

            if (!$periodo_academico) {
                return [
                    'error' => true,
                    'message' => "No se pudo crear el nuevo periodo academico",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se ha creado el nuevo periodo academico con exito",
                'data' => $periodo_academico->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al agregar el nuevo periodo academico");
            return [
                'error' => true,
                'message' => "Error en el servidor al agregar el nuevo periodo académico",
                'data' => $data
            ];
        }
    }

    public function obtenerPeriodoAcademicoPorId(int $id_anio_escolar): array
    {
        try {
            $periodo_academico = PeriodoAcademico::where('id_anio_escolar', $id_anio_escolar)->first();

            if (!$periodo_academico) {
                return [
                    'error' => true,
                    'message' => 'No se encontró el periodo académico',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Periodo académico obtenido',
                'data' => $periodo_academico->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener el periodo académico');
            return [
                'error' => true,
                'message' => "Error en el servidor al obtener el periodo academico",
                'data' => []
            ];
        }
    }

    public function obtenerPeriodoAcademico(?int $id_anio_escolar = null, ?bool $estado = null): array
    {
        try {
            $periodos_academico = PeriodoAcademico::with('anioEscolar:id,anio_inicio,anio_fin')
                ->when(
                    $estado !== null,
                    fn($query) => $query->where('activo', $estado ? 1 : 0)
                )
                ->when($id_anio_escolar, fn($query) => $query->where('id_anio_escolar', $id_anio_escolar))
                ->get();

            if ($periodos_academico->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron periodos académicos',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Periodos académicos obtenidos',
                'data' => $periodos_academico->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener los periodos académicos');
            return [
                'error' => true,
                'message' => "Error en el servicor al obtener los periodos académicos",
                'data' => []
            ];
        }
    }

    public function editarPeriodoAcademico(int $id_periodo, array $data): array
    {
        try {
            $periodo_academico = PeriodoAcademico::find($id_periodo);

            if (!$periodo_academico) {
                return [
                    'error' => true,
                    'message' => 'No se ha encontrado el periodo académico con el ID: ' . $id_periodo,
                    'data' => []
                ];
            }

            $periodoAcademicoUpdate = $periodo_academico->update($data);

            if (!$periodoAcademicoUpdate) {
                return [
                    'error' => true,
                    'message' => "No se actualizó el periodo académico",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se actualizó el periodo académico",
                'data' => $periodo_academico->refresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al actualizar el periodo academico");
            return [
                'error' => true,
                'message' => "Error al actualizar el periodo academico",
                'data' => []
            ];
        }
    }

    public function eliminarPeriodoAcademico(array $ids_periodo_academico): array
    {
        try {
            $periodos_academicos = PeriodoAcademico::whereIn('id', $ids_periodo_academico)->get();

            if ($periodos_academicos->isEmpty()) {
                return [
                    'error' => true,
                    'message' => "No se encontraron periodos académicos con esos IDs para eliminar: " . implode(', ', $ids_periodo_academico),
                    'data' => []
                ];
            }

            $eliminados = PeriodoAcademico::whereIn('id', $ids_periodo_academico)->delete();

            if ($eliminados === 0) {
                return [
                    'error' => true,
                    'message' => "No se lograron eliminar los periodos academicos",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se han eliminado los periodos académicos",
                'data' => ['eliminados' => $eliminados]
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al eliminar los periodos academicos");
            return [
                'error' => true,
                'message' => "Error al eliminar los periodos academicos en el servidor",
                'data' => []
            ];
        }
    }

    public function desactivarPeriodosAcademicos(?array $ids_periodos_academicos, int $estado = 0): array {
        try {
            $periodos_academicos_update = PeriodoAcademico::query()
                ->when(
                    !empty($ids_periodos_academicos),
                    fn($query) => $query->whereIn('id', $ids_periodos_academicos)
                )
                ->update([
                    'activo' => $estado
                ]);

            if ($periodos_academicos_update === 0) {
                return [
                    'error' => true,
                    'message' => "No se actualizó ningún periodo academico",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se actualizó el periodo academico",
                'data' => []
            ];
        }catch(Exception $e){
            $this->sendError($e, "Error al actualizar el estado del periodo academico");
            return [
                'error' => true,
                'message' => "Error en el servidor al actualizar el estado del periodo academico",
                'data' => []
            ];
        }
    }

    /**
     * Desactiva los periodos académicos activos cuya fecha_fin ya pasó (estrictamente
     * antes de hoy, así que el periodo sigue activo durante todo su último día).
     * Pensado para correr diario vía comando programado (ver DesactivarPeriodosVencidosCommand).
     */
    public function desactivarPeriodosVencidos(): array
    {
        try {
            $desactivados = PeriodoAcademico::where('activo', true)
                ->whereDate('fecha_fin', '<', now()->toDateString())
                ->update(['activo' => false]);

            return [
                'error' => false,
                'message' => "Se desactivaron {$desactivados} periodo(s) académico(s) vencido(s)",
                'data' => ['desactivados' => $desactivados]
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al desactivar los periodos académicos vencidos");
            return [
                'error' => true,
                'message' => "Error en el servidor al desactivar los periodos académicos vencidos",
                'data' => []
            ];
        }
    }
}
