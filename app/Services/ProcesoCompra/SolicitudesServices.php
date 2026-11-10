<?php

namespace App\Services\ProcesoCompra;

use App\Services\Usuarios\PermisosPerfil;
use App\Enums\Mails;
use App\Mail\ProcesoCompra\OrdenCompraMail;
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
use App\Support\Permiso;
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
        return Usuario::correosPorPerfilesYNivel(PermisosPerfil::perfilesCon(Permiso::COORDINACION_NIVEL), $idNivel, soloActivos: true);
    }

    /**
     * Suma el grupo COMPRAS_VENTAS de `correos_institucionales` a los destinatarios, sin repetidos.
     *
     * @param  array<int, string>  $correos
     * @return array<int, string>
     */
    private function conComprasVentas(array $correos): array
    {
        return array_values(array_unique([...$correos, ...Mails::COMPRAS_VENTAS->recipients()]));
    }

    // No bloqueante a propósito: un correo caído no debe impedir crear/gestionar la solicitud.
    private function notificarNuevaSolicitud(SolicitudInicial $solicitud): void
    {
        $correos = $this->conComprasVentas($this->coordinadoresDeNivel($solicitud->usuario?->id_nivel));

        if (empty($correos)) {
            return;
        }

        $nombreSolicitante = trim(($solicitud->usuario?->nombre ?? '').' '.($solicitud->usuario?->apellido ?? ''));

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

            if (! $solicitud) {
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

            if (! empty($filtros['id_user'])) {
                $query->where('id_user', $filtros['id_user']);
            }

            if (! empty($filtros['id_area'])) {
                $query->where('id_area', $filtros['id_area']);
            }

            if (! empty($filtros['id_nivel'])) {
                $query->whereHas('usuario', fn ($u) => $u->where('id_nivel', $filtros['id_nivel']));
            }

            if (! empty($filtros['perfil'])) {
                $query->whereHas('usuario', fn ($u) => $u->where('perfil', $filtros['perfil']));
            }

            if (isset($filtros['estado']) && $filtros['estado'] !== null && $filtros['estado'] !== '') {
                $query->where('estado', (int) $filtros['estado']);
            }

            if (! empty($filtros['fecha_desde'])) {
                $query->whereDate('fecha_solicitud', '>=', $filtros['fecha_desde']);
            }

            if (! empty($filtros['fecha_hasta'])) {
                $query->whereDate('fecha_solicitud', '<=', $filtros['fecha_hasta']);
            }

            if (! empty($filtros['s'])) {
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

            if (! $solicitud) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            return ['error' => false, 'message' => 'Solicitud encontrada', 'data' => $solicitud];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Filtro "tipo" del seguimiento → área de la solicitud (32 = O20 Asistente administrativa, 85 = O13 Oficina de sistemas). */
    private const AREA_POR_TIPO_SEGUIMIENTO = ['operativo' => 32, 'sistemas' => 85];

    // Seguimiento de compra: todas las solicitudes formalizadas no anuladas (tabla `solicitudes`),
    // ordenadas por fecha de solicitud más reciente primero.
    // $filtros: fecha_desde/fecha_hasta/id_user/s (todos abiertos a Compras) e
    // id_nivel/perfil (el controller solo los pasa cuando quien pide es Super Admin/Admin).
    // tipo: operativo/sistemas → área fija de la solicitud (AREA_POR_TIPO_SEGUIMIENTO).
    public function listarSeguimiento(array $filtros = []): array
    {
        try {
            $query = Solicitud::with([
                'area:id,nombre',
                'usuario:id_user,nombre,apellido',
                // aprobador y datos de contacto del proveedor: los usa el PDF de orden de compra;
                // detalle_producto, el buscador del seguimiento.
                'aprobador:id_user,nombre,apellido',
                'proveedor:id_proveedor,nombre,num_identificacion,comercial_nombre,telefono_comercial,ciudad,detalle_producto,correo,correo_comercial',
                'productos',
                'verificacion:id_solicitud,factura_doc,id_log,fecha_verificacion,cantidad,observacion_cant,calidad,observacion_calidad,precios,observacion_precios,plazos,observacion_plazo',
                'verificacion.usuario:id_user,nombre,apellido',
            ])
                ->where('anulada', 0);

            if (isset(self::AREA_POR_TIPO_SEGUIMIENTO[$filtros['tipo'] ?? ''])) {
                $query->where('id_area', self::AREA_POR_TIPO_SEGUIMIENTO[$filtros['tipo']]);
            }

            if (! empty($filtros['id_user'])) {
                $query->where('id_user', $filtros['id_user']);
            }

            if (! empty($filtros['id_nivel'])) {
                $query->whereHas('usuario', fn ($u) => $u->where('id_nivel', $filtros['id_nivel']));
            }

            if (! empty($filtros['perfil'])) {
                $query->whereHas('usuario', fn ($u) => $u->where('perfil', $filtros['perfil']));
            }

            if (! empty($filtros['fecha_desde'])) {
                $query->whereDate('fecha_solicitud', '>=', $filtros['fecha_desde']);
            }

            if (! empty($filtros['fecha_hasta'])) {
                $query->whereDate('fecha_solicitud', '<=', $filtros['fecha_hasta']);
            }

            if (! empty($filtros['s'])) {
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
            $inventariosPorCompra = Inventario::select('id', 'id_compra', 'detalles', 'descripcion', 'estado', 'id_area', 'id_user')
                ->with(['area:id,nombre', 'usuario:id_user,nombre,apellido'])
                ->whereIn('id_compra', $solicitudes->pluck('id'))
                ->orderBy('id')
                ->get()
                ->groupBy('id_compra');

            // Ingresos del sistema legacy: `agregar_inv_product` guarda por compra+producto
            // la cantidad ya ingresada (`consumo`), sin `detalles` en `inventario`.
            $legacyPorCompra = DB::table('agregar_inv_product')
                ->select('id_compra', 'id_producto', DB::raw('SUM(consumo) as total'))
                ->whereIn('id_compra', $solicitudes->pluck('id'))
                ->groupBy('id_compra', 'id_producto')
                ->get()
                ->groupBy('id_compra');

            $solicitudes->each(function (Solicitud $solicitud) use ($inventariosPorCompra, $legacyPorCompra) {
                $solicitud->setAttribute('fecha_mostrar', $solicitud->fecha_aplazado ?? $solicitud->fecha_solicitud);
                $solicitud->setAttribute('url_cotizacion', $this->fileStorage->url($solicitud->cotizacion_doc));
                $solicitud->setAttribute('url_factura', $this->fileStorage->url($solicitud->verificacion?->factura_doc));

                // Cuántas unidades de cada producto ya entraron al inventario y sus ids,
                // para saber cuántas restan y cuáles filas se crearon en el seguimiento.
                $inventarios = $inventariosPorCompra->get($solicitud->id, collect());

                // Todo lo asignado a esta compra (flujo nuevo y legacy) para la tabla "Inventario Asignado".
                $solicitud->setAttribute('inventarios', $inventarios->map(fn ($i) => [
                    'id' => $i->id,
                    'descripcion' => $i->descripcion,
                    'estado' => $i->estado,
                    'area' => $i->area?->nombre,
                    'usuario' => $i->usuario ? trim("{$i->usuario->nombre} {$i->usuario->apellido}") : null,
                ])->values());

                $legacy = $legacyPorCompra->get($solicitud->id, collect())->keyBy('id_producto');

                $solicitud->productos->each(function (SolicitudProducto $producto) use ($inventarios, $legacy) {
                    // Flujo nuevo: `detalles` empieza por el id del producto (Inventario::detalleEsDeProducto).
                    // Legacy: sin `detalles`, se asocia por descripción igual al nombre del producto.
                    $desc = mb_substr($producto->producto, 0, 200);
                    $delProducto = $inventarios->filter(fn ($i) => Inventario::detalleEsDeProducto($i->detalles, $producto->id));
                    $ids = $inventarios->filter(fn ($i) => Inventario::detalleEsDeProducto($i->detalles, $producto->id)
                        || (($i->detalles === null || $i->detalles === '') && $i->descripcion === $desc))->pluck('id');
                    $producto->setAttribute('ingresado', $delProducto->count() + (int) ($legacy->get($producto->id)->total ?? 0));
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

            if (! $solicitud) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            $solicitud->update(['anulada' => 1, 'id_log' => $idLog]);

            return ['error' => false, 'message' => 'Solicitud anulada correctamente'];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Envía la orden de compra (PDF generado por el frontend) al proveedor asignado.
     * Los destinatarios salen del proveedor en BD (`correo` y `correo_comercial`), nunca
     * del body. Solo formalizada o en devolución (reenvío), con todos los productos con
     * cantidad y precio > 0. El correo lleva como contacto (y responder-a) el grupo
     * COMPRAS_VENTAS. No guarda nada: el envío no deja registro en la solicitud.
     */
    public function enviarOrdenCompra(int $id, UploadedFile $pdf): array
    {
        try {
            $solicitud = Solicitud::with([
                'proveedor:id_proveedor,nombre,correo,correo_comercial',
                'productos:id,id_solicitud,cantidad,precio',
            ])->find($id);

            if (! $solicitud || $solicitud->anulada) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            if (! in_array((int) $solicitud->estado, [self::ESTADO_FORMALIZADA, self::ESTADO_DEVOLUCION], true)) {
                return ['error' => true, 'message' => 'Solo se puede enviar la orden de una solicitud formalizada o en devolución', 'status' => 422];
            }

            if (! $solicitud->proveedor) {
                return ['error' => true, 'message' => 'La solicitud no tiene proveedor asignado', 'status' => 422];
            }

            // En BD cantidad y precio son strings y el precio puede ser null.
            $positivo = fn ($valor): bool => is_numeric($valor) && (float) $valor > 0;
            $productosInvalidos = $solicitud->productos->contains(
                fn (SolicitudProducto $p) => ! $positivo($p->cantidad) || ! $positivo($p->precio)
            );

            if ($solicitud->productos->isEmpty() || $productosInvalidos) {
                return ['error' => true, 'message' => 'Todos los productos deben tener cantidad y precio mayores a 0 (revise el estudio)', 'status' => 422];
            }

            $correos = array_values(array_unique(array_filter(
                [trim((string) $solicitud->proveedor->correo), trim((string) $solicitud->proveedor->correo_comercial)],
                fn (string $correo) => filter_var($correo, FILTER_VALIDATE_EMAIL) !== false,
            )));

            if (empty($correos)) {
                return ['error' => true, 'message' => 'El proveedor no tiene un correo válido registrado', 'status' => 422];
            }

            $numero = str_pad((string) $solicitud->id, 3, '0', STR_PAD_LEFT);
            $enviado = $this->mailService->send($correos, new OrdenCompraMail(
                $numero,
                (string) $solicitud->proveedor->nombre,
                $pdf->get(),
                Mails::COMPRAS_VENTAS->recipients(),
            ));

            if (! $enviado) {
                return ['error' => true, 'message' => 'No se pudo enviar el correo al proveedor', 'status' => 502];
            }

            return ['error' => false, 'message' => 'Orden de compra enviada a '.implode(', ', $correos), 'data' => ['correos' => $correos]];
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

            if (! $inicial) {
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

            if (! $inicial) {
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

            if (! $solicitud) {
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

            if (! $solicitud) {
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
            // Con `id_solicitud` (la final) se edita esa misma: las solicitudes del legacy no
            // tienen solicitud inicial, y tomar su id como el de una inicial apuntaba a otra.
            $existente = ! empty($datos['id_solicitud']) ? Solicitud::find($datos['id_solicitud']) : null;
            $inicial = $existente
                ? ($existente->id_solicitud_inicial ? SolicitudInicial::with('productos')->find($existente->id_solicitud_inicial) : null)
                : SolicitudInicial::with('productos')->find($idSolicitudInicial);

            if (! $existente && ! $inicial) {
                return ['error' => true, 'message' => 'Solicitud no encontrada', 'status' => 404];
            }

            $existente ??= Solicitud::where('id_solicitud_inicial', $inicial->id)->first();
            $subida = null;
            $nombreCotizacion = $existente?->cotizacion_doc;
            if ($cotizacion) {
                $subida = $this->fileStorage->uploadFile($cotizacion, self::CARPETA_COTIZACION);
                if ($subida['error'] ?? false) {
                    return ['error' => true, 'message' => $subida['message'] ?? 'No se pudo guardar la cotización'];
                }
                $nombreCotizacion = $subida['nombre_guardado'];
            } elseif (! $nombreCotizacion) {
                return ['error' => true, 'message' => 'Debe adjuntar la cotización'];
            }

            try {
                return DB::transaction(function () use ($inicial, $existente, $datos, $nombreCotizacion, $idLog) {
                    // La final ya fue creada al registrar la solicitud; se completa/edita aquí.
                    $solicitud = $existente;
                    // Datos base: los de la inicial o, en las del legacy (sin inicial), los de la final.
                    $base = $inicial ?? $solicitud;

                    $observacion = $datos['observaciones'] ?? $base->observacion;
                    $fechaSolicitud = $datos['fecha_solicitado'] ?? $base->fecha_solicitud;

                    // Estado decidido en el estudio: aprobada/pendiente -> activo 1,
                    // aplazada -> activo 10 + fecha, rechazada -> activo 0 + motivo. Una ya
                    // cerrada, en devolución o en stock (2, 3, 4) conserva su estado al editarla.
                    $estadoFinal = $solicitud && in_array((int) $solicitud->estado, [2, 3, 4], true)
                        ? (int) $solicitud->estado
                        : self::ESTADO_FORMALIZADA;
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
                        'id_user' => $base->id_user,
                        'id_area' => $base->id_area,
                        'fecha_solicitud' => $fechaSolicitud,
                        'justificacion' => $datos['justificacion'] ?? $base->justificacion,
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

                    $this->guardarProductosDelEstudio($solicitud, $datos['productos'] ?? [], $idLog);

                    $inicial?->update([
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
                if (! empty($subida['ruta'])) {
                    $this->fileStorage->eliminar($subida['ruta']);
                }

                return ['error' => true, 'message' => $e->getMessage()];
            }
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Productos editados en el estudio: con `id` se actualizan (descripción, cantidad, precio,
     * IVA); sin `id` se crean. Lo que no llega se conserva (el borrador de eliminados es solo
     * del frontend). Un producto con unidades ya en el inventario no puede bajar de esa
     * cantidad ni cambiar de descripción: rompería el restante y el vínculo de las compras
     * legacy, que se asocian por descripción. Se llama dentro de la transacción: una excepción
     * revierte todo.
     *
     * @param  array<int, array{id?: ?int, producto: string, cantidad: int|string, precio?: ?string, iva?: ?string}>  $productos
     */
    private function guardarProductosDelEstudio(Solicitud $solicitud, array $productos, int $idLog): void
    {
        foreach ($productos as $p) {
            $campos = [
                'producto' => trim($p['producto']),
                'cantidad' => (string) (int) $p['cantidad'],
                'precio' => $p['precio'] ?? null,
                'iva' => $p['iva'] ?? null,
            ];

            if (empty($p['id'])) {
                SolicitudProducto::create($campos + ['id_solicitud' => $solicitud->id, 'id_log' => $idLog]);

                continue;
            }

            $producto = SolicitudProducto::where('id_solicitud', $solicitud->id)->find($p['id']);
            if (! $producto) {
                throw new Exception("El producto #{$p['id']} no pertenece a esta solicitud");
            }

            $ingresado = Inventario::unidadesIngresadas($solicitud->id, $producto->id);
            $conInventarioLegacy = Inventario::where('id_compra', $solicitud->id)
                ->where(fn ($q) => $q->whereNull('detalles')->orWhere('detalles', ''))
                ->where('descripcion', mb_substr((string) $producto->producto, 0, 200))
                ->exists();

            if ((int) $campos['cantidad'] < $ingresado) {
                throw new Exception("\"{$producto->producto}\" ya tiene {$ingresado} unidad(es) en el inventario: la cantidad no puede ser menor.");
            }
            if (($ingresado > 0 || $conInventarioLegacy) && $campos['producto'] !== trim((string) $producto->producto)) {
                throw new Exception("\"{$producto->producto}\" ya tiene unidades en el inventario: no se puede cambiar su descripción.");
            }

            $producto->update($campos);
        }
    }

    // Convención heredada: activo=10 -> aplazada, activo=0 -> rechazada (estado se mantiene en 1).
    private const ACTIVO_APLAZADA = 10;

    private const ACTIVO_RECHAZADA = 0;

    public function aplazar(int $id, string $fechaAplazado, int $idLog): array
    {
        try {
            $solicitud = Solicitud::find($id);

            if (! $solicitud) {
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

            if (! $solicitud) {
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

            if (! $solicitud) {
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
            } elseif (! $nombreFactura) {
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
                if (! empty($subida['ruta'])) {
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

        $correos = $this->conComprasVentas($correos);

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
