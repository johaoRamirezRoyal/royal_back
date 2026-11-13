<?php

namespace App\Services\AnioEscolar;

use App\Models\AnioEscolar\PeriodoAcademico;
use App\Services\Service;
use Exception;

class PeriodoAcademicoServices extends Service
{
    public function __construct(private AnioEscolarServices $anioEscolarServices) {}

    /**
     * Error de las fechas de un periodo, o null si son válidas. Pura (la usa el test
     * FechasPeriodoAcademicoTest): `$rango` es el del año escolar (`fecha_min`/`fecha_max`,
     * según el calendario A o B) y `$otros` los demás periodos de ese año, para no solaparse.
     *
     * @param  array{fecha_min: string, fecha_max: string}  $rango
     * @param  iterable<array{nombre: ?string, fecha_inicio: string, fecha_fin: string}>  $otros
     */
    public static function errorDeFechas(string $inicio, string $fin, array $rango, iterable $otros): ?string
    {
        $inicio = substr($inicio, 0, 10);
        $fin = substr($fin, 0, 10);

        if ($fin <= $inicio) {
            return 'La fecha de finalización debe ser posterior a la de inicio';
        }
        if ($inicio < $rango['fecha_min'] || $fin > $rango['fecha_max']) {
            return "El periodo debe estar dentro del año escolar: del {$rango['fecha_min']} al {$rango['fecha_max']}";
        }
        foreach ($otros as $o) {
            // Se solapan si cada uno empieza antes de que el otro termine (los extremos cuentan).
            if ($inicio <= substr($o['fecha_fin'], 0, 10) && $fin >= substr($o['fecha_inicio'], 0, 10)) {
                $nombre = $o['nombre'] ?: 'otro periodo';

                return "Las fechas se cruzan con {$nombre} (del " . substr($o['fecha_inicio'], 0, 10) . ' al ' . substr($o['fecha_fin'], 0, 10) . ')';
            }
        }

        return null;
    }

    /**
     * Resuelve el año escolar por la fecha de inicio y valida las fechas contra su rango y los
     * demás periodos del año. Devuelve [año, null] o [null, mensaje de error].
     */
    private function validarFechas(string $inicio, string $fin, ?int $excluirId = null): array
    {
        $anio = $this->anioEscolarServices->obtenerAnioEscolarPorFecha($inicio);
        if (! $anio) {
            return [null, 'No existe un año escolar registrado para la fecha de inicio indicada'];
        }

        $otros = PeriodoAcademico::where('id_anio_escolar', $anio->id)
            ->when($excluirId, fn ($q) => $q->where('id', '!=', $excluirId))
            ->get(['nombre', 'fecha_inicio', 'fecha_fin'])
            ->map(fn ($p) => ['nombre' => $p->nombre, 'fecha_inicio' => (string) $p->fecha_inicio, 'fecha_fin' => (string) $p->fecha_fin]);

        $error = self::errorDeFechas($inicio, $fin, $this->anioEscolarServices->rangoDeAnioEscolar($anio), $otros);

        return $error ? [null, $error] : [$anio, null];
    }

    public function agregarPeriodoAcademico(array $data): array
    {
        try {
            [$anioEscolar, $error] = $this->validarFechas($data['fecha_inicio'], $data['fecha_fin']);
            if ($error) {
                return ['error' => true, 'message' => $error, 'data' => []];
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

            // Con fechas nuevas se revalidan (rango del año y cruces) y el año escolar se vuelve a
            // resolver por la fecha de inicio: un periodo movido de año cambia de año.
            if (isset($data['fecha_inicio']) || isset($data['fecha_fin'])) {
                [$anio, $error] = $this->validarFechas(
                    (string) ($data['fecha_inicio'] ?? $periodo_academico->fecha_inicio),
                    (string) ($data['fecha_fin'] ?? $periodo_academico->fecha_fin),
                    $periodo_academico->id,
                );
                if ($error) {
                    return ['error' => true, 'message' => $error, 'data' => []];
                }
                $data['id_anio_escolar'] = $anio->id;
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

            // Los referentes y sus notas apuntan al periodo sin FK (periodo_academico es MyISAM):
            // borrarlo los dejaría huérfanos. Las notas cuelgan de los referentes.
            if (\App\Models\GestionAcademica\Referente::whereIn('id_periodo_academico', $ids_periodo_academico)->exists()) {
                return [
                    'error' => true,
                    'message' => 'No se puede eliminar un periodo que ya tiene referentes o notas registradas. Desactívalo en su lugar.',
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
