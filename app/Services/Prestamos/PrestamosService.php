<?php

namespace App\Services\Prestamos;

use App\Models\Inventario\Inventario;
use App\Models\Inventario\InventarioLog;
use App\Models\Prestamos\PrestamosInventario;
use App\Models\Reservas\Reservas;
use App\Models\Usuarios\Usuario;
use App\Services\inventario\InventarioEmailHelper;
use App\Services\MailService;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\DB;

class PrestamosService extends Service
{
    public function __construct(
        private MailService $mailService,
    ) {}

    // Mismo correo fijo de sistemas que InventarioServices::$mailTo — el equipo que
    // monitorea el resto de los procesos de inventario también debe ver los préstamos.
    private function destinatarios(?string ...$correos): array
    {
        $destinatarios = array_merge(
            ['cronograma.sistemas@royalschool.edu.co'],
            array_filter($correos),
        );

        return array_values(array_unique($destinatarios));
    }

    // Mismo registro que InventarioServices::registrarLog — los cambios de estado por
    // préstamo (8 = Prestado, 1 = devuelto/Asignado) no pasaban por ahí y quedaban fuera
    // del historial de inventario (`inventario_log`).
    public function registrarLog(Inventario $inventario, int $estado, ?int $idUser): void
    {
        InventarioLog::create([
            'id_inventario' => $inventario->id,
            'id_user' => $idUser,
            'id_area' => $inventario->id_area,
            'id_log' => $idUser,
            'estado' => $estado,
            'id_super_empresa' => null,
        ]);
    }

