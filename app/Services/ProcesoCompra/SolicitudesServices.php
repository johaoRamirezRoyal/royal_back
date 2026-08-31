<?php

namespace App\Services\ProcesoCompra;

use App\Models\Inventario\Inventario;
use App\Models\ProcesoCompra\Solicitudes\Solicitud;
use App\Models\ProcesoCompra\Solicitudes\SolicitudInicial;
use App\Models\ProcesoCompra\Solicitudes\SolicitudProducto;
use App\Models\ProcesoCompra\Solicitudes\SolicitudProductoInicial;
use App\Models\ProcesoCompra\Solicitudes\SolicitudVerificacion;
use App\Models\ProcesoCompra\Solicitudes\SolicitudVerificacionInicial;
use App\Models\Usuarios\Usuario;
use App\Services\FileStorageService;
use App\Services\MailService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class SolicitudesServices
{
    public const ESTADO_PENDIENTE = 0;
    public const ESTADO_APROBADA = 1;
    public const ESTADO_DEVUELTA = 2;
    public const ESTADO_RECHAZADA = 3;
    public const ESTADO_CONVERTIDA = 4;
    public const ESTADO_CANCELADA = 5;

    // Estados de la solicitud final (tabla `solicitudes`):
    public const ESTADO_PENDIENTE_GESTION = 0;
    public const ESTADO_FORMALIZADA = 1;
    public const ESTADO_CERRADA = 2;
    public const ESTADO_DEVOLUCION = 3;
    public const ESTADO_DISPONIBLE_STOCK = 4;

    private const DECISIONES = [
        'aprobar' => self::ESTADO_APROBADA,
        'devolver' => self::ESTADO_DEVUELTA,
        'rechazar' => self::ESTADO_RECHAZADA,
    ];

    // Mismo perfil Coordinador (26) que ya usa EvaluacionesServices::PERFIL_COORDINADOR —
    // un coordinador queda a cargo de todas las solicitudes de usuarios de su id_nivel.
    private const PERFIL_COORDINADOR = 26;

    // Los archivos se guardan en la raíz del disco de uploads para que su URL
    // pública sea directa: /public/upload/<nombre_archivo>.
    private const CARPETA_COTIZACION = '';
    private const CARPETA_FACTURA = '';

    public function __construct(
        private FileStorageService $fileStorage,
        private MailService $mailService,
    ) {}

    public function crear(array $datos, array $productos, int $idUser, int $idLog): array
    {
        try {
            $solicitud = DB::transaction(function () use ($datos, $productos, $idUser, $idLog) {
                $solicitud = SolicitudInicial::create([
                    'id_user' => $idUser,
                    'id_area' => $datos['id_area'],
                    'fecha_solicitud' => $datos['fecha_solicitud'] ?? now()->toDateString(),
                    'justificacion' => $datos['justificacion'],
                    'iva' => $datos['iva'] ?? null,
                    'estado' => self::ESTADO_PENDIENTE,
                    'id_log' => $idLog,
                ]);

                foreach ($productos as $producto) {
                    SolicitudProductoInicial::create([
                        'id_solicitud' => $solicitud->id,
                        'producto' => $producto['producto'],
                        'cantidad' => $producto['cantidad'],
                        'precio' => $producto['precio'] ?? null,
                        'iva' => $producto['iva'] ?? null,
                        'id_log' => $idLog,
                    ]);
                }

                return $solicitud;
            });

            $solicitud->load(['usuario', 'productos']);
            $this->notificarNuevaSolicitud($solicitud);

            return [
                'error' => false,
                'message' => 'Solicitud creada correctamente',
                'data' => $solicitud,
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Correos de los coordinadores activos a cargo del nivel dado (puede no haber ninguno). */
    private function coordinadoresDeNivel(?int $idNivel): array
    {
        if (!$idNivel) {
            return [];
        }

        return Usuario::where('perfil', self::PERFIL_COORDINADOR)
            ->where('id_nivel', $idNivel)
            ->where('estado', 'activo')
            ->pluck('correo')
            ->filter()
            ->all();
    }

    // No bloqueante a propósito: un correo caído no debe impedir crear/gestionar la solicitud.
    private function notificarNuevaSolicitud(SolicitudInicial $solicitud): void
    {
        $correos = $this->coordinadoresDeNivel($solicitud->usuario?->id_nivel);

        if (empty($correos)) {
            return;
        }

        $nombreSolicitante = trim(($solicitud->usuario?->nombre ?? '') . ' ' . ($solicitud->usuario?->apellido ?? ''));

        // La vista `emails.generic` ya escapa el contenido completo (`e()`) y convierte
        // saltos de línea con nl2br() — texto plano acá, sin HTML propio.
        $this->mailService->sendGeneric(
            $correos,
            'Nueva solicitud de compra para aprobar',
            "{$nombreSolicitante} registró una nueva solicitud de compra (No. {$solicitud->id}) que requiere tu aprobación.\nJustificación: {$solicitud->justificacion}",
        );
    }

    // Bandeja de "mis solicitudes": todo lo que el propio usuario ha pedido, con el
    // vínculo a la solicitud final (si el coordinador ya la aprobó) para que el frontend
    // pueda mostrar el estado real de gestión, no solo el de aprobación.
    public function misSolicitudes(int $idUser): array
    {
        try {
            $solicitudes = SolicitudInicial::with(['productos', 'solicitudFinal'])
                ->where('id_user', $idUser)
                ->orderByDesc('id')
                ->get();

            return ['error' => false, 'message' => 'Solicitudes obtenidas correctamente', 'data' => $solicitudes];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // El propio solicitante puede cancelar su solicitud mientras el coordinador no la
    // haya decidido — una vez aprobada/rechazada, ya no es reversible desde acá.
    public function cancelar(int $idSolicitudInicial, int $idUser, int $idLog): array
    {
        try {
            $solicitud = SolicitudInicial::find($idSolicitudInicial);

            if (!$solicitud) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            if ($solicitud->id_user !== $idUser) {
                return ['error' => true, 'message' => 'No puedes cancelar una solicitud de otro usuario', 'status' => 403];
            }

            if ($solicitud->estado !== self::ESTADO_PENDIENTE) {
                return ['error' => true, 'message' => 'La solicitud ya fue decidida por el coordinador y no se puede cancelar', 'status' => 422];
            }

            $solicitud->update([
                'estado' => self::ESTADO_CANCELADA,
                'observacion' => 'Cancelada por el solicitante',
                'id_log' => $idLog,
            ]);

            return [
                'error' => false,
                'message' => 'Solicitud cancelada correctamente',
                'data' => $solicitud->fresh()->load(['productos']),
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function listar(array $filtros = [], int $perPage = 10): array
    {
        try {
            $query = SolicitudInicial::with(['usuario:id_user,nombre,apellido,documento,correo,telefono,perfil', 'productos', 'verificacionInicial'])
                ->orderByDesc('id');

            if (!empty($filtros['id_user'])) {
                $query->where('id_user', $filtros['id_user']);
            }

            if (!empty($filtros['id_area'])) {
                $query->where('id_area', $filtros['id_area']);
            }

            if (!empty($filtros['id_nivel'])) {
                $query->whereHas('usuario', fn ($u) => $u->where('id_nivel', $filtros['id_nivel']));
            }

            if (!empty($filtros['perfil'])) {
                $query->whereHas('usuario', fn ($u) => $u->where('perfil', $filtros['perfil']));
            }

            if (isset($filtros['estado']) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
                $query->where('estado', (int) $filtros['estado']);
            }

            if (!empty($filtros['fecha_desde'])) {
                $query->whereDate('fecha_solicitud', '>=', $filtros['fecha_desde']);
            }

            if (!empty($filtros['fecha_hasta'])) {
                $query->whereDate('fecha_solicitud', '<=', $filtros['fecha_hasta']);
            }

            if (!empty($filtros['s'])) {
                $search = $filtros['s'];
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                        ->orWhere('id_user', $search)
                        ->orWhereHas('usuario', fn ($u) => $u->where('nombre', 'like', "%{$search}%")
                            ->orWhere('documento', 'like', "%{$search}%"))
                        ->orWhereHas('productos', fn ($p) => $p->where('producto', 'like', "%{$search}%"));
                });
            }

            return ['error' => false, 'message' => 'Solicitudes obtenidas correctamente', 'data' => $query->paginate($perPage)];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function ver(int $id): array
    {
        try {
            $solicitud = SolicitudInicial::with(['usuario', 'productos', 'verificacionInicial'])->find($id);

            if (!$solicitud) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            return ['error' => false, 'message' => 'Solicitud encontrada', 'data' => $solicitud];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // Seguimiento de compra: todas las solicitudes formalizadas no anuladas (tabla `solicitudes`),
    // ordenadas por fecha de solicitud más reciente primero.
    // $filtros: fecha_desde/fecha_hasta/id_user/s (todos abiertos a Compras) e
    // id_nivel/perfil (el controller solo los pasa cuando quien pide es Super Admin/Admin).
    public function listarSeguimiento(array $filtros = []): array
    {
        try {
            $query = Solicitud::with([
                'area:id,nombre',
                'usuario:id_user,nombre,apellido',
                'proveedor:id_proveedor,nombre',
                'productos',
                'verificacion:id_solicitud,factura_doc,id_log,fecha_verificacion,cantidad,observacion_cant,calidad,observacion_calidad,precios,observacion_precios,plazos,observacion_plazo',
                'verificacion.usuario:id_user,nombre,apellido',
            ])
                ->where('anulada', 0);

            if (!empty($filtros['id_user'])) {
                $query->where('id_user', $filtros['id_user']);
            }

            if (!empty($filtros['id_nivel'])) {
                $query->whereHas('usuario', fn ($u) => $u->where('id_nivel', $filtros['id_nivel']));
            }

            if (!empty($filtros['perfil'])) {
                $query->whereHas('usuario', fn ($u) => $u->where('perfil', $filtros['perfil']));
            }

            if (!empty($filtros['fecha_desde'])) {
                $query->whereDate('fecha_solicitud', '>=', $filtros['fecha_desde']);
            }

            if (!empty($filtros['fecha_hasta'])) {
                $query->whereDate('fecha_solicitud', '<=', $filtros['fecha_hasta']);
            }

            if (!empty($filtros['s'])) {
                $search = $filtros['s'];
                $query->where(function ($q) use ($search) {
                    $q->where('id', $search)
                        ->orWhereHas('usuario', fn ($u) => $u->where('nombre', 'like', "%{$search}%")
                            ->orWhere('documento', 'like', "%{$search}%"))
                        ->orWhereHas('productos', fn ($p) => $p->where('producto', 'like', "%{$search}%"));
                });
            }

            $solicitudes = $query
                ->orderByDesc('fecha_solicitud')
                ->orderByDesc('id')
                ->get();

            // Todas las entradas de inventario ya generadas por estas compras, precargadas
            // en una sola query y agrupadas por id_compra (evita una query por solicitud).
            $inventariosPorCompra = Inventario::select('id', 'id_compra', 'detalles')
                ->whereIn('id_compra', $solicitudes->pluck('id'))
                ->whereNotNull('detalles')
                ->get()
                ->groupBy('id_compra');

            $solicitudes->each(function (Solicitud $solicitud) use ($inventariosPorCompra) {
                $solicitud->setAttribute('fecha_mostrar', $solicitud->fecha_aplazado ?? $solicitud->fecha_solicitud);
                $solicitud->setAttribute('url_cotizacion', $this->fileStorage->url($solicitud->cotizacion_doc));
                $solicitud->setAttribute('url_factura', $this->fileStorage->url($solicitud->verificacion?->factura_doc));

                // Cuántas unidades de cada producto ya entraron al inventario y sus ids,
                // para saber cuántas restan y cuáles filas se crearon en el seguimiento.
                $inventarios = $inventariosPorCompra->get($solicitud->id, collect());

                $solicitud->productos->each(function (SolicitudProducto $producto) use ($inventarios) {
                    $ids = $inventarios->where('detalles', (string) $producto->id)->pluck('id');
                    $producto->setAttribute('ingresado', $ids->count());
                    $producto->setAttribute('inventario_ids', $ids->values());
                });
            });

            return ['error' => false, 'message' => 'Solicitudes obtenidas correctamente', 'data' => $solicitudes];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // Anula una solicitud: la oculta del seguimiento sin borrar sus datos.
    public function anular(int $id, int $idLog): array
    {
        try {
            $solicitud = Solicitud::find($id);

            if (!$solicitud) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            $solicitud->update(['anulada' => 1, 'id_log' => $idLog]);

            return ['error' => false, 'message' => 'Solicitud anulada correctamente'];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // El coordinador aprueba una solicitud de su nivel: recién acá se materializa la fila
    // final en `solicitudes` (visible para Compras) — antes de esto Compras no ve nada.
    public function aprobar(int $idSolicitudInicial, ?string $observacion, int $idLog): array
    {
        try {
            $inicial = SolicitudInicial::with('productos', 'usuario')->find($idSolicitudInicial);

            if (!$inicial) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            if ($inicial->estado !== self::ESTADO_PENDIENTE) {
                return ['error' => true, 'message' => 'La solicitud ya fue decidida', 'status' => 422];
            }

            $solicitud = DB::transaction(function () use ($inicial, $observacion, $idLog) {
                $inicial->update([
                    'estado' => self::ESTADO_APROBADA,
                    'observacion' => $observacion,
                    'id_log' => $idLog,
                ]);

                return $this->crearSolicitudFinalDesdeInicial(
                    $inicial,
                    ['estado' => self::ESTADO_PENDIENTE_GESTION, 'activo' => 1],
                    $idLog,
                );
            });

            return [
                'error' => false,
                'message' => 'Solicitud aprobada correctamente',
                'data' => $solicitud->load(['usuario', 'productos']),
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // El coordinador rechaza una solicitud de su nivel: no se crea fila final, Compras
    // nunca la ve.
    public function rechazarInicial(int $idSolicitudInicial, string $motivo, int $idLog): array
    {
        try {
            $inicial = SolicitudInicial::find($idSolicitudInicial);

            if (!$inicial) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            if ($inicial->estado !== self::ESTADO_PENDIENTE) {
                return ['error' => true, 'message' => 'La solicitud ya fue decidida', 'status' => 422];
            }

            $inicial->update([
                'estado' => self::ESTADO_RECHAZADA,
                'observacion' => $motivo,
                'id_log' => $idLog,
            ]);

            return [
                'error' => false,
                'message' => 'Solicitud rechazada correctamente',
                'data' => $inicial->fresh()->load(['usuario', 'productos']),
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // Compras decide que la solicitud se resuelve con stock propio, sin iniciar compra.
    public function marcarDisponibleStock(int $id, int $idLog): array
    {
        try {
            $solicitud = Solicitud::find($id);

            if (!$solicitud) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            if ($solicitud->estado !== self::ESTADO_PENDIENTE_GESTION || $solicitud->id_proveedor) {
                return ['error' => true, 'message' => 'La solicitud ya tiene un proceso de compra en curso', 'status' => 422];
            }

            $solicitud->update(['estado' => self::ESTADO_DISPONIBLE_STOCK, 'id_log' => $idLog]);

            return [
                'error' => false,
                'message' => 'Solicitud marcada como disponible en stock',
                'data' => $solicitud->fresh()->load(['usuario', 'productos']),
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Copia la solicitud inicial + sus productos a la tabla final `solicitudes`. */
    private function crearSolicitudFinalDesdeInicial(SolicitudInicial $inicial, array $camposExtra, int $idLog): Solicitud
    {
        $solicitud = Solicitud::create([
            'id_solicitud_inicial' => $inicial->id,
            'id_user' => $inicial->id_user,
            'id_area' => $inicial->id_area,
            'fecha_solicitud' => $inicial->fecha_solicitud,
            'justificacion' => $inicial->justificacion,
            'id_log' => $idLog,
            ...$camposExtra,
        ]);

        foreach ($inicial->productos as $producto) {
            SolicitudProducto::create([
                'id_solicitud' => $solicitud->id,
                'producto' => $producto->producto,
                'cantidad' => $producto->cantidad,
                'precio' => $producto->precio,
                'iva' => null,
                'id_log' => $idLog,
            ]);
        }

        return $solicitud;
    }

    public function verificar(int $idSolicitud, array $datos, int $idLog): array
    {
        try {
            $solicitud = SolicitudInicial::find($idSolicitud);

            if (!$solicitud) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            $verificacion = SolicitudVerificacionInicial::firstOrNew(['id_solicitud' => $idSolicitud]);
            $verificacion->fill([
                'cantidad' => $datos['cantidad'],
                'observacion_cant' => $datos['observacion_cant'] ?? null,
                'calidad' => $datos['calidad'],
                'observacion_calidad' => $datos['observacion_calidad'] ?? null,
                'precios' => $datos['precios'],
                'observacion_precios' => $datos['observacion_precios'] ?? null,
                'plazos' => $datos['plazos'],
                'observacion_plazo' => $datos['observacion_plazo'] ?? null,
                'id_log' => $idLog,
                'fecha_verificacion' => now()->toDateString(),
            ]);
            $verificacion->save();

            $solicitud->update([
                'estado' => self::DECISIONES[$datos['decision']],
                'observacion' => $datos['observacion'] ?? null,
                'id_log' => $idLog,
            ]);

            return [
                'error' => false,
                'message' => 'Solicitud verificada correctamente',
                'data' => $solicitud->fresh()->load(['usuario', 'productos', 'verificacionInicial']),
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // La cotización es opcional al editar: si llega, se reemplaza; si no, se conserva la existente.
    public function asignarProveedor(int $idSolicitudInicial, array $datos, ?UploadedFile $cotizacion, int $idLog): array
    {
        try {
            $inicial = SolicitudInicial::with('productos')->find($idSolicitudInicial);

            if (!$inicial) {
                return ['error' => true, 'message' => 'Solicitud inicial no encontrada', 'status' => 404];
            }

            $existente = Solicitud::where('id_solicitud_inicial', $inicial->id)->first();

            $subida = null;
            $nombreCotizacion = $existente?->cotizacion_doc;
            if ($cotizacion) {
                $subida = $this->fileStorage->uploadFile($cotizacion, self::CARPETA_COTIZACION);
                if ($subida['error'] ?? false) {
                    return ['error' => true, 'message' => $subida['message'] ?? 'No se pudo guardar la cotización'];
                }
                $nombreCotizacion = $subida['nombre_guardado'];
            } elseif (!$nombreCotizacion) {
                return ['error' => true, 'message' => 'Debe adjuntar la cotización'];
            }

            try {
                return DB::transaction(function () use ($inicial, $datos, $nombreCotizacion, $idLog) {
                    // La final ya fue creada al registrar la solicitud; se completa/edita aquí.
                    $solicitud = Solicitud::where('id_solicitud_inicial', $inicial->id)->first();

                    $observacion = $datos['observaciones'] ?? $inicial->observacion;
                    $fechaSolicitud = $datos['fecha_solicitado'] ?? $inicial->fecha_solicitud;

                    // Estado decidido en el estudio: aprobada/pendiente -> activo 1,
                    // aplazada -> activo 10 + fecha, rechazada -> activo 0 + motivo.
                    $estadoFinal = self::ESTADO_FORMALIZADA;
                    $activo = 1;
                    $fechaAplazado = null;
                    $motivo = null;
                    if (($datos['estado'] ?? null) === 'aplazada') {
                        $activo = self::ACTIVO_APLAZADA;
                        $fechaAplazado = $datos['fecha_aplazado'] ?? null;
                    } elseif (($datos['estado'] ?? null) === 'rechazada') {
                        $activo = self::ACTIVO_RECHAZADA;
                        $motivo = $observacion;
                    }

                    $campos = [
                        'id_user' => $inicial->id_user,
                        'id_area' => $inicial->id_area,
                        'fecha_solicitud' => $fechaSolicitud,
                        'justificacion' => $inicial->justificacion,
                        'id_log' => $idLog,
                        'estado' => $estadoFinal,
                        'observacion' => $observacion,
                        'iva' => $datos['iva'] ?? null,
                        'id_proveedor' => $datos['id_proveedor'],
                        'activo' => $activo,
                        'fecha_aplazado' => $fechaAplazado,
                        'motivo' => $motivo,
                        'cotizacion_doc' => $nombreCotizacion,
                    ];

                    if ($solicitud) {
                        $solicitud->update($campos);
                    } else {
                        // Solicitudes iniciales aprobadas antes de este cambio, sin fila final
                        // todavía (o creadas bajo el legacy que no pasaba por aprobar()).
                        $solicitud = $this->crearSolicitudFinalDesdeInicial($inicial, $campos, $idLog);
                    }

                    // Precios e IVA decididos por producto en el estudio.
                    foreach (($datos['productos'] ?? []) as $p) {
                        SolicitudProducto::where('id_solicitud', $solicitud->id)
                            ->where('id', $p['id'])
                            ->update([
                                'precio' => $p['precio'] ?? null,
                                'iva' => $p['iva'] ?? null,
                            ]);
                    }

                    $inicial->update([
                        'estado' => self::ESTADO_CONVERTIDA,
                        'id_log' => $idLog,
                    ]);

                    $solicitud->load(['usuario', 'proveedor', 'productos']);
                    $solicitud->setAttribute('url_cotizacion', $this->fileStorage->url($solicitud->cotizacion_doc));

                    return [
                        'error' => false,
                        'message' => 'Proveedor asignado y solicitud formalizada correctamente',
                        'data' => $solicitud,
                    ];
                });
            } catch (Exception $e) {
                if (!empty($subida['ruta'])) {
                    $this->fileStorage->eliminar($subida['ruta']);
                }

                return ['error' => true, 'message' => $e->getMessage()];
            }
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // Convención heredada: activo=10 -> aplazada, activo=0 -> rechazada (estado se mantiene en 1).
    private const ACTIVO_APLAZADA = 10;
    private const ACTIVO_RECHAZADA = 0;

    public function aplazar(int $id, string $fechaAplazado, int $idLog): array
    {
        try {
            $solicitud = Solicitud::find($id);

            if (!$solicitud) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            $solicitud->update([
                'fecha_aplazado' => $fechaAplazado,
                'activo' => self::ACTIVO_APLAZADA,
                'id_log' => $idLog,
            ]);

            return ['error' => false, 'message' => 'Compra aplazada correctamente', 'data' => $solicitud->fresh()->load(['usuario', 'proveedor', 'productos'])];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function rechazar(int $id, array $datos, int $idLog): array
    {
        try {
            $solicitud = Solicitud::find($id);

            if (!$solicitud) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            $solicitud->update([
                'motivo' => $datos['motivo'],
                'observacion' => $datos['observacion'] ?? null,
                'fecha_aplazado' => null,
                'activo' => self::ACTIVO_RECHAZADA,
                'id_log' => $idLog,
            ]);

            return ['error' => false, 'message' => 'Compra rechazada correctamente', 'data' => $solicitud->fresh()->load(['usuario', 'proveedor', 'productos'])];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // HU-09: el receptor valida lo recibido en solicitud_verificacion y adjunta la factura.
    // Decisiones: cerrar -> estado 2 (cerrada), devolucion -> estado 3 (devolución).
    public function verificarEntrega(int $idSolicitud, array $datos, ?UploadedFile $factura, int $idLog): array
    {
        try {
            $solicitud = Solicitud::find($idSolicitud);

            if (!$solicitud) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            $existente = SolicitudVerificacion::where('id_solicitud', $solicitud->id)->first();

            $subida = null;
            $nombreFactura = $existente?->factura_doc;
            if ($factura) {
                $subida = $this->fileStorage->uploadFile($factura, self::CARPETA_FACTURA);
                if ($subida['error'] ?? false) {
                    return ['error' => true, 'message' => $subida['message'] ?? 'No se pudo guardar la factura'];
                }
                $nombreFactura = $subida['nombre_guardado'];
            } elseif (!$nombreFactura) {
                return ['error' => true, 'message' => 'Debe adjuntar la factura'];
            }

            try {
                $resultado = DB::transaction(function () use ($solicitud, $datos, $nombreFactura, $idLog) {
                    $verificacion = SolicitudVerificacion::firstOrNew(['id_solicitud' => $solicitud->id]);
                    $verificacion->fill([
                        'cantidad' => $datos['cantidad'],
                        'observacion_cant' => $datos['observacion_cant'] ?? null,
                        'calidad' => $datos['calidad'],
                        'observacion_calidad' => $datos['observacion_calidad'] ?? null,
                        'precios' => $datos['precios'],
                        'observacion_precios' => $datos['observacion_precios'] ?? null,
                        'plazos' => $datos['plazos'],
                        'observacion_plazo' => $datos['observacion_plazo'] ?? null,
                        'id_log' => $idLog,
                        'factura_doc' => $nombreFactura,
                        'fecha_verificacion' => $datos['fecha_verificacion'] ?? now()->toDateString(),
                    ]);
                    $verificacion->save();

                    $solicitud->update([
                        'estado' => $datos['decision'] === 'cerrar' ? self::ESTADO_CERRADA : self::ESTADO_DEVOLUCION,
                        'id_log' => $idLog,
                    ]);

                    return [
                        'error' => false,
                        'message' => 'Entrega verificada y compra '.($datos['decision'] === 'cerrar' ? 'cerrada' : 'en devolución').' correctamente',
                        'data' => $solicitud->fresh()->load(['usuario', 'proveedor', 'productos', 'verificacion']),
                    ];
                });

                $this->notificarCambioEstado($resultado['data'], $datos['decision'] === 'cerrar');

                return $resultado;
            } catch (Exception $e) {
                if (!empty($subida['ruta'])) {
                    $this->fileStorage->eliminar($subida['ruta']);
                }

                return ['error' => true, 'message' => $e->getMessage()];
            }
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // No bloqueante a propósito, igual que notificarNuevaSolicitud().
    private function notificarCambioEstado(Solicitud $solicitud, bool $cerrada): void
    {
        $solicitante = $solicitud->usuario;
        $correos = $this->coordinadoresDeNivel($solicitante?->id_nivel);

        if ($solicitante?->correo) {
            $correos[] = $solicitante->correo;
        }

        if (empty($correos)) {
            return;
        }

        $estadoTexto = $cerrada ? 'cerrada (entrega conforme)' : 'en devolución';

        $this->mailService->sendGeneric(
            $correos,
            'Actualización de tu solicitud de compra',
            "La solicitud de compra No. {$solicitud->id} fue marcada como {$estadoTexto}.",
        );
    }
}