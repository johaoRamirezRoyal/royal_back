<?php

namespace App\Services\AdminManagement;

use App\Models\LogDominio;
use App\Services\Service;
use Exception;

class LogDominioService extends Service
{
    /**
     * Lista paginada de peticiones por dominio, más recientes primero.
     * @param array{dominio?: string, metodo?: string, ruta?: string, fecha_desde?: string, fecha_hasta?: string} $filtros
     */
    public function listar(array $filtros = [], ?int $perpage = 20): array
    {
        try {
            $logs = LogDominio::query()
                ->with('usuario:id_user,nombre,apellido')
                // Exacto, no LIKE: el filtro ahora es un select poblado con
                // dominiosDisponibles(), no texto libre (ver LogsDominioToolbar.parts.tsx).
                ->when(
                    !empty($filtros['dominio']),
                    fn ($q) => $q->where('dominio', $filtros['dominio'])
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
                'message' => 'Logs por dominio obtenidos',
                'data' => $logs,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener los logs por dominio');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener los logs por dominio',
                'data' => [],
            ];
        }
    }

    /**
     * Borra los logs por dominio más viejos que el número de días de retención indicado
     * — mismo criterio que LogsActividadService::purgarLogsAntiguos, pero sobre
     * `logs_dominio` (conexión `admin_management`, ver LogDominio::$connection).
     */
    public function purgarLogsAntiguos(int $diasRetencion = 90): array
    {
        try {
            $eliminados = LogDominio::where('fechareg', '<', now()->subDays($diasRetencion))->delete();

            return [
                'error' => false,
                'message' => "Se eliminaron {$eliminados} log(s) por dominio con más de {$diasRetencion} días",
                'data' => ['eliminados' => $eliminados],
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al purgar los logs por dominio antiguos');

            return [
                'error' => true,
                'message' => 'Error en el servidor al purgar los logs por dominio antiguos',
                'data' => [],
            ];
        }
    }

    /**
     * Dominios distintos que aparecen en el log — para poblar un select de filtro en vez
     * de un texto libre. A propósito no se saca de `marcas_dominio`: acá se registra el
     * dominio de CUALQUIER usuario autenticado, tenga o no una marca configurada.
     */
    public function dominiosDisponibles(): array
    {
        try {
            $dominios = LogDominio::query()
                ->whereNotNull('dominio')
                ->distinct()
                ->orderBy('dominio')
                ->pluck('dominio');

            return ['error' => false, 'message' => 'Dominios obtenidos', 'data' => $dominios];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener los dominios disponibles');

            return ['error' => true, 'message' => 'Error en el servidor al obtener los dominios', 'data' => []];
        }
    }
}
