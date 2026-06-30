<?php

namespace App\Services\GestionAcademica;

use App\Models\GestionAcademica\Asignatura as AsignaturaModel;
use App\Services\Service;
use Exception;

class Asignatura extends Service
{

    public function mostrarAsignaturasFiltradas(?string $nombre, ?string $codigo, ?string $abreviatura, ?int $estado)
    {
        try {
            $asignaturas = AsignaturaModel::query()
                ->when($nombre, function ($query) use ($nombre) {
                    $query->where('nombre', 'LIKE', "%$nombre%");
                })
                ->when($codigo, function ($query) use ($codigo) {
                    $query->where('codigo',  'LIKE', "%$codigo%");
                })
                ->when($abreviatura, function ($query) use ($abreviatura) {
                    $query->where('abreviatura', 'LIKE', "%$abreviatura%");
                })
                ->when($estado !== null, function ($query) use ($estado) {
                    $query->where('activo', $estado);
                })->get();

            if ($asignaturas->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron asignaturas',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Asignaturas obtenidas.',
                'data' => $asignaturas->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al mostrar asignaturas filtradas.');
            return [
                'error' => true,
                'message' => 'Error en el servidor.',
                'data' => []
            ];
        }
    }

    public function cambiarEstadoAsignatura(array $ids, int $estado = 0): array
    {
        try {
            $data = AsignaturaModel::whereIn('id', $ids)->update(['activo' => $estado]);

            if ($data == 0) {
                return [
                    'error' => true,
                    'message' => 'No se pudo cambiar el estado de las asignaturas',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se han actualizado $data asignaturas",
                'data' => $data
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al cambiar el estado de las asignaturas.');
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
            $item = AsignaturaModel::find($id);

            if (!$item) {
                return ['error' => true, 'message' => "Asignatura con ID $id no encontrada.", 'data' => []];
            }

            return ['error' => false, 'message' => 'Asignatura obtenida.', 'data' => $item->toArray()];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener asignatura.');
            return ['error' => true, 'message' => 'Error en el servidor.', 'data' => []];
        }
    }

    public function crear(array $data): array
    {
        try {
            $item = AsignaturaModel::create($data);

            return [
                'error' => false,
                'message' => 'Asignatura creada.',
                'data' => $item->toArray()
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al crear asignatura.');

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
            $item = AsignaturaModel::find($id);

            if (!$item) {
                return [
                    'error' => true,
                    'message' => "Asignatura con ID $id no encontrada.",
                    'data' => []
                ];
            }

            $item->update($data);

            return [
                'error' => false,
                'message' => 'Asignatura actualizada.',
                'data' => $item->fresh()->toArray()
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al actualizar asignatura.');

            return [
                'error' => true,
                'message' => 'Error en el servidor.',
                'data' => []
            ];
        }
    }

    public function eliminar(int $id): array
    {
        try {
            $item = AsignaturaModel::find($id);

            if (!$item) {
                return [
                    'error' => true,
                    'message' => "Asignatura con ID $id no encontrada.",
                    'data' => []
                ];
            }

            $item->update(['activo' => 0]);

            return [
                'error' => false,
                'message' => 'Asignatura desactivada.',
                'data' => []
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al eliminar asignatura.');

            return [
                'error' => true,
                'message' => 'Error en el servidor.',
                'data' => []
            ];
        }
    }
}
