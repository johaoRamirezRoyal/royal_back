<?php

namespace App\Services\Prestamos;

use App\Models\Inventario\Inventario;
use App\Models\Prestamos\PrestamosInventario;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\DB;

class PrestamosService extends Service
{


    /**
     * Método para agregar o prestar un elemento del inventario.
     * @param array $data [id_inventario, id_user_entrega, id_user_recibe, id_user_prestamo, fecha_prestamo, fecha_compromiso, fecha_devolucion, observacion, user_log]
     * @return array
     */
    public function agregarPrestamos(array $data): array
    {
        try {
            return DB::transaction(function () use ($data) {

                $inventario = Inventario::find($data['id_inventario']);

                if (!$inventario) {
                    return [
                        'error' => true,
                        'message' => 'No se encontró el inventario.',
                        'data' => []
                    ];
                }

                $prestamoActivo = PrestamosInventario::where('id_inventario', $inventario->id)
                    ->whereNull('fecha_devolucion')
                    ->exists();

                if ($prestamoActivo) {
                    return [
                        'error' => true,
                        'message' => 'El elemento ya se encuentra prestado.',
                        'data' => []
                    ];
                }

                $prestamo = PrestamosInventario::create($data);

                $inventario->update([
                    'estado' => 8, // Prestado
                    'id_user' => $data['id_user_prestamo'],
                ]);

                return [
                    'error' => false,
                    'message' => 'Se ha registrado el préstamo correctamente.',
                    'data' => $prestamo->fresh()->toArray()
                ];
            });
        } catch (Exception $e) {

            $this->sendError($e, 'Error al crear el préstamo');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear el préstamo.',
                'data' => []
            ];
        }
    }

    public function actualizarPrestamo(array $data): array
    {
        try {
            $prestamo = PrestamosInventario::find($data['id']);

            if (!$prestamo) {
                return [
                    'error' => true,
                    'message' => "No se encontró el préstamo con id: {$data['id']}",
                    'data' => []
                ];
            }

            unset($data['id']);

            $prestamo->update($data);

            return [
                'error' => false,
                'message' => 'Se ha actualizado el préstamo correctamente',
                'data' => $prestamo->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el préstamo');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el préstamo',
                'data' => []
            ];
        }
    }

    public function mostrarPrestamosInventario(?string $descripcion, ?bool $activo, ?bool $devueltos, ?bool $vencidos, ?int $perpage = 10): array
    {
        try {
            $prestamos = PrestamosInventario::with([
                'usuarioEntrega:id_user,nombre,apellido',
                'usuarioRecibe:id_user,nombre,apellido',
                'usuarioPresta:id_user,nombre,apellido',
                'inventario:id,descripcion,marca,modelo,estado,activo'
            ])
                ->when($descripcion, function ($query) use ($descripcion) {
                    $query->whereHas("inventario", function ($query) use ($descripcion) {
                        $query->where("descripcion", 'like', "%{$descripcion}%");
                    });
                })
                ->when($activo, function ($query) {
                    $query->activos();
                })
                ->when($devueltos, function ($query) {
                    $query->devueltos();
                })
                ->when($vencidos, function ($query) {
                    $query->vencidos();
                })->paginate($perpage);

            if ($prestamos->count() === 0) {
                return [
                    'error' => true,
                    'message' => "No se encontraron ningun prestamo con esas indicaciones",
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Se han obtenido correctamente los datos solicitados',
                'data' => $prestamos
            ];
        } catch (Exception $e) {
            $this->sendError($e, "Error al obtener los prestamos");
            return [
                'error' => true,
                'message' => "Error en el servidor al tratar de obtener los prestamos",
                'data' => []
            ];
        }
    }
}
