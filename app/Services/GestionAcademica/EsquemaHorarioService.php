<?php

namespace App\Services\GestionAcademica;

use App\Models\GestionAcademica\EsquemaHorario;
use App\Services\Service;
use Exception;

class EsquemaHorarioService extends Service
{
    public function listarEsquemas(?int $id_anio_escolar = null, ?int $id_nivel = null): array
    {
        try {
            $esquemas = EsquemaHorario::query()
                ->with(['nivel:id,nombre'])
                ->when($id_anio_escolar, function ($query) use ($id_anio_escolar) {
                    $query->where('id_anio_escolar', $id_anio_escolar);
                })
                ->when($id_nivel, function ($query) use ($id_nivel) {
                    $query->where('id_nivel', $id_nivel);
                })
                ->orderBy('id_nivel')
                ->get();

            if ($esquemas->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontró ningún esquema de horario',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Se encontraron los esquemas de horario',
                'data' => $esquemas->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener los esquemas de horario');
            return [
                'error' => true,
                'message' => 'Error en el servidor para los esquemas de horario',
                'data' => []
            ];
        }
    }

    public function crearEsquema(array $data): array
    {
        try {
            $existe = EsquemaHorario::where('id_nivel', $data['id_nivel'])
                ->where('id_anio_escolar', $data['id_anio_escolar'])
                ->exists();

            if ($existe) {
                return [
                    'error' => true,
                    'message' => 'Ya existe un esquema de horario para ese nivel en ese año escolar.',
                    'data' => []
                ];
            }

            $esquema = EsquemaHorario::create($data);

            return [
                'error' => false,
                'message' => 'Esquema de horario creado correctamente.',
                'data' => $esquema->load('nivel')->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear el esquema de horario');
            return [
                'error' => true,
                'message' => 'Error en el servidor al crear el esquema de horario.',
                'data' => []
            ];
        }
    }

    public function actualizarEsquema(int $id, array $data): array
    {
        try {
            $esquema = EsquemaHorario::find($id);

            if (!$esquema) {
                return [
                    'error' => true,
                    'message' => 'El esquema de horario no existe.',
                    'data' => []
                ];
            }

            $esquema->update($data);

            return [
                'error' => false,
                'message' => 'Esquema de horario actualizado correctamente.',
                'data' => $esquema->fresh('nivel')->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el esquema de horario');
            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el esquema de horario.',
                'data' => []
            ];
        }
    }

    public function eliminarEsquema(array $ids): array
    {
        try {
            if (empty($ids)) {
                return [
                    'error' => true,
                    'message' => 'Debe enviar al menos un esquema para eliminar.',
                    'data' => []
                ];
            }

            $tieneFranjas = EsquemaHorario::whereIn('id', $ids)->whereHas('franjas')->exists();

            if ($tieneFranjas) {
                return [
                    'error' => true,
                    'message' => 'No se puede eliminar un esquema con franjas horarias asociadas. Elimina primero sus franjas.',
                    'data' => []
                ];
            }

            $eliminados = EsquemaHorario::whereIn('id', $ids)->delete();

            if ($eliminados === 0) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron esquemas para eliminar.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se eliminaron {$eliminados} esquema(s) de horario.",
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar los esquemas de horario');
            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar los esquemas de horario.',
                'data' => []
            ];
        }
    }
}
