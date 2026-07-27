<?php

namespace App\Services\AsistenciasTrabajador;

use App\Models\AsistenciasTrabajador\AsistenciaTrabajador as ModelsAsistenciaTrabajador;
use App\Services\Service;
use Exception;

class AsistenciaTrabajadorService extends Service
{
    public function registrarLlegada(int $id_user, string $fecha, string $hora): array
    {
        try {
            $yaRegistrada = ModelsAsistenciaTrabajador::where('id_user', $id_user)
                ->where('fecha_asistencia', $fecha)
                ->exists();

            if ($yaRegistrada) {
                return [
                    'error' => false,
                    'message' => 'El trabajador ya tiene una llegada registrada hoy',
                    'data' => []
                ];
            }

            $asistencia = ModelsAsistenciaTrabajador::create([
                'id_user' => $id_user,
                'fecha_asistencia' => $fecha,
                'hora_asistencia' => $hora,
            ]);

            if (!$asistencia) {
                return [
                    'error' => true,
                    'message' => 'No se ha podido registrar la llegada del trabajador',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Llegada de trabajador registrada correctamente',
                'data' => $asistencia->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al registrar la llegada del trabajador");
            return [
                'error' => true,
                'message' => "Error al registrar la llegada del trabajador",
                'data' => []
            ];
        }
    }

    public function obtenerLlegadas(?string $fecha_inicio = null, ?string $fecha_fin = null, ?int $id_user = null): array
    {
        try {
            $llegadas = ModelsAsistenciaTrabajador::with([
                'trabajador' => fn($q) => $q->select('id_user', 'nombre', 'apellido', 'perfil')
                                            ->with('perfilRelacion:id_perfil,nombre'),
            ])
                ->when($fecha_inicio !== null, fn($q) => $q->whereDate('fecha_asistencia', '>=', $fecha_inicio))
                ->when($fecha_fin !== null, fn($q) => $q->whereDate('fecha_asistencia', '<=', $fecha_fin))
                ->when($id_user !== null, fn($q) => $q->where('id_user', $id_user))
                ->orderByDesc('fecha_asistencia')
                ->orderByDesc('hora_asistencia')
                ->get();

            return [
                'error' => false,
                'message' => 'Llegadas de trabajadores obtenidas',
                'data' => [
                    'total' => $llegadas->count(),
                    'registros' => $llegadas
                ]
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al obtener las llegadas de trabajadores");
            return [
                'error' => true,
                'message' => "Error en el servidor al obtener las llegadas de trabajadores",
                'data' => []
            ];
        }
    }

    public function eliminarLlegadas(array $ids): array
    {
        try {
            $registros = ModelsAsistenciaTrabajador::whereIn('id', $ids)->get();

            if ($registros->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron llegadas para los IDs: ' . implode(', ', $ids),
                    'data' => []
                ];
            }

            $eliminados = ModelsAsistenciaTrabajador::whereIn('id', $ids)->delete();

            if ($eliminados === 0) {
                return [
                    'error' => true,
                    'message' => 'No se eliminaron los registros de llegada',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Se ha eliminado la llegada del trabajador',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al eliminar la llegada del trabajador");
            return [
                'error' => true,
                'message' => "Error en el servidor al tratar de eliminar la llegada del trabajador",
                'data' => []
            ];
        }
    }
}
