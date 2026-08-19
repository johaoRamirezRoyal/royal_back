<?php

namespace App\Services\GestionAcademica;

use App\Models\GestionAcademica\AreaAcademica as AreaAcademicaModel;
use App\Services\Service;
use Exception;

class AreaAcademicaService extends Service
{
    public function mostrarAreasFiltradas(?string $nombre, ?int $estado)
    {
        try {
            $areas = AreaAcademicaModel::query()
                ->when($nombre, function ($query) use ($nombre) {
                    $query->where('nombre', 'LIKE', "%$nombre%");
                })
                ->when($estado !== null, function ($query) use ($estado) {
                    $query->where('activo', $estado);
                })
                ->withCount('asignaturas')
                ->get();

            if ($areas->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron áreas académicas',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Áreas académicas obtenidas.',
                'data' => $areas->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al mostrar áreas académicas filtradas.');
            return [
                'error' => true,
                'message' => 'Error en el servidor.',
                'data' => []
            ];
        }
    }

    public function obtenerPorId(int $id): array
    {
        try {
            $item = AreaAcademicaModel::withCount('asignaturas')->find($id);

            if (!$item) {
                return ['error' => true, 'message' => "Área académica con ID $id no encontrada.", 'data' => []];
            }

            return ['error' => false, 'message' => 'Área académica obtenida.', 'data' => $item->toArray()];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener área académica.');
            return ['error' => true, 'message' => 'Error en el servidor.', 'data' => []];
        }
    }

    public function crear(array $data): array
    {
        try {
            $item = AreaAcademicaModel::create($data);

            return [
                'error' => false,
                'message' => 'Área académica creada.',
                'data' => $item->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear área académica.');

            return [
                'error' => true,
                'message' => 'Error en el servidor.',
                'data' => []
            ];
        }
    }

    public function actualizar(int $id, array $data): array
    {
        try {
            $item = AreaAcademicaModel::find($id);

            if (!$item) {
                return [
                    'error' => true,
                    'message' => "Área académica con ID $id no encontrada.",
                    'data' => []
                ];
            }

            $item->update($data);

            return [
                'error' => false,
                'message' => 'Área académica actualizada.',
                'data' => $item->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar área académica.');

            return [
                'error' => true,
                'message' => 'Error en el servidor.',
                'data' => []
            ];
        }
    }

    // Desactiva en vez de borrar — mismo criterio que Asignatura::eliminar, porque una
    // asignatura ya podría estar apuntando a esta área (id_area queda con FK
    // nullOnDelete, pero desactivar es menos destructivo que un borrado real).
    public function eliminar(array $ids): array
    {
        try {
            if (empty($ids)) {
                return [
                    'error' => true,
                    'message' => 'Debe indicar al menos un id de área académica para desactivar.',
                    'data' => []
                ];
            }

            $desactivadas = AreaAcademicaModel::whereIn('id', $ids)->update(['activo' => 0]);

            if ($desactivadas === 0) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron áreas académicas para desactivar.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se desactivaron {$desactivadas} área(s) académica(s).",
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar área académica.');

            return [
                'error' => true,
                'message' => 'Error en el servidor.',
                'data' => []
            ];
        }
    }

}
