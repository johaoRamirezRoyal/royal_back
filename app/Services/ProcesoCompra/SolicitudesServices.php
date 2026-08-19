<?php

namespace App\Services\ProcesoCompra;

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

    private const CARPETA_COTIZACION = 'solicitudes/cotizaciones';
    private const CARPETA_FACTURA = 'solicitudes/facturas';

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

    public function asignarProveedor(int $idSolicitudInicial, array $datos, UploadedFile $cotizacion, int $idLog): array
    {
        try {
            $inicial = SolicitudInicial::with('productos')->find($idSolicitudInicial);

            if (!$inicial) {
                return ['error' => true, 'message' => 'Solicitud inicial no encontrada', 'status' => 404];
            }

            if ($inicial->estado !== self::ESTADO_APROBADA) {
                return ['error' => true, 'message' => 'La solicitud debe estar aprobada para asignar proveedor', 'status' => 422];
            }

            $subida = $this->fileStorage->uploadFile($cotizacion, self::CARPETA_COTIZACION);
            if ($subida['error'] ?? false) {
                return ['error' => true, 'message' => $subida['message'] ?? 'No se pudo guardar la cotización'];
            }

            try {
                return DB::transaction(function () use ($inicial, $datos, $subida, $idLog) {
                    $solicitud = Solicitud::create([
                        'id_user' => $inicial->id_user,
                        'id_area' => $inicial->id_area,
                        'fecha_solicitud' => $inicial->fecha_solicitud,
                        'justificacion' => $inicial->justificacion,
                        'id_log' => $idLog,
                        'estado' => self::ESTADO_PENDIENTE,
                        'observacion' => $inicial->observacion,
                        'iva' => $datos['iva'] ?? null,
                        'id_proveedor' => $datos['id_proveedor'],
                        'activo' => 1,
                        'cotizacion_doc' => $subida['nombre_guardado'],
                    ]);

                    foreach ($inicial->productos as $producto) {
                        SolicitudProducto::create([
                            'id_solicitud' => $solicitud->id,
                            'producto' => $producto->producto,
                            'cantidad' => $producto->cantidad,
                            'precio' => $producto->precio,
                            'iva' => $producto->iva,
                            'id_log' => $idLog,
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
                $this->fileStorage->eliminar($subida['ruta']);

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

        return Storage::disk(config('filesystems.uploads_disk', 'public'))->url(self::CARPETA_COTIZACION.'/'.$nombre);
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
    public function verificarEntrega(int $idSolicitud, array $datos, UploadedFile $factura, int $idLog): array
    {
        try {
            $solicitud = Solicitud::find($idSolicitud);

            if (!$solicitud) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            $subida = $this->fileStorage->uploadFile($factura, self::CARPETA_FACTURA);
            if ($subida['error'] ?? false) {
                return ['error' => true, 'message' => $subida['message'] ?? 'No se pudo guardar la factura'];
            }

            try {
                return DB::transaction(function () use ($solicitud, $datos, $subida, $idLog) {
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
                        'factura_doc' => $subida['nombre_guardado'],
                        'fecha_verificacion' => now()->toDateString(),
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
                $this->fileStorage->eliminar($subida['ruta']);

                return ['error' => true, 'message' => $e->getMessage()];
            }
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}