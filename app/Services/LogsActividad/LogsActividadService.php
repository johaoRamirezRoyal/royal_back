<?php

namespace App\Services\LogsActividad;

use App\Models\LogActividad;
use App\Services\Service;
use Exception;

class LogsActividadService extends Service
{
    /**
     * Lista paginada de logs de actividad, más recientes primero.
     * @param array{id_user?: int, metodo?: string, ruta?: string, fecha_desde?: string, fecha_hasta?: string} $filtros
     */
    public function listar(array $filtros = [], ?int $perpage = 20): array
    {
        try {
            $logs = LogActividad::query()
                ->with('usuario:id_user,nombre,apellido')
                ->when(
                    !empty($filtros['id_user']),
                    fn ($q) => $q->where('id_user', $filtros['id_user'])
                )
                ->when(
                    !empty($filtros['metodo']),
                    fn ($q) => $q->where('metodo', strtoupper($filtros['metodo']))
                )
                ->when(
                    !empty($filtros['ruta']),
                    fn ($q) => $q->where('ruta', 'LIKE', "%{$filtros['ruta']}%")
                )
                ->when(
                    !empty($filtros['fecha_desde']),
                    fn ($q) => $q->whereDate('fechareg', '>=', $filtros['fecha_desde'])
                )
                ->when(
                    !empty($filtros['fecha_hasta']),
                    fn ($q) => $q->whereDate('fechareg', '<=', $filtros['fecha_hasta'])
                )
                ->orderByDesc('fechareg')
                ->paginate($perpage ?: 20);

            return [
                'error' => false,
                'message' => 'Logs de actividad obtenidos',
                'data' => $logs,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener los logs de actividad');
            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener los logs de actividad',
                'data' => [],
            ];
        }
    }
}
