<?php

namespace App\Services\inventario;

use App\Models\AnioEscolar\Anio;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\InventarioDescontinuado;
use App\Models\Inventario\InventarioLiberado;
use App\Models\Inventario\InventarioLog;
use App\Models\Inventario\Reportes;
use App\Models\Usuarios\Usuario;
use App\Services\MailService;
use Carbon\Carbon;
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
        'cronograma.sistemas@royalschool.edu.co'
    ];

    private function destinatarios(?string $responsableCorreo = null, ?string $reportadorCorreo = null): array
    {
        $destinatarios = $this->mailTo;
        foreach (array_filter([$responsableCorreo, $reportadorCorreo]) as $correo) {
            $destinatarios[] = $correo;
        }
        return array_values(array_unique($destinatarios));
    }

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
     * @param string|null $sort 'usuario' o 'cantidad'
     * @param string $dir 'asc' o 'desc'
     * @return array{data: array, error: bool, message: string|array{data: null, error: bool, message: string}}
     */
    public function obtenerListadoInventario($perPage = 15, $search = null, $datos = [], $sort = null, $dir = 'asc')
    {
        try {
            $dir = strtolower($dir) === 'desc' ? 'desc' : 'asc';

            // Último reporte/mantenimiento (id_reporte IS NULL) por ítem, con su solución si
            // ya la tiene. Va como LEFT JOIN a una tabla derivada -en vez de subconsulta
            // correlacionada dentro del GROUP_CONCAT- porque MariaDB (ONLY_FULL_GROUP_BY)
            // no permite referenciar `inventario.id` desde dentro de una subconsulta ahí.
            $ultimoReporteJoin = DB::raw("(
                SELECT
                    ranked.id_inventario,
                    ranked.id,
                    ranked.tipo_reporte,
                    ranked.descripcion,
                    ranked.estado,
                    ranked.fechareg,
                    sol.id as sol_id,
                    sol.observacion as sol_observacion,
                    sol.estado as sol_estado,
                    sol.id_resp as sol_id_resp,
                    IF(CAST(sol.fecha_respuesta AS CHAR) = '0000-00-00 00:00:00', NULL, sol.fecha_respuesta) as sol_fecha_respuesta,
                    sol.fechareg as sol_fechareg
                FROM (
                    SELECT r.*, ROW_NUMBER() OVER (PARTITION BY r.id_inventario ORDER BY r.fechareg DESC) as rn
                    FROM reportes r
                    WHERE r.id_reporte IS NULL
                ) ranked
                LEFT JOIN reportes sol ON sol.id_reporte = ranked.id
                WHERE ranked.rn = 1
            ) as ur");

            $listado = Inventario::select(
                'inventario.id_user',
                'inventario.id_area',
                'inventario.descripcion',
                'inventario.id_categoria',
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
                            'codigo', inventario.codigo,
                            'fecha_compra', inventario.fecha_compra,
                            'ultimo_reporte', IF(ur.id IS NULL, NULL, JSON_OBJECT(
                                'id', ur.id,
                                'tipo_reporte', ur.tipo_reporte,
                                'descripcion', ur.descripcion,
                                'estado', ur.estado,
                                'fechareg', ur.fechareg,
                                'solucion', IF(ur.sol_id IS NULL, NULL, JSON_OBJECT(
                                    'id', ur.sol_id,
                                    'observacion', ur.sol_observacion,
                                    'estado', ur.sol_estado,
                                    'id_resp', ur.sol_id_resp,
                                    'fecha_respuesta', ur.sol_fecha_respuesta,
                                    'fechareg', ur.sol_fechareg
                                ))
                            ))
                        )
                    ),
                    ']'
                ) as items
            ")
            )
                ->leftJoin('estado as e', 'inventario.estado', '=', 'e.id')
                ->leftJoin('usuarios as u', 'inventario.id_user', '=', 'u.id_user')
                ->leftJoin($ultimoReporteJoin, 'ur.id_inventario', '=', 'inventario.id')
                ->with([
                    'usuario:id_user,nombre,apellido',
                    'area:id,nombre',
                    'categoria:id,nombre'
                ])
                ->when($search, function ($query, $search) {
                    $query->where(function ($q) use ($search) {
                        $q->where('inventario.descripcion', 'like', "%{$search}%");
                    });
                })->when($datos['id_area'] ?? null, function ($query) use ($datos) {
                    $query->whereIn('inventario.id_area', $datos['id_area']);
                })->when($datos['id_categoria'] ?? null, function ($query) use ($datos) {
                    $query->whereIn('inventario.id_categoria', $datos['id_categoria']);
                })->when($datos['estado'] ?? null, function ($query) use ($datos) {
                    $query->whereIn('inventario.estado', $datos['estado']);
                })->when($datos['id_usuario'] ?? null, function ($query) use ($datos) {
                    $query->where('inventario.id_user', $datos['id_usuario']);
                })
                // u.nombre se agrega al GROUP BY solo para satisfacer ONLY_FULL_GROUP_BY: es
                // funcionalmente dependiente de id_user (join 1:1 por PK), no cambia los grupos.
                ->groupBy('inventario.id_user', 'inventario.id_area', 'inventario.descripcion', 'inventario.id_categoria', 'u.nombre')
                ->when($sort === 'usuario', function ($query) use ($dir) {
                    $query->orderBy('u.nombre', $dir);
                })
                ->when($sort === 'cantidad', function ($query) use ($dir) {
                    $query->orderByRaw("COUNT(inventario.id) {$dir}");
                })
                ->paginate($perPage);

            // convertir string a JSON real
            $listado->transform(function ($item) {
                $item->items = json_decode($item->items);

                // MariaDB no anida JSON_OBJECT() dentro de JSON_OBJECT(): 'ultimo_reporte' y,
                // dentro de este, 'solucion', llegan como strings JSON escapados en vez de
                // objetos anidados — un decode por cada nivel de anidamiento.
                foreach ($item->items as $articulo) {
                    if (is_string($articulo->ultimo_reporte ?? null)) {
                        $articulo->ultimo_reporte = json_decode($articulo->ultimo_reporte);
                    }
                    if (is_string($articulo->ultimo_reporte->solucion ?? null)) {
                        $articulo->ultimo_reporte->solucion = json_decode($articulo->ultimo_reporte->solucion);
                    }
                }

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
     * Listado consolidado: sin 'descripcion' agrupa por (usuario, área, descripción)
     * con cantidad; con 'descripcion' trae los ítems individuales de ese grupo.
     * @param array $filtros {id_usuario, id_area, id_categoria, tipo_categoria, estado, s, descripcion}
     * @return array
     */
    public function obtenerListadoConsolidado(array $filtros, int $perPage = 15)
    {
        try {
            $query = Inventario::query()
                ->leftJoin('estado as e', 'inventario.estado', '=', 'e.id')
                ->leftJoin('usuarios as u', 'inventario.id_user', '=', 'u.id_user')
                ->leftJoin('areas as a', 'inventario.id_area', '=', 'a.id')
                ->leftJoin('categoria as c', 'inventario.id_categoria', '=', 'c.id')
                ->where('inventario.activo', 1)
                ->whereNotIn('inventario.estado', [4, 5])
                ->when($filtros['id_usuario'] ?? null, fn ($q, $v) => $q->where('inventario.id_user', $v))
                ->when($filtros['id_area'] ?? null, fn ($q, $v) => $q->whereIn('inventario.id_area', $v))
                ->when($filtros['id_categoria'] ?? null, fn ($q, $v) => $q->whereIn('inventario.id_categoria', $v))
                ->when($filtros['tipo_categoria'] ?? null, fn ($q, $v) => $q->where('c.tipo_categoria', $v))
                ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->whereIn('inventario.estado', $v))
                ->when($filtros['s'] ?? null, function ($q, $s) {
                    $q->where(function ($q) use ($s) {
                        $q->where('inventario.descripcion', 'like', "%{$s}%")
                            ->orWhereRaw("CONCAT(u.nombre, ' ', u.apellido) LIKE ?", ["%{$s}%"])
                            ->orWhere('u.documento', 'like', "%{$s}%")
                            ->orWhere('c.nombre', 'like', "%{$s}%");
                    });
                });

            // Modo detalle: ítems sueltos de un grupo (query 2)
            if (!empty($filtros['descripcion'])) {
                $listado = $query
                    ->where('inventario.descripcion', $filtros['descripcion'])
                    ->select(
                        'inventario.*',
                        'e.nombre as estado_nombre',
                        DB::raw("CONCAT(u.nombre, ' ', u.apellido) as nom_user"),
                        'a.nombre as nom_area'
                    )
                    ->orderByDesc('inventario.id')
                    ->paginate($perPage);
            } else {
                // Modo agrupado (query 1): un solo grupo por (usuario, área, descripción).
                // estado_nombre/categoria_nombre = los del ítem más reciente del grupo.
                $listado = $query
                    ->select(
                        'inventario.id_user',
                        'inventario.id_area',
                        'inventario.descripcion',
                        DB::raw("CAST(SUBSTRING_INDEX(GROUP_CONCAT(inventario.id_categoria ORDER BY inventario.id DESC), ',', 1) AS UNSIGNED) as id_categoria"),
                        DB::raw("SUBSTRING_INDEX(GROUP_CONCAT(c.nombre ORDER BY inventario.id DESC), ',', 1) as categoria_nombre"),
                        DB::raw("SUBSTRING_INDEX(GROUP_CONCAT(e.nombre ORDER BY inventario.id DESC), ',', 1) as estado_nombre"),
                        DB::raw("CONCAT(u.nombre, ' ', u.apellido) as nom_user"),
                        'a.nombre as nom_area',
                        DB::raw('COUNT(inventario.id) as cantidad')
                    )
                    ->groupBy(
                        'inventario.id_user',
                        'inventario.id_area',
                        'inventario.descripcion',
                        'u.nombre',
                        'u.apellido',
                        'a.nombre'
                    )
                    ->orderByDesc(DB::raw('MAX(inventario.id)'))
                    ->paginate($perPage);
            }

            return [
                'error' => false,
                'data' => $listado,
                'message' => 'Listado de inventario obtenido',
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'data' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Items visibles de un grupo del listado consolidado (activo + estado válido).
     */
    private function itemsDeGrupo(string $descripcion, int $idArea, int $idUsuario)
    {
        return Inventario::where('descripcion', $descripcion)
            ->where('id_area', $idArea)
            ->where('id_user', $idUsuario)
            ->where('activo', 1)
            ->whereNotIn('estado', [4, 5]);
    }

    /**
     * Edita la descripción de todos los ítems de un grupo del listado consolidado.
     * @return array
     */
    public function editarDescripcionGrupo(string $descripcion, string $nuevaDescripcion, int $idArea, int $idUsuario): array
    {
        try {
            $query = $this->itemsDeGrupo($descripcion, $idArea, $idUsuario);

            if ($query->count() === 0) {
                return [
                    'error' => true,
                    'data' => null,
                    'message' => 'No se encontró el grupo de inventario con esos datos.',
                ];
            }

            $query->update(['descripcion' => $nuevaDescripcion]);

            return [
                'error' => false,
                'data' => ['descripcion' => $nuevaDescripcion, 'items_actualizados' => $query->count()],
                'message' => 'Descripción actualizada para el grupo de inventario.',
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'data' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Aumenta la cantidad de un grupo clonando las características de sus ítems.
     * @return array
     */
    public function incrementarCantidadGrupo(string $descripcion, int $idArea, int $idUsuario, int $cantidad): array
    {
        try {
            $template = $this->itemsDeGrupo($descripcion, $idArea, $idUsuario)
                ->orderByDesc('id')
                ->first();

            if (!$template) {
                return [
                    'error' => true,
                    'data' => null,
                    'message' => 'No se encontró el grupo de inventario con esos datos.',
                ];
            }

            $nuevos = DB::transaction(function () use ($template, $cantidad) {
                $creados = [];

                for ($i = 0; $i < $cantidad; $i++) {
                    $creados[] = Inventario::create([
                        'descripcion' => $template->descripcion,
                        'marca' => $template->marca,
                        'modelo' => $template->modelo,
                        'precio' => $template->precio,
                        'estado' => $template->estado,
                        'activo' => 1,
                        'fecha_compra' => $template->fecha_compra,
                        'observacion' => $template->observacion,
                        'id_user' => $template->id_user,
                        'id_area' => $template->id_area,
                        'id_categoria' => $template->id_categoria,
                        'codigo' => $template->codigo,
                        'id_compra' => $template->id_compra,
                        'detalles' => $template->detalles,
                    ]);
                }

                $this->registrarLog($creados, $template->estado, null, $template->id_area);

                return $creados;
            });

            return [
                'error' => false,
                'data' => $nuevos,
                'message' => "Se agregaron {$cantidad} ítem(s) al grupo de inventario.",
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'data' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Disminuye la cantidad de un grupo descontinuando los ítems más antiguos.
     * @return array
     */
    public function disminuirCantidadGrupo(string $descripcion, int $idArea, int $idUsuario, int $cantidad, ?int $idLog): array
    {
        try {
            $ids = $this->itemsDeGrupo($descripcion, $idArea, $idUsuario)
                ->orderBy('id')
                ->limit($cantidad)
                ->pluck('id')
                ->all();

            if (empty($ids)) {
                return [
                    'error' => true,
                    'data' => null,
                    'message' => 'No se encontró el grupo de inventario con esos datos.',
                ];
            }

            if (count($ids) < $cantidad) {
                return [
                    'error' => true,
                    'data' => null,
                    'message' => "El grupo solo tiene {$this->itemsDeGrupo($descripcion, $idArea, $idUsuario)->count()} ítem(s) disponibles, no se pueden descontinuar {$cantidad}.",
                ];
            }

            return $this->descontinuarInventario($ids, $idLog);
        } catch (\Exception $e) {
            return [
                'error' => true,
                'data' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Actualiza los datos básicos de un ítem (descripcion/marca/modelo/precio/fecha_compra).
     * No toca estado/área/usuario: eso sigue gestionado por liberar/asignar/reportar/descontinuar.
     * @param int $id
     * @param array $data
     * @return array{data: array|null, error: bool, message: string}
     */
    public function actualizarInventario(int $id, array $data): array
    {
        try {
            $inventario = Inventario::find($id);

            if (!$inventario) {
                return [
                    'error' => true,
                    'data' => null,
                    'message' => 'No se encontró el ítem de inventario.',
                ];
            }

            $inventario->update($data);

            return [
                'error' => false,
                'data' => $inventario->fresh()->toArray(),
                'message' => 'Inventario actualizado correctamente',
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'data' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Historial completo de un ítem: reportes y mantenimientos preventivos
     * (tipo_reporte 1 y 2 respectivamente) por separado, incluyendo los ya
     * resueltos con su solución — a diferencia de mostrarReportesDeInventario(),
     * que solo trae pendientes (para la bandeja de reportes).
     * @param int $idInventario
     * @return array{data: array{item: Inventario, reportes: array, mantenimientos: array}|null, error: bool, message: string}
     */
    public function historialInventario(int $idInventario): array
    {
        try {
            $item = Inventario::with([
                'usuario:id_user,nombre,apellido',
                'area:id,nombre',
                'categoria:id,nombre',
            ])->find($idInventario);

            if (!$item) {
                return [
                    'error' => true,
                    'data' => null,
                    'message' => 'No se encontró el artículo de inventario.',
                ];
            }

            $query = fn (int $tipoReporte) => Reportes::where('id_inventario', $idInventario)
                ->where('tipo_reporte', $tipoReporte)
                ->whereNull('id_reporte') // solo reportes originales, no las filas de solución
                ->with([
                    'solucion',
                    'usuario:id_user,nombre,apellido',
                    'responsable:id_user,nombre,apellido',
                    'area:id,nombre',
                ])
                ->orderByDesc('fechareg')
                ->get();

            return [
                'error' => false,
                'data' => [
                    'item' => $item,
                    'reportes' => $query(1),
                    'mantenimientos' => $query(2),
                ],
                'message' => 'Historial obtenido correctamente.',
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'data' => null,
                'message' => $e->getMessage(),
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
                $destinatarios = $this->mailTo;

                foreach ($result['data'] as $inv) {
                    $contenido .= "- {$inv->descripcion} (Código: {$inv->codigo})\n";

                    $responsable = Usuario::find($inv->id_user)?->correo;
                    $ultimoReporte = $inv->reportes()->latest('id')->first();
                    $reportador = $ultimoReporte?->id_user ? Usuario::find($ultimoReporte->id_user)?->correo : null;

                    foreach (array_filter([$responsable, $reportador]) as $correo) {
                        $destinatarios[] = $correo;
                    }
                }

                $this->mailService->sendGeneric(array_values(array_unique($destinatarios)), $titulo, $contenido);
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

            $resultado = DB::transaction(function () use ($ids, $id_log, $descripcion, $id_anio, $id_periodo) {

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

            if (!$resultado['error']) {
                $reportador = Usuario::find($id_log)?->correo;

                foreach ($resultado['data'] as $item) {
                    $responsable = Usuario::find($item->id_user)?->correo;
                    $titulo = "Notificación | Inventario Reportado";
                    $contenido = "Se ha reportado el inventario:\n\n- {$item->descripcion} (Código: {$item->codigo})\n\nDescripción: {$descripcion}";
                    $this->mailService->sendGeneric($this->destinatarios($responsable, $reportador), $titulo, $contenido);
                }
            }

            return $resultado;
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
        ?int $per_page,
        ?int $tipo_reporte = null,
        ?bool $sin_solucion = false,
        ?int $id_categoria = null,
        ?string $estado_solucion = null
    ): array {
        try {

            $esSolucionado = $estado_solucion === 'solucionado';

            $query = DB::table('inventario as iv')
                ->join('reportes as rp', 'rp.id_inventario', '=', 'iv.id')
                ->leftJoin('usuarios as u', 'u.id_user', '=', 'iv.id_user')
                ->leftJoin('areas as ar', 'ar.id', '=', 'rp.id_area')
                ->leftJoin('categoria as c', 'c.id', '=', 'iv.id_categoria')
                ->leftJoin('anio_escolar as ae', 'ae.id', '=', 'rp.id_anio')
                ->where('iv.activo', 1)
                ->when($esSolucionado, function ($q) {
                    $q->whereNotIn('iv.estado', [4, 5])
                        ->whereNull('rp.id_reporte')
                        ->whereExists(function ($query) {
                            $query->select(DB::raw(1))
                                ->from('reportes as rpe')
                                ->whereColumn('rpe.id_reporte', 'rp.id')
                                ->where('rpe.estado', 3);
                        });
                }, function ($q) {
                    $q->where('iv.estado', 2)
                        ->where('rp.estado', 2)
                        ->whereNotExists(function ($query) {
                            $query->select(DB::raw(1))
                                ->from('reportes as rpe')
                                ->whereColumn('rpe.id_reporte', 'rp.id')
                                ->where('rpe.estado', 3);
                        });
                })
                ->select(
                    'iv.*',
                    DB::raw("(SELECT e.nombre FROM estado e WHERE e.id = iv.estado) AS nom_estado"),
                    DB::raw("(SELECT a.nombre FROM areas a WHERE a.id = iv.id_area) AS AREA"),
                    DB::raw("(SELECT CONCAT(u2.nombre, ' ', u2.apellido) FROM usuarios u2 WHERE u2.id_user = rp.id_user) AS usuario"),
                    DB::raw("(SELECT r.fechareg FROM reportes r WHERE r.id_inventario = iv.id AND r.estado = 2 ORDER BY r.id DESC LIMIT 1) AS fecha_reporte"),
                    DB::raw("(SELECT r.id FROM reportes r WHERE r.id_inventario = iv.id ORDER BY r.id DESC LIMIT 1) AS id_reporte"),
                    DB::raw("CONCAT(u.nombre, ' ', u.apellido) AS nom_usuario"),
                    DB::raw("CONCAT(ae.anio_inicio, ' - ', ae.anio_fin) AS anio_escolar"),
                    'c.tipo_categoria',
                    'c.nombre as nom_categoria',
                    'ar.nombre as nom_area',
                    'rp.id as reporte_id'
                )
                ->distinct()
                ->when(!empty($id_inventario), function ($q) use ($id_inventario) {
                    $q->whereIn('iv.id', $id_inventario);
                })
                ->when($id_user, function ($q) use ($id_user) {
                    $q->where('rp.id_user', $id_user);
                })
                ->when($id_anio, function ($q) use ($id_anio) {
                    $q->where('rp.id_anio', $id_anio);
                })
                ->when($id_periodo, function ($q) use ($id_periodo) {
                    $q->where('rp.periodo', $id_periodo);
                })
                ->when(!is_null($estado), function ($q) use ($estado) {
                    $q->where('rp.estado', $estado);
                })
                ->when($tipo_reporte, function ($q) use ($tipo_reporte) {
                    $q->where('rp.tipo_reporte', $tipo_reporte);
                })
                ->when($id_categoria, function ($q) use ($id_categoria) {
                    $q->where('iv.id_categoria', $id_categoria);
                })
                ->when($tipo_categoria, function ($q) use ($tipo_categoria) {
                    $q->where('c.tipo_categoria', $tipo_categoria);
                })
                ->when($search, function ($q) use ($search) {
                    $q->where(function ($query) use ($search) {
                        $query->where('iv.id', 'like', "%{$search}%")
                            ->orWhere('iv.codigo', 'like', "%{$search}%")
                            ->orWhere('iv.descripcion', 'like', "%{$search}%")
                            ->orWhere('iv.marca', 'like', "%{$search}%")
                            ->orWhere('iv.modelo', 'like', "%{$search}%")
                            ->orWhere('rp.id', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(u.nombre, ' ', u.apellido) LIKE ?", ["%{$search}%"]);
                    });
                })
                ->orderByDesc('fecha_reporte');

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
            $resultado = DB::transaction(function () use ($id_reporte, $id_resp, $fecha_respuesta, $descripcion) {

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

            if (!$resultado['error']) {
                $reporteFresco = Reportes::with('inventario.usuario')->find($id_reporte);
                $responsable = $reporteFresco?->inventario?->usuario?->correo;
                $reportador = Usuario::find($reporteFresco?->id_user)?->correo;
                $titulo = "Notificación | Reporte Solucionado";
                $contenido = "Se ha solucionado el reporte #{$id_reporte} del inventario \"{$reporteFresco?->inventario?->descripcion}\" (Código: {$reporteFresco?->inventario?->codigo}).\n\nSolución: {$descripcion}\n\nEn caso de no recibir nuevamente el reporte de este inventario se tomará como satisfecha la solución al reporte.";
                $this->mailService->sendGeneric($this->destinatarios($responsable, $reportador), $titulo, $contenido);
            }

            return $resultado;
        } catch (\Throwable $e) {

            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => []
            ];
        }
    }

    /**
     * Programa mantenimientos preventivos para los inventarios dados. A cada
     * inventario se le asigna una fecha aleatoria dentro de [fecha_inicio, fecha_fin],
     * en día hábil (lunes a viernes) y entre 07:30 y 15:45.
     *
     * @param bool $conSolucion Si es true, además crea la solución del mantenimiento
     *                          en la misma fecha indicada + 45 minutos.
     */
    public function programarMantenimientoPreventivo(
        array $ids,
        string $fecha_inicio,
        string $fecha_fin,
        int $id_log,
        string $descripcion,
        ?int $id_anio,
        ?int $periodo,
        bool $conSolucion = false
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

            if (is_null($idAnio)) {
                $ultimoAnioEscolar = Anio::where('activo', 1)->latest('id')->first();

                if (!$ultimoAnioEscolar) {
                    return [
                        'error' => true,
                        'message' => 'No existe un año escolar registrado.',
                        'data' => []
                    ];
                }

                $idAnio = $ultimoAnioEscolar->id;
            }

            DB::transaction(function () use (
                $inventarios,
                $fecha_inicio,
                $fecha_fin,
                $id_log,
                $descripcion,
                $idAnio,
                $periodo,
                $conSolucion
            ) {
                $inicio = Carbon::parse($fecha_inicio);
                $fin = Carbon::parse($fecha_fin);

                foreach ($inventarios as $inventario) {
                    $fecha = $this->fechaMantenimientoAleatoria($inicio, $fin);

                    $reporte = Reportes::create([
                        'id_inventario' => $inventario->id,
                        'id_area' => $inventario->id_area,
                        'observacion' => 'Mantenimiento Preventivo',
                        'estado' => 6,
                        'id_user' => $inventario->id_user,
                        'id_log' => $id_log,
                        'id_resp' => $inventario->id_user,
                        'tipo_reporte' => 2,
                        'descripcion' => $descripcion,
                        'id_anio' => $idAnio,
                        'periodo' => $periodo,
                        'fechareg' => $fecha,
                    ]);

                    $inventario->update([
                        'estado' => 6,
                        'observacion' => $descripcion,
                    ]);

                    if ($conSolucion) {
                        Reportes::create([
                            'id_reporte' => $reporte->id,
                            'id_inventario' => $inventario->id,
                            'id_area' => $inventario->id_area,
                            'observacion' => 'Mantenimiento Preventivo Realizado',
                            'estado' => 3,
                            'id_user' => $inventario->id_user,
                            'id_log' => $id_log,
                            'id_resp' => $id_log,
                            'tipo_reporte' => 2,
                            'descripcion' => $descripcion,
                            'fecha_respuesta' => $fecha->copy()->addMinutes(45),
                            'id_anio' => $idAnio,
                            'periodo' => $periodo,
                        ]);
                    }
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

    /**
     * Fecha aleatoria dentro de [inicio, fin], día hábil (L-V) y hora entre 07:30 y 15:45.
     */
    private function fechaMantenimientoAleatoria(Carbon $inicio, Carbon $fin): Carbon
    {
        $dias = [];
        $cursor = $inicio->copy()->startOfDay();

        while ($cursor->lte($fin)) {
            if ($cursor->isWeekday()) {
                $dias[] = $cursor->copy();
            }
            $cursor->addDay();
        }

        if (empty($dias)) {
            $dias[] = $inicio->copy();
        }

        $dia = $dias[array_rand($dias)];
        $minuto = random_int(0, 495); // 07:30 → 15:45 (495 minutos de rango)

        return $dia->setTime(7, 30)->addMinutes($minuto);
    }
}