    /**
     * Método para agregar o prestar un elemento del inventario.
     * @param array $data [id_inventario, id_user_entrega, id_user_recibe, id_user_prestamo, fecha_prestamo, fecha_compromiso, fecha_devolucion, observacion, user_log]
     * @return array
     */
    public function agregarPrestamos(array $data): array
    {
        try {
            $inventarioPrestado = null;

            $resultado = DB::transaction(function () use ($data, &$inventarioPrestado) {

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
                $this->registrarLog($inventario, 8, $data['id_user_entrega'] ?? $data['id_user_prestamo']);
                $inventarioPrestado = $inventario;

                return [
                    'error' => false,
                    'message' => 'Se ha registrado el préstamo correctamente.',
                    'data' => $prestamo->fresh()->toArray()
                ];
            });

            if (!$resultado['error'] && $inventarioPrestado) {
                $this->notificarPrestamo($inventarioPrestado, $data);
            }

            return $resultado;
        } catch (Exception $e) {

            $this->sendError($e, 'Error al crear el préstamo');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear el préstamo.',
                'data' => []
            ];
        }
    }

    /** Correo al registrar un préstamo — mismos campos estándar que el resto de Inventario. */
    private function notificarPrestamo(Inventario $inventario, array $data): void
    {
        try {
            $actor = InventarioEmailHelper::nombreUsuario($data['id_user_entrega'] ?? null);
            $responsableNombre = InventarioEmailHelper::nombreUsuario($data['id_user_prestamo'] ?? null);
            $fecha = !empty($data['fecha_prestamo'])
                ? \Carbon\Carbon::parse($data['fecha_prestamo'])->format('d/m/Y H:i')
                : now()->format('d/m/Y H:i');
            $nombreEstado = InventarioEmailHelper::nombreEstado(8, 'Prestado');

            $titulo = 'Notificación | Inventario Prestado';
            $contenido = "Se ha registrado el siguiente préstamo de inventario:\n\n"
                . InventarioEmailHelper::detalle($inventario, $nombreEstado, $actor, $responsableNombre, $fecha);

            $correoEntrega = Usuario::find($data['id_user_entrega'] ?? null)?->correo;
            $correoPresta = Usuario::find($data['id_user_prestamo'] ?? null)?->correo;

            $this->mailService->sendGeneric($this->destinatarios($correoEntrega, $correoPresta), $titulo, $contenido);
        } catch (\Throwable $e) {
            $this->sendError($e, 'No se pudo notificar el préstamo');
        }
    }

    public function actualizarPrestamo(array $data): array
    {
        try {
            $inventarioDevuelto = null;

            $resultado = DB::transaction(function () use ($data, &$inventarioDevuelto) {

                $prestamo = PrestamosInventario::with('inventario')
                    ->find($data['id']);

                if (!$prestamo) {
                    return [
                        'error' => true,
                        'message' => "No se encontró el préstamo con id: {$data['id']}",
                        'data' => []
                    ];
                }

                // Evitar registrar una devolución dos veces
                if (
                    isset($data['id_user_recibe']) &&
                    $prestamo->fecha_devolucion !== null
                ) {
                    return [
                        'error' => true,
                        'message' => 'El préstamo ya fue devuelto.',
                        'data' => []
                    ];
                }

                unset($data['id']);

                $prestamo->update($data);

                // Si se está registrando la devolución: el inventario vuelve a estado
                // 1 (Asignado) y se reasigna a quien lo entregó originalmente (el
                // responsable), no se queda a nombre de quien lo tenía prestado.
                if (isset($data['id_user_recibe'])) {

                    $prestamo->inventario->update([
                        'estado'  => 1,
                        'id_user' => $prestamo->id_user_entrega,
                    ]);
                    $this->registrarLog($prestamo->inventario, 1, $data['id_user_recibe']);
                    $inventarioDevuelto = $prestamo->inventario;
                }

                $prestamo->refresh();

                return [
                    'error' => false,
                    'message' => 'Se ha actualizado el préstamo correctamente.',
                    'data' => $prestamo->toArray()
                ];
            });

            if (!$resultado['error'] && $inventarioDevuelto) {
                $this->notificarDevolucionPrestamo($inventarioDevuelto, $data);
            }

            return $resultado;
        } catch (Exception $e) {

            $this->sendError($e, 'Error al actualizar el préstamo');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el préstamo.',
                'data' => []
            ];
        }
    }

    /** Correo al registrar la devolución de un préstamo — mismos campos estándar. */
    private function notificarDevolucionPrestamo(Inventario $inventario, array $data): void
    {
        try {
            $inventario->refresh();

            $actor = InventarioEmailHelper::nombreUsuario($data['id_user_recibe'] ?? null);
            $responsableNombre = InventarioEmailHelper::nombreUsuario($inventario->id_user);
            $fecha = !empty($data['fecha_devolucion'])
                ? \Carbon\Carbon::parse($data['fecha_devolucion'])->format('d/m/Y H:i')
                : now()->format('d/m/Y H:i');
            $nombreEstado = InventarioEmailHelper::nombreEstado(1, 'Asignado');

            $titulo = 'Notificación | Préstamo Devuelto';
            $contenido = "Se ha registrado la devolución del siguiente préstamo de inventario:\n\n"
                . InventarioEmailHelper::detalle($inventario, $nombreEstado, $actor, $responsableNombre, $fecha);

            $correoRecibe = Usuario::find($data['id_user_recibe'] ?? null)?->correo;
            $correoResponsable = Usuario::find($inventario->id_user)?->correo;

            $this->mailService->sendGeneric($this->destinatarios($correoRecibe, $correoResponsable), $titulo, $contenido);
        } catch (\Throwable $e) {
            $this->sendError($e, 'No se pudo notificar la devolución del préstamo');
        }
    }

    public function mostrarPrestamosInventario(
        ?string $descripcion,
        ?bool $activo,
        ?bool $devueltos,
        ?bool $vencidos,
        ?int $idUserEntrega = null,
        ?int $idUserPresta = null,
        ?int $perpage = 10
    ): array {
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
                })
                ->when($idUserEntrega, function ($query) use ($idUserEntrega) {
                    $query->where('id_user_entrega', $idUserEntrega);
                })
                ->when($idUserPresta, function ($query) use ($idUserPresta) {
                    $query->where('id_user_prestamo', $idUserPresta);
                })
                ->paginate($perpage);

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

    public function registrarPrestamo(array $data)
    {
        $inventario = Inventario::find($data['id_inventario']);

        if (!$inventario) {
            throw new Exception('No existe el inventario.');
        }

        $prestamoActivo = PrestamosInventario::where('id_inventario', $inventario->id)
            ->whereNull('fecha_devolucion')
            ->exists();

        if ($prestamoActivo) {
            throw new Exception('El portátil ya se encuentra prestado.');
        }

        $prestamo = PrestamosInventario::create($data);

        $inventario->update([
            'estado' => 8,
            'id_user' => $data['id_user_prestamo']
        ]);
        $this->registrarLog($inventario, 8, $data['id_user_entrega'] ?? $data['id_user_prestamo']);

        return $prestamo;
    }
}
