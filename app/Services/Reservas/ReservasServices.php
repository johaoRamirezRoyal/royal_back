<?php

namespace App\Services\Reservas;

use App\Models\Inventario\Inventario;
use App\Models\Prestamos\PrestamosInventario;
use App\Models\Reservas\Horas;
use App\Models\Reservas\Reservas;
use App\Models\Reservas\Salones;
use App\Models\Usuarios\Usuario;
use App\Services\Prestamos\PrestamosService;
use App\Services\Service;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReservasServices extends Service
{
    public function __construct(
        protected PrestamosService $prestamosService
    ) {}

    private function validarDisponibilidadPortatil(string $fecha, int $hora): array
    {
        $total = Inventario::where('descripcion', 'like', '%PORTATIL%')
            ->where('activo', 1)
            ->whereNotIn('estado', [2, 5, 8])
            ->count();

        $reservados = Reservas::whereDate('fecha_reserva', $fecha)
            ->where('hora_reserva', $hora)
            ->where('activo', 1)
            ->whereNull('fecha_cancelado')
            ->sum('portatil');

        return [
            'reservados' => $reservados,
            'total' => $total,
            'disponibles' => $total - $reservados
        ];
    }

    public function crearReserva(array $data): array
    {
        $salon = Salones::activo()->find($data['id_salon']);

        if (!$salon) {
            return [
                'error' => true,
                'message' => 'El salón no existe o está inactivo.',
                'data' => []
            ];
        }

        $sonidoSolicitado = $data['sonido'] ?? false;

        if ($sonidoSolicitado && !$salon->sonido) {
            return [
                'error' => true,
                'message' => 'El salón no tiene disponibilidad de sonido.',
                'data' => []
            ];
        }

        $usuario = Usuario::find($data['id_user']);

        if (!$usuario) {
            return [
                'error' => true,
                'message' => 'El usuario no existe.',
                'data' => []
            ];
        }

        $fechas = (array) ($data['fecha_reserva'] ?? []);
        $horas = (array) ($data['hora_reserva'] ?? []);
        $portatilSolicitado = $data['portatil'] ?? 0;

        $combos = collect($fechas)->crossJoin($horas)->map(function ($combo) {
            return ['fecha' => $combo[0], 'hora' => $combo[1]];
        })->unique(function ($combo) {
            return $combo['fecha'] . '-' . $combo['hora'];
        })->values();

        if ($combos->isEmpty()) {
            return [
                'error' => true,
                'message' => 'Debe especificar al menos una fecha y hora.',
                'data' => []
            ];
        }

        foreach ($combos as $combo) {
            if (!strtotime($combo['fecha'])) {
                return [
                    'error' => true,
                    'message' => "La fecha {$combo['fecha']} no tiene un formato válido (Y-m-d).",
                    'data' => []
                ];
            }

            $limite = Carbon::parse($combo['fecha'])->subDay()->setTime(12, 0, 0);

            if (now() > $limite) {
                return [
                    'error' => true,
                    'message' => "La reserva para el {$combo['fecha']} debe hacerse antes de las 12:00 del día anterior.",
                    'data' => []
                ];
            }

            $hora = Horas::find($combo['hora']);

            if (!$hora) {
                return [
                    'error' => true,
                    'message' => "La hora {$combo['hora']} no existe.",
                    'data' => []
                ];
            }

            $ocupado = Reservas::where('id_salon', $data['id_salon'])
                ->where('fecha_reserva', $combo['fecha'])
                ->where('hora_reserva', $combo['hora'])
                ->where('activo', 1)
                ->whereNull('fecha_cancelado')
                ->exists();

            if ($ocupado) {
                return [
                    'error' => true,
                    'message' => "El salón ya está reservado el {$combo['fecha']} en la hora {$combo['hora']}.",
                    'data' => []
                ];
            }

            if ($portatilSolicitado > 0) {
                $disponibilidad = $this->validarDisponibilidadPortatil($combo['fecha'], $combo['hora']);

                if ($disponibilidad['disponibles'] < $portatilSolicitado) {
                    return [
                        'error' => true,
                        'message' => "No hay suficientes portátiles disponibles para el {$combo['fecha']} hora {$combo['hora']}.",
                        'data' => $disponibilidad
                    ];
                }
            }
        }

        try {
            $reservas = DB::transaction(function () use ($data, $combos, $portatilSolicitado) {
                $creadas = [];

                foreach ($combos as $combo) {
                    $reserva = Reservas::create([
                        ...$data,
                        'fecha_reserva' => $combo['fecha'],
                        'hora_reserva' => $combo['hora'],
                        'confirmado' => 2,
                        'activo' => 1,
                    ]);

                    if ($portatilSolicitado > 0) {
                        $portatiles = Inventario::where('descripcion', 'like', '%PORTATIL%')
                            ->where('activo', 1)
                            ->whereNotIn('estado', [2, 5, 8])
                            ->lockForUpdate()
                            ->limit($portatilSolicitado)
                            ->get();

                        foreach ($portatiles as $portatil) {
                            $this->prestamosService->registrarPrestamo([
                                'id_inventario'    => $portatil->id,
                                'id_user_prestamo' => $data['id_user'],
                                'fecha_prestamo'   => $combo['fecha'],
                                'id_user_entrega'  => $portatil->id_user,
                                'fecha_compromiso' => $combo['fecha'] . ' 23:59:59',
                                'observacion'      => 'Préstamo automático por reserva #' . $reserva->id,
                            ]);
                        }
                    }

                    $creadas[] = $reserva->fresh()->toArray();
                }

                return $creadas;
            });

            $total = count($reservas);

            return [
                'error' => false,
                'message' => $total === 1
                    ? 'Reserva creada correctamente.'
                    : "$total reservas creadas correctamente.",
                'data' => $reservas,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear la reserva');

            return [
                'error' => true,
                'message' => 'Error al asignar portátil(es): ' . $e->getMessage(),
                'data' => []
            ];
        }
    }

    public function actualizarReserva(array $data): array
    {
        DB::beginTransaction();

        try {
            $reserva = Reservas::find($data['id']);

            if (!$reserva) {
                DB::rollBack();
                return [
                    'error' => true,
                    'message' => "No se encontró la reserva con id: {$data['id']}.",
                    'data' => []
                ];
            }

            // Finalizar (devuelve portátiles, marca como completada)
            if (!empty($data['finalizar'])) {
                $prestamos = PrestamosInventario::where('observacion', 'like', "%reserva #{$reserva->id}")
                    ->whereNull('fecha_devolucion')
                    ->get();

                foreach ($prestamos as $prestamo) {
                    $prestamo->update(['fecha_devolucion' => now()]);
                    $prestamo->inventario()->update(['estado' => 1]);
                }

                $reserva->update(['confirmado' => 1]);

                DB::commit();
                return [
                    'error' => false,
                    'message' => 'Reserva finalizada correctamente.',
                    'data' => $reserva->fresh()->toArray()
                ];
            }

            // Cancelación
            if (!empty($data['cancelar'])) {
                $reserva->update([
                    'fecha_cancelado' => now(),
                    'activo' => 0,
                ]);

                // Devolver portátiles prestados por esta reserva
                $prestamos = PrestamosInventario::where('observacion', 'like', "%reserva #{$reserva->id}")
                    ->whereNull('fecha_devolucion')
                    ->get();

                foreach ($prestamos as $prestamo) {
                    $prestamo->update(['fecha_devolucion' => now()]);
                    $prestamo->inventario()->update(['estado' => 1]);
                }

                DB::commit();
                return [
                    'error' => false,
                    'message' => 'Reserva cancelada correctamente.',
                    'data' => $reserva->fresh()->toArray()
                ];
            }

            // No permitir cambiar portátil ni sonido
            unset($data['portatil'], $data['sonido']);

            if (!empty($data['hora_reserva'])) {
                $hora = Horas::find($data['hora_reserva']);

                if (!$hora) {
                    DB::rollBack();
                    return [
                        'error' => true,
                        'message' => 'La hora seleccionada no existe.',
                        'data' => []
                    ];
                }
            }

            if (!empty($data['id_salon'])) {
                $fechaReserva = $data['fecha_reserva'] ?? $reserva->fecha_reserva;
                $horaReserva = $data['hora_reserva'] ?? $reserva->hora_reserva;

                $ocupado = Reservas::where('id_salon', $data['id_salon'])
                    ->where('fecha_reserva', $fechaReserva)
                    ->where('hora_reserva', $horaReserva)
                    ->where('activo', 1)
                    ->whereNull('fecha_cancelado')
                    ->where('id', '!=', $data['id'])
                    ->exists();

                if ($ocupado) {
                    DB::rollBack();
                    return [
                        'error' => true,
                        'message' => 'El salón ya se encuentra reservado para esa fecha y hora.',
                        'data' => []
                    ];
                }
            }

            unset($data['id'], $data['cancelar'], $data['finalizar']);
            $reserva->update($data);

            DB::commit();

            return [
                'error' => false,
                'message' => 'Reserva actualizada correctamente.',
                'data' => $reserva->fresh()->toArray()
            ];
        } catch (Exception $e) {
            DB::rollBack();

            $this->sendError($e, 'Error al actualizar la reserva');

            return [
                'error' => true,
                'message' => 'Error al actualizar la reserva.',
                'data' => []
            ];
        }
    }

    public function mostrarReservas(
        ?int $id_user,
        ?int $id_salon,
        ?string $fechaReserva,
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?bool $cancelado,
        ?int $perpage = 10
    ): array {
        try {

            $reservas = Reservas::with([
                'salon:id,nombre',
                'hora:id,horas',
                'usuario:id_user,nombre,apellido'
            ])
                ->when($id_user, function ($query) use ($id_user) {
                    $query->where('id_user', $id_user);
                })
                ->when($id_salon, function ($query) use ($id_salon) {
                    $query->where('id_salon', $id_salon);
                })
                ->when($fechaReserva, function ($query) use ($fechaReserva) {
                    $query->whereDate('fecha_reserva', $fechaReserva);
                })
                ->when($fechaDesde, function ($query) use ($fechaDesde) {
                    $query->whereDate('fecha_reserva', '>=', $fechaDesde);
                })
                ->when($fechaHasta, function ($query) use ($fechaHasta) {
                    $query->whereDate('fecha_reserva', '<=', $fechaHasta);
                })
                ->when(!is_null($cancelado), function ($query) use ($cancelado) {
                    if ($cancelado) {
                        $query->whereNotNull('fecha_cancelado');
                    } else {
                        $query->whereNull('fecha_cancelado');
                    }
                })
                ->orderBy('fecha_reserva')
                ->orderBy('id_salon')
                ->orderBy('hora_reserva')
                ->paginate($perpage);

            if ($reservas->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron reservas con los filtros indicados.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Se obtuvieron correctamente las reservas.',
                'data' => $reservas
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al obtener las reservas');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener las reservas.',
                'data' => []
            ];
        }
    }

    /*
    -------------------------------------------------
    |
    |             SALONES
    |
    -------------------------------------------------
    */

    public function crearSalon(array $datos): array
    {
        try {
            $salon = Salones::create([
                ...$datos,
                'id_user' => Auth::id(),
                'activo' => 1,
            ]);

            return [
                'error' => false,
                'message' => 'Salón creado correctamente.',
                'data' => $salon->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear el salón');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear el salón.',
                'data' => []
            ];
        }
    }

    public function actualizarSalon(array $datos, int $id): array
    {
        try {
            $salon = Salones::activo()->find($id);

            if (!$salon) {
                return [
                    'error' => true,
                    'message' => "No se encontró el salón con id: {$id}.",
                    'data' => []
                ];
            }

            $salon->update($datos);

            return [
                'error' => false,
                'message' => 'Salón actualizado correctamente.',
                'data' => $salon->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el salón');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el salón.',
                'data' => []
            ];
        }
    }

    /**
     * Baja lógica (activo=0): un hard delete dejaría huérfanas las reservas
     * históricas que apuntan a id_salon (no hay FK en BD para esa columna).
     */
    public function eliminarSalon(int $id): array
    {
        try {
            $salon = Salones::activo()->find($id);

            if (!$salon) {
                return [
                    'error' => true,
                    'message' => "No se encontró el salón con id: {$id}.",
                    'data' => []
                ];
            }

            $salon->update(['activo' => 0]);

            return [
                'error' => false,
                'message' => 'Salón eliminado correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el salón');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar el salón.',
                'data' => []
            ];
        }
    }
}
