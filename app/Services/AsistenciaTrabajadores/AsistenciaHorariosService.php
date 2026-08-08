<?php

namespace App\Services\AsistenciaTrabajadores;

use App\Models\AsistenciaGestion\AsistenciaHorario;
use App\Models\AsistenciaGestion\AsistenciaPuntualidadBanda;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\DB;

class AsistenciaHorariosService extends Service
{
    public function listarHorarios(): array
    {
        try {
            $horarios = AsistenciaHorario::with('bandas')->orderBy('nombre')->get();

            return [
                'error' => false,
                'message' => 'Horarios obtenidos correctamente',
                'data' => $horarios,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener los horarios');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener los horarios',
                'data' => [],
            ];
        }
    }

    public function crearHorario(array $datos): array
    {
        try {
            $conflicto = $this->existeHorarioParaGrupo($datos['grupo_id'] ?? null, $datos['dias_habiles']);
            if ($conflicto) {
                return [
                    'error' => true,
                    'message' => 'Ya existe un horario activo para ese grupo que se superpone en al menos un día',
                    'data' => null,
                ];
            }

            $horario = AsistenciaHorario::create($datos);

            return [
                'error' => false,
                'message' => 'Horario creado correctamente',
                'data' => $horario,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear el horario');
            return [
                'error' => true,
                'message' => 'Error en el servidor al crear el horario',
                'data' => null,
            ];
        }
    }

    public function actualizarHorario(int $id, array $datos): array
    {
        try {
            $horario = AsistenciaHorario::find($id);

            if (!$horario) {
                return [
                    'error' => true,
                    'message' => 'El horario no existe',
                    'data' => null,
                ];
            }

            if (array_key_exists('grupo_id', $datos) || array_key_exists('dias_habiles', $datos)) {
                $grupoId = array_key_exists('grupo_id', $datos) ? $datos['grupo_id'] : $horario->grupo_id;
                $diasHabiles = $datos['dias_habiles'] ?? $horario->dias_habiles;

                $conflicto = $this->existeHorarioParaGrupo($grupoId, $diasHabiles, excluirId: $id);
                if ($conflicto) {
                    return [
                        'error' => true,
                        'message' => 'Ya existe un horario activo para ese grupo que se superpone en al menos un día',
                        'data' => null,
                    ];
                }
            }

            $horario->update($datos);

            return [
                'error' => false,
                'message' => 'Horario actualizado correctamente',
                'data' => $horario,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el horario');
            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el horario',
                'data' => null,
            ];
        }
    }

    public function eliminarHorario(int $id): array
    {
        try {
            $horario = AsistenciaHorario::find($id);

            if (!$horario) {
                return [
                    'error' => true,
                    'message' => 'El horario no existe',
                    'data' => null,
                ];
            }

            $horario->delete();

            return [
                'error' => false,
                'message' => 'Horario eliminado correctamente',
                'data' => null,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el horario');
            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar el horario',
                'data' => null,
            ];
        }
    }

    public function crearBanda(int $idHorario, array $datos): array
    {
        try {
            if (!AsistenciaHorario::where('id', $idHorario)->exists()) {
                return [
                    'error' => true,
                    'message' => 'El horario no existe',
                    'data' => null,
                ];
            }

            $banda = AsistenciaPuntualidadBanda::create([...$datos, 'id_horario' => $idHorario]);

            return [
                'error' => false,
                'message' => 'Banda de puntualidad creada correctamente',
                'data' => $banda,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear la banda de puntualidad');
            return [
                'error' => true,
                'message' => 'Error en el servidor al crear la banda de puntualidad',
                'data' => null,
            ];
        }
    }

    public function actualizarBanda(int $id, array $datos): array
    {
        try {
            $banda = AsistenciaPuntualidadBanda::find($id);

            if (!$banda) {
                return [
                    'error' => true,
                    'message' => 'La banda de puntualidad no existe',
                    'data' => null,
                ];
            }

            $banda->update($datos);

            return [
                'error' => false,
                'message' => 'Banda de puntualidad actualizada correctamente',
                'data' => $banda,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar la banda de puntualidad');
            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la banda de puntualidad',
                'data' => null,
            ];
        }
    }

    public function eliminarBanda(int $id): array
    {
        try {
            $banda = AsistenciaPuntualidadBanda::find($id);

            if (!$banda) {
                return [
                    'error' => true,
                    'message' => 'La banda de puntualidad no existe',
                    'data' => null,
                ];
            }

            $banda->delete();

            return [
                'error' => false,
                'message' => 'Banda de puntualidad eliminada correctamente',
                'data' => null,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar la banda de puntualidad');
            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar la banda de puntualidad',
                'data' => null,
            ];
        }
    }

    /** Sin índice único sobre (id_horario, orden) en esta tabla, un solo pase de updates alcanza. */
    public function reordenarBandas(array $bandas): array
    {
        try {
            foreach ($bandas as $banda) {
                if (!isset($banda['id']) || !isset($banda['orden'])) {
                    return [
                        'error' => true,
                        'message' => 'Cada banda debe contener id y orden',
                        'data' => null,
                    ];
                }
            }

            DB::transaction(function () use ($bandas) {
                foreach ($bandas as $banda) {
                    AsistenciaPuntualidadBanda::where('id', $banda['id'])->update(['orden' => $banda['orden']]);
                }
            });

            return [
                'error' => false,
                'message' => 'Orden de las bandas actualizado correctamente',
                'data' => null,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al reordenar las bandas de puntualidad');
            return [
                'error' => true,
                'message' => 'Error en el servidor al reordenar las bandas de puntualidad',
                'data' => null,
            ];
        }
    }

    /**
     * Ya no basta con "mismo grupo" para ser conflicto: dos horarios activos del mismo
     * grupo pueden coexistir si cubren días distintos (ej. Lun-Vie vs Sábado). Solo hay
     * conflicto si además comparten al menos un día en `dias_habiles`.
     */
    private function existeHorarioParaGrupo(?int $grupoId, array $diasHabiles, ?int $excluirId = null): bool
    {
        return AsistenciaHorario::where('grupo_id', $grupoId)
            ->where('activo', true)
            ->when($excluirId, fn ($q) => $q->where('id', '!=', $excluirId))
            ->get()
            ->contains(fn (AsistenciaHorario $horario) => array_intersect($horario->dias_habiles ?? [], $diasHabiles) !== []);
    }
}
