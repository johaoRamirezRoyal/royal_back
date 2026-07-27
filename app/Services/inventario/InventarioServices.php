<?php

namespace App\Services\inventario;

use App\Models\AnioEscolar\Anio;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\InventarioDescontinuado;
use App\Models\Inventario\InventarioLiberado;
use App\Models\Inventario\InventarioLog;
use App\Models\Inventario\Reportes;
use App\Services\MailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventarioServices
{
    public function __construct(
        private MailService $mailService
    ) {}

    /**
     * Summary of mailTo
     * @var array
     */
    private array $mailTo = [
        'hernando.ramirez@royalschool.edu.co'
    ];

    private function registrarLog(array $items, int $estado, ?int $idUser, ?int $idArea = null): void
    {
        $registros = array_map(function ($item) use ($estado, $idUser, $idArea) {
            $idInventario = is_object($item) ? $item->id : $item;
            $area = $idArea ?? (is_object($item) ? $item->id_area : null);

            return [
                'id_inventario' => $idInventario,
                'id_user' => $idUser,
                'id_area' => $area,
                'id_log' => $idUser,
                'estado' => $estado,
                'id_super_empresa' => null,
            ];
        }, $items);

        InventarioLog::insert($registros);
    }

    /**
     * Summary of obtenerListadoInventario
     * @param mixed $perPage
     * @param mixed $search
     * @param mixed $datos
     * @return array{data: array, error: bool, message: string|array{data: null, error: bool, message: string}}
     */
    public function obtenerListadoInventario($perPage = 15, $search = null, $datos = [])
    {
        try {
            $listado = Inventario::select(
                'id_user',
                'id_area',
                'descripcion',
                'id_categoria',
                DB::raw("
                CONCAT(
                    '[',
                    GROUP_CONCAT(
                        JSON_OBJECT(
                            'id', inventario.id,
                            'marca', inventario.marca,
                            'modelo', inventario.modelo,
                            'precio', inventario.precio,
                            'estado_id', inventario.estado,
                            'estado_nombre', e.nombre,
                            'codigo', inventario.codigo
                        )
                    ),
                    ']'
                ) as items
            ")
            )
                ->leftJoin('estado as e', 'inventario.estado', '=', 'e.id')
                ->with([
                    'usuario:id_user,nombre,apellido',
                    'area:id,nombre',
                    'categoria:id,nombre'
                ])
                ->when($search, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('descripcion', 'like', "%{$search}%");
                    });
                })->when($datos['id_area'] ?? null, function ($query) use ($datos) {
                    $query->whereIn('id_area', $datos['id_area']);
                })->when($datos['id_categoria'] ?? null, function ($query) use ($datos) {
                    $query->whereIn('id_categoria', $datos['id_categoria']);
                })->when($datos['estado'] ?? null, function ($query) use ($datos) {
                    $query->whereIn('estado', $datos['estado']);
                })->when($datos['id_usuario'] ?? null, function ($query) use ($datos) {
                    $query->where('id_user', $datos['id_usuario']);
                })
                ->groupBy('id_user', 'id_area', 'descripcion', 'id_categoria')
                ->paginate($perPage);

            // convertir string a JSON real
            $listado->transform(function ($item) {
                $item->items = json_decode($item->items);
                return $item;
            });

            return [
                'error' => false,
                'data' => $listado->toArray(),
                'message' => "Listado de inventario obtenido"
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'data' => null,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Summary of agregarInventario
     * @param mixed $inventario
     * @return array{data: array, error: bool, message: string|array{data: null, error: bool, message: string}}
     */
    public function agregarInventario($inventario)
    {
        try {
            $inventario = Inventario::create($inventario);

            $this->registrarLog([$inventario], $inventario->estado, null);

            return [
                'error' => false,
                'data' => $inventario->toArray(),
                'message' => "Inventario agregado"
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'data' => null,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Summary of descontinuarInventario
     * @param array $ids
     * @param mixed $id_log
     */
    public function descontinuarInventario(array $ids, ?int $id_log = null)
    {
        try {

            $result = DB::transaction(function () use ($ids, $id_log) {

                $inventario = Inventario::whereIn('id', $ids)
                    ->where('estado', '!=', 5)
                    ->get();

                if ($inventario->isEmpty()) {
                    return [
                        "error" => true,
                        "data" => null,
                        "message" => "No se encontraron esos elementos del inventario"
                    ];
                }

                Inventario::whereIn('id', $inventario->pluck('id'))
                    ->update([
                        "estado" => 5,
                        "activo" => 0,
                    ]);

                $registros = [];

                foreach ($inventario as $inv) {
                    $registros[] = [
                        "id_inventario" => $inv->id,
                        "id_log" => $id_log
                    ];
                }

                InventarioDescontinuado::insert($registros);

                $this->registrarLog($inventario->all(), 5, $id_log);

                return [
                    "error" => false,
                    "message" => "Inventario descontinuado correctamente",
                    "data" => $inventario
                ];
            });

            if (!$result['error']) {

                $titulo = "Notificación | Inventario Descontinuado";
                $contenido = "Se han descontinuado los siguientes elementos:\n\n";

                foreach ($result['data'] as $inv) {
                    $contenido .= "- {$inv->descripcion} (Código: {$inv->codigo})\n";
                }

                $this->mailService->sendGeneric($this->mailTo, $titulo, $contenido);
            }

            return $result;
        } catch (\Exception $e) {
            return [
                "error" => true,
                "message" => $e->getMessage(),
                "data" => null,
            ];
        }
    }

    /**
     * Summary of liberarInventario
     * @param array $ids
     * @param mixed $id_log
     */
    public function liberarInventario(array $ids, ?int $id_log = null)
    {
        try {
            $result = DB::transaction(function () use ($ids, $id_log) {
                $inventario = Inventario::whereIn('id', $ids)
                    ->whereNotIn('estado', [4, 5])
                    ->get();

                if ($inventario->isEmpty()) {
                    return [
                        'error' => true,
                        'message' => 'No se encontraron esos elementos en el inventario',
                        'data' => null,
                    ];
                }

                Inventario::whereIn('id', $inventario->pluck('id'))
                    ->update([
                        "estado" => 4,
                        "id_user" => null,
                        "id_area" => null,
                    ]);

                $registros = [];

                foreach ($inventario as $i) {
                    $registros[] = [
                        'id_inventario' => $i->id,
                        'id_log' => $id_log,
                    ];
                }

                InventarioLiberado::insert($registros);

                $this->registrarLog($inventario->all(), 4, $id_log);

                return [
                    "error" => false,
                    "message" => "Inventario Liberado correctamente",
                    "data" => $inventario
                ];
            });

            if (!$result['error']) {
                $titulo = "Notificación | Inventario Liberado";
                $contenido = "Se han Liberado los siguientes elementos:\n\n";

                foreach ($result['data'] as $inv) {
                    $contenido .= "- {$inv->descripcion} (Código: {$inv->codigo})\n";
                }

                $this->mailService->sendGeneric($this->mailTo, $titulo, $contenido);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('No se liberaron los elementos: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => 'Error liberando esos elementos: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Summary of asignarInventario
     * @param array $ids
     * @param int $id_area
     * @param int $id_usuario
     * @return array{data: array, error: bool, message: string|array{data: null, error: bool, message: string}}
     */
    public function asignarInventario(array $ids, int $id_area, int $id_usuario)
    {
        try {
            $inventario_liberado = Inventario::whereIn('id', $ids)
                ->where('estado', 4)
                ->get();

            if ($inventario_liberado->isEmpty()) {
                return [
                    'message' => "Ese inventario no está liberado.",
                    'data' => null,
                    'error' => true,
                ];
            }

            Inventario::whereIn('id', $inventario_liberado->pluck('id'))
                ->where('estado', 4)
                ->update([
                    'estado' => 1,
                    'id_area' => $id_area,
                    'id_user' => $id_usuario,
                ]);

            $this->registrarLog($inventario_liberado->all(), 1, null, $id_area);

            $titulo = "Notificación | Inventario Asignado";
            $contenido = "Se han Asignado los siguientes elementos:\n\n";


            foreach ($inventario_liberado as $inv) {
                $contenido .= "{$inv->descripcion} (Codigo: {$inv->id})\n";
            }

            $this->mailService->sendGeneric($this->mailTo, $titulo, $contenido);

            return [
                'data' => $inventario_liberado->toArray(),
                'message' => "Inventario asignado",
                'error' => false,
            ];
        } catch (\Exception $e) {
            Log::error("No se asigno el inventario: " . $e->getMessage());

            return [
                'data' => null,
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Metodo para reportar varios inventarios. 
     * @param array $ids
     * @param int $id_log
     * @param string $descripcion
     * @param int $id_anio
     * @param int $id_periodo
     * @return array
     */
    public function reportarInventario(
        array $ids,
        int $id_log,
        string $descripcion,
        int $id_anio,
        int $id_periodo
    ): array {

        try {

            return DB::transaction(function () use ($ids, $id_log, $descripcion, $id_anio, $id_periodo) {

                $inventario = Inventario::whereIn('id', $ids)
                    ->whereNotIn('estado', [2, 5])
                    ->get();

                if ($inventario->isEmpty()) {
                    return [
                        'error' => true,
                        'message' => 'No se encontró inventario disponible para reportar.',
                        'data' => []
                    ];
                }

                foreach ($inventario as $item) {

                    $item->update([
                        'estado' => 2,
                        'observacion' => $descripcion
                    ]);

                    Reportes::create([
                        'id_inventario' => $item->id,
                        'id_area' => $item->id_area,
                        'tipo_reporte' => 1,
                        'estado' => 2,
                        'id_log' => $id_log,
                        'id_user' => $id_log,
                        'descripcion' => $descripcion,
                        'observacion' => $descripcion,
                        'id_anio' => $id_anio,
                        'periodo' => $id_periodo
                    ]);
                }

                $this->registrarLog($inventario->all(), 2, $id_log);

                return [
                    'error' => false,
                    'message' => 'Inventario reportado correctamente.',
                    'data' => $inventario
                ];
            });
        } catch (\Throwable $e) {

            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    public function mostrarReportesDeInventario(
        ?array $id_inventario,
        ?int $id_user,
        ?int $id_anio,
        ?int $id_periodo,
        ?string $search,
        ?int $estado,
        ?int $tipo_categoria,
        ?int $per_page
    ): array {
        try {

            $query = Reportes::query()
                ->with([
                    'inventario',
                    'usuario',
                    'anio',
                    'periodo'
                ])
                ->when(!empty($id_inventario), function ($q) use ($id_inventario) {
                    $q->whereIn('id_inventario', $id_inventario);
                })
                ->when($id_user, function ($q) use ($id_user) {
                    $q->where('id_user', $id_user);
                })
                ->when($id_anio, function ($q) use ($id_anio) {
                    $q->where('id_anio', $id_anio);
                })
                ->when($id_periodo, function ($q) use ($id_periodo) {
                    $q->where('periodo', $id_periodo);
                })
                ->when(!is_null($estado), function ($q) use ($estado) {
                    $q->where('estado', $estado);
                })
                ->when($tipo_categoria, function ($q) use ($tipo_categoria) {
                    $q->whereHas('inventario.categoria', function ($categoria) use ($tipo_categoria) {
                        $categoria->where('tipo_categoria', $tipo_categoria);
                    });
                })
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($query) use ($search) {
                        $query->where('descripcion', 'like', "%{$search}%")
                            ->orWhereHas('inventario', function ($inventario) use ($search) {
                                $inventario->where('codigo', 'like', "%{$search}%")
                                    ->orWhere('descripcion', 'like', "%{$search}%");
                            })
                            ->orWhereHas('usuario', function ($usuario) use ($search) {
                                $usuario->where('nombre', 'like', "%{$search}%");
                            });
                    });
                })
                ->latest('id');

            $reportes = $per_page
                ? $query->paginate($per_page)
                : $query->get();

            return [
                'error' => false,
                'message' => 'Reportes obtenidos correctamente.',
                'data' => $reportes
            ];
        } catch (\Throwable $e) {

            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Método para solucionar un reporte a partir de su ID.
     * @param int $id_reporte
     * @param int $id_resp
     * @param mixed $fecha_respuesta
     * @param string $descripcion
     * @return array
     */
    public function solucionarReporteInventario(
        int $id_reporte,
        int $id_resp,
        ?string $fecha_respuesta,
        string $descripcion
    ): array {
        try {

            return DB::transaction(function () use ($id_reporte, $id_resp, $fecha_respuesta, $descripcion) {

                $reporte = Reportes::with('inventario')->find($id_reporte);

                if (!$reporte) {
                    return [
                        'error' => true,
                        'message' => 'No se encontró el reporte.',
                        'data' => []
                    ];
                }

                // Verificar si ya existe una solución para este reporte
                $solucion = Reportes::where('id_reporte', $reporte->id)->first();

                if ($solucion) {
                    return [
                        'error' => true,
                        'message' => 'El reporte ya fue solucionado.',
                        'data' => $solucion
                    ];
                }

                $solucion = Reportes::create([
                    'id_reporte'        => $reporte->id,
                    'id_inventario'     => $reporte->id_inventario,
                    'id_area'           => $reporte->id_area,
                    'id_user'           => $reporte->id_user,
                    'id_log'            => $id_resp,
                    'id_resp'           => $id_resp,
                    'fecha_respuesta'   => $fecha_respuesta ?? now(),
                    'descripcion'       => $descripcion,
                    'estado'            => 3, // Solucionado
                    'periodo'        => $reporte->periodo,
                    'id_anio'           => $reporte->id_anio,
                ]);

                // Verificar si quedan otros reportes pendientes para el inventario
                $tienePendientes = Reportes::where('id_inventario', $reporte->id_inventario)
                    ->whereNull('id_reporte') // Solo reportes originales
                    ->where('estado', 2)      // Estado reportado
                    ->where('id', '<>', $reporte->id)
                    ->whereDoesntHave('solucion')
                    ->exists();

                if (!$tienePendientes) {
                    $reporte->inventario->update([
                        'estado' => 3,
                        'observacion' => $descripcion
                    ]);

                    $this->registrarLog([$reporte->inventario], 3, $id_resp, $reporte->id_area);
                }

                return [
                    'error' => false,
                    'message' => 'Reporte solucionado correctamente.',
                    'data' => $solucion->fresh()
                ];
            });
        } catch (\Throwable $e) {

            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    public function programarMantenimientoPreventivo(
        array $ids,
        string $fecha_inicio,
        int $id_log,
        string $observacion,
        ?int $id_anio,
        ?int $periodo
    ): array {
        try {
            $inventarios = Inventario::whereIn('id', $ids)
                ->whereNotIn('estado', [2, 5, 6])
                ->get();

            if ($inventarios->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron inventarios disponibles para programar mantenimiento preventivo.',
                    'data' => []
                ];
            }

            $idAnio = $id_anio;
            $idPeriodo = $periodo;

            if (is_null($idAnio) || is_null($idPeriodo)) {
                $ultimoAnioEscolar = Anio::with([
                    'periodos:id,id_anio,numero,fecha_inicio,fecha_fin'
                ])
                    ->where('activo', 1)
                    ->latest('id')
                    ->first();

                if (is_null($idPeriodo)) {
                    return [
                        'error' => true,
                        'message' => "Debes añadir el periodo escolar para el mantenimiento",
                        'data' => []
                    ];
                }

                if (!$ultimoAnioEscolar) {
                    return [
                        'error' => true,
                        'message' => 'No existe un año escolar registrado.',
                        'data' => []
                    ];
                }

                $idAnio ??= $ultimoAnioEscolar->id;
            }

            DB::transaction(function () use (
                $inventarios,
                $fecha_inicio,
                $id_log,
                $observacion,
                $idAnio,
                $idPeriodo
            ) {
                foreach ($inventarios as $inventario) {
                    Reportes::create([
                        'id_inventario' => $inventario->id,
                        'id_area' => $inventario->id_area,
                        'observacion' => 'Mantenimiento Preventivo',
                        'estado' => 6,
                        'id_user' => $inventario->id_user,
                        'id_log' => $id_log,
                        'id_resp' => $inventario->id_user,
                        'tipo_reporte' => 2,
                        'descripcion' => $observacion,
                        'id_anio' => $idAnio,
                        'periodo' => $idPeriodo,
                        'fechareg' => $fecha_inicio,
                    ]);

                    $inventario->update([
                        'estado' => 6,
                        'observacion' => $observacion,
                    ]);
                }
            });

            return [
                'error' => false,
                'message' => 'Se programó el mantenimiento preventivo para ' . $inventarios->count() . ' inventario(s).',
                'data' => []
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }
}
