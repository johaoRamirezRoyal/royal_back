<?php

namespace App\Services\LogsActividad;

use App\Models\LogActividad;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\Http;

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

            $this->resolverPaises($logs->getCollection());

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

    /**
     * Resuelve y cachea en BD el país de la IP de cada log que aún no lo tenga, agrupando
     * por IP para no repetir la consulta externa cuando varios logs comparten origen.
     * Se hace aquí (al listar) y no en el middleware para no meterle latencia de red a
     * cada petición auditada.
     */
    private function resolverPaises($logs): void
    {
        $pendientes = $logs->filter(fn ($log) => $log->pais === null && !empty($log->ip));

        foreach ($pendientes->groupBy('ip') as $ip => $grupo) {
            $pais = $this->consultarPais($ip);

            if ($pais === null) {
                continue; // fallo transitorio: se reintenta en la próxima consulta
            }

            LogActividad::whereIn('id', $grupo->pluck('id'))->update(['pais' => $pais]);
            $grupo->each(fn ($log) => $log->pais = $pais);
        }
    }

    // ponytail: geolocalización por API pública gratuita (sin key), suficiente para "país
    // aproximado" en un panel de auditoría interno. Si el volumen de IPs distintas crece,
    // cambiar a una base local (p. ej. MaxMind GeoLite2) para no depender de un tercero.
    private function consultarPais(string $ip): ?string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return 'Red local';
        }

        try {
            $respuesta = Http::timeout(2)->get("https://ipapi.co/{$ip}/json/");
            return $respuesta->ok() ? ($respuesta->json('country_name') ?? 'Desconocido') : 'Desconocido';
        } catch (\Throwable $e) {
            return null;
        }
    }
}
