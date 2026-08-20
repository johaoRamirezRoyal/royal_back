<?php

namespace App\Services\ProcesoCompra;

use App\Models\Inventario\Inventario;
use App\Models\ProcesoCompra\Solicitudes\Solicitud;
use App\Models\ProcesoCompra\Solicitudes\SolicitudInicial;
use App\Models\ProcesoCompra\Solicitudes\SolicitudProducto;
use App\Models\ProcesoCompra\Solicitudes\SolicitudProductoInicial;
use App\Models\ProcesoCompra\Solicitudes\SolicitudVerificacion;
use App\Models\ProcesoCompra\Solicitudes\SolicitudVerificacionInicial;
use App\Services\FileStorageService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SolicitudesServices
{
    public const ESTADO_PENDIENTE = 0;
    public const ESTADO_APROBADA = 1;
    public const ESTADO_DEVUELTA = 2;
    public const ESTADO_RECHAZADA = 3;
    public const ESTADO_CONVERTIDA = 4;

    // Estados de la solicitud final (tabla `solicitudes`):
    public const ESTADO_FORMALIZADA = 1;
    public const ESTADO_CERRADA = 2;
    public const ESTADO_DEVOLUCION = 3;

    private const DECISIONES = [
        'aprobar' => self::ESTADO_APROBADA,
        'devolver' => self::ESTADO_DEVUELTA,
        'rechazar' => self::ESTADO_RECHAZADA,
    ];

    // Los archivos se guardan en la raíz del disco de uploads para que su URL
    // pública sea directa: /public/upload/<nombre_archivo>.
    private const CARPETA_COTIZACION = '';
    private const CARPETA_FACTURA = '';

    // ponytail: sistemas/operativos se distinguen por id_area del solicitante; ajustar ids si cambia el catálogo de áreas.
    private const AREAS_SISTEMAS = [85, 41, 146];
    private const AREAS_OPERATIVOS = [32];

    public function __construct(private FileStorageService $fileStorage) {}
    public function crear(array $datos, array $productos, int $idUser, int $idLog): array
    {
        try {
            return DB::transaction(function () use ($datos, $productos, $idUser, $idLog) {
                $solicitud = SolicitudInicial::create([
                    'id_user' => $idUser,
                    'id_area' => $datos['id_area'],
                    'fecha_solicitud' => $datos['fecha_solicitud'] ?? now()->toDateString(),
                    'justificacion' => $datos['justificacion'],
                    'iva' => $datos['iva'] ?? null,
                    'estado' => 0,
                    'id_log' => $idLog,
                ]);

                // El legacy crea la solicitud formalizada en ambas tablas al registrar;
                // la inicial queda como borrador vinculado y la final (estado 0) se
                // completa luego en asignar-proveedor. El IVA no se guarda aún.
                $solicitudFinal = Solicitud::create([
                    'id_solicitud_inicial' => $solicitud->id,
                    'id_user' => $idUser,
                    'id_area' => $datos['id_area'],
                    'fecha_solicitud' => $datos['fecha_solicitud'] ?? now()->toDateString(),
                    'justificacion' => $datos['justificacion'],
                    'iva' => null,
                    'estado' => 0,
                    'activo' => 1,
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

                    SolicitudProducto::create([
                        'id_solicitud' => $solicitudFinal->id,
                        'producto' => $producto['producto'],
                        'cantidad' => $producto['cantidad'],
                        'precio' => $producto['precio'] ?? null,
                        'iva' => null,
                        'id_log' => $idLog,
                    ]);
                }

                return [
                    'error' => false,
                    'message' => 'Solicitud creada correctamente',
                    'data' => $solicitud->fresh()->load(['usuario', 'productos']),
                ];
            });
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function listar(array $filtros = [], int $perPage = 10): array
    {
        try {
            $query = SolicitudInicial::with(['usuario:id_user,nombre,apellido,documento', 'productos', 'verificacionInicial'])
                ->orderByDesc('id');

            if (!empty($filtros['id_user'])) {
                $query->where('id_user', $filtros['id_user']);
            }

            if (!empty($filtros['id_area'])) {
                $query->where('id_area', $filtros['id_area']);
            }

            if (!empty($filtros['tipo'])) {
                $areas = match ($filtros['tipo']) {
                    'sistemas' => self::AREAS_SISTEMAS,
                    'operativos' => self::AREAS_OPERATIVOS,
                    default => [],
                };
                if ($areas) {
                    $query->whereIn('id_area', $areas);
                }
            }

            if (!empty($filtros['id_nivel'])) {
                $query->whereHas('usuario', fn ($u) => $u->where('id_nivel', $filtros['id_nivel']));
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
    public function listarSeguimiento(): array
    {
        try {
            $solicitudes = Solicitud::with([
                'area:id,nombre',
                'usuario:id_user,nombre,apellido',
                'proveedor:id_proveedor,nombre',
                'productos',
                'verificacion:id_solicitud,factura_doc,id_log,fecha_verificacion,cantidad,observacion_cant,calidad,observacion_calidad,precios,observacion_precios,plazos,observacion_plazo',
                'verificacion.usuario:id_user,nombre,apellido',
            ])
                ->where('anulada', 0)
                ->orderByDesc('fecha_solicitud')
                ->orderByDesc('id')
                ->get()
                ->each(function (Solicitud $solicitud) {
                    $solicitud->setAttribute('fecha_mostrar', $solicitud->fecha_aplazado ?? $solicitud->fecha_solicitud);
                    $solicitud->setAttribute('url_cotizacion', $this->urlCotizacion($solicitud->cotizacion_doc));
                    $solicitud->setAttribute('url_factura', $this->urlCotizacion($solicitud->verificacion?->factura_doc));

                    // Cuántas unidades de cada producto ya entraron al inventario y sus ids,
                    // para saber cuántas restan y cuáles filas se crearon en el seguimiento.
                    $inventarios = Inventario::select('id', 'detalles')
                        ->where('id_compra', $solicitud->id)
                        ->whereNotNull('detalles')
                        ->get();

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
                        // Solicitudes iniciales creadas antes del vínculo: se crea la final.
                        $solicitud = Solicitud::create(['id_solicitud_inicial' => $inicial->id] + $campos);

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
                    $solicitud->setAttribute('url_cotizacion', $this->urlCotizacion($solicitud->cotizacion_doc));

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

    private function urlCotizacion(?string $nombre): ?string
    {
        if (!$nombre) {
            return null;
        }

        return Storage::disk(config('filesystems.uploads_disk', 'public'))->url($nombre);
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
                return DB::transaction(function () use ($solicitud, $datos, $nombreFactura, $idLog) {
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
}