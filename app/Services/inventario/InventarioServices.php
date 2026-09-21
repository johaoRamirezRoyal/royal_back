<?php

namespace App\Services\inventario;

use App\Enums\Mails;
use App\Models\AnioEscolar\Anio;
use App\Models\Areas\Areas;
use App\Models\Inventario\Categoria;
use App\Models\Inventario\Estado;
use App\Models\Inventario\Inventario;
use App\Models\Inventario\InventarioCheck;
use App\Models\Inventario\InventarioDescontinuado;
use App\Models\Inventario\InventarioLiberado;
use App\Models\Inventario\InventarioLog;
use App\Models\Inventario\Reportes;
use App\Models\ProcesoCompra\Solicitudes\Solicitud;
use App\Models\ProcesoCompra\Solicitudes\SolicitudProducto;
use App\Models\Usuarios\Usuario;
use App\Pdf\Inventario\MantenimientoChecklistPdfService;
use App\Services\branding\MarcaDominioService;
use App\Services\MailService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventarioServices
{
    public function __construct(
        private MailService $mailService,
    ) {}

    /**
     * Summary of mailTo
     * @var array
     */
    private array $mailTo = [
        'cronograma.sistemas@royalschool.edu.co'
    ];

    private function destinatarios(?string $responsableCorreo = null, ?string $reportadorCorreo = null, ?string $solucionadorCorreo = null): array
    {
        $destinatarios = $this->mailTo;
        foreach (array_filter([$responsableCorreo, $reportadorCorreo, $solucionadorCorreo]) as $correo) {
            $destinatarios[] = $correo;
        }
        return array_values(array_unique($destinatarios));
    }

    /**
     * Notifica un movimiento de Área Común (reportar daño, programar mantenimiento o
     * registrar check semestral) al responsable del bloque, al responsable del
     * inventario (`inventario.id_user` — no necesariamente la misma persona que el
     * responsable del bloque) y a Dirección Administrativa (`correos_institucionales`,
     * grupo `DIRECCION_ADMINISTRATIVA`, mismo mecanismo que usa Noticias —
     * `Mails::recipients()`). Ítems que no sean Área Común (`categoria.tipo_categoria`
     * != 3) se ignoran en silencio, así los tres callers (`reportarInventario`,
     * `programarMantenimientoPreventivo`, `registrarCheckInventario`) pueden pasar la
     * misma lista mixta de ítems que ya procesaron para el módulo general de
     * Inventario, sin filtrar antes.
     *
     * @param array<array{inventario: Inventario, fecha: ?Carbon}> $entradas
     */
    private function notificarAreaComun(array $entradas, string $movimiento, ?int $idActor): void
    {
        if (empty($entradas)) {
            return;
        }

        try {
            $actorNombre = InventarioEmailHelper::nombreUsuario($idActor);
            $direccionAdministrativa = Mails::DIRECCION_ADMINISTRATIVA->recipients();

            foreach ($entradas as $entrada) {
                $item = $entrada['inventario'];
                $item->loadMissing(['categoria', 'area.bloque', 'bloque', 'usuario']);

                if ((int) ($item->categoria?->tipo_categoria) !== 3) {
                    continue;
                }

                $bloque = $item->bloque ?? $item->area?->bloque;
                $responsablesArea = $bloque ? $bloque->responsables()->get() : collect();
                $responsableInventario = $item->usuario;
                $fecha = ($entrada['fecha'] ?? now())->format('d/m/Y H:i');

                $destinatarios = array_values(array_unique(array_filter(array_merge(
                    $responsablesArea->pluck('correo')->all(),
                    [$responsableInventario?->correo],
                    $direccionAdministrativa
                ))));

                if (empty($destinatarios)) {
                    continue;
                }

                $nombreResponsablesArea = $responsablesArea->isNotEmpty()
                    ? $responsablesArea->map(fn ($u) => trim("{$u->nombre} {$u->apellido}"))->implode(', ')
                    : '—';
                $nombreResponsableInventario = $responsableInventario
                    ? trim("{$responsableInventario->nombre} {$responsableInventario->apellido}")
                    : '—';

                $titulo = "Notificación | Área Común — {$movimiento}";
                $contenido = "Bloque: " . ($bloque?->nombre ?? '—') . "\n"
                    . "Área: " . ($item->area?->nombre ?? '—') . "\n"
                    . "Área común: {$item->descripcion}\n"
                    . "Responsable del área: {$nombreResponsablesArea}\n"
                    . "Responsable del inventario: {$nombreResponsableInventario}\n"
                    . "Movimiento: {$movimiento}\n"
                    . "Fecha del movimiento: {$fecha}\n"
                    . "Realizado por: " . ($actorNombre ?? '—');

                $this->mailService->sendGeneric($destinatarios, $titulo, $contenido);
            }
        } catch (\Throwable $e) {
            Log::error('No se notificó el movimiento de área común: ' . $e->getMessage());
        }
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

            // El GROUP_CONCAT de abajo arma un JSON con TODOS los ítems de cada grupo, y
            // MySQL trunca ese texto en seco al límite de `group_concat_max_len` (1024 bytes
            // por defecto en MySQL — algunos grupos, como el de liberados sin usuario
            // asignado, agrupan decenas de ítems y superan eso fácil). Un GROUP_CONCAT
            // truncado a mitad de un JSON_OBJECT rompe el JSON completo del grupo: el
            // json_decode() de más abajo devuelve null y el 'foreach' sobre null explota
            // como excepción, tumbando TODA la petición con "Respuesta inválida del
            // servidor" en el frontend — se vio primero en /inventario/liberado, pero
            // afecta a cualquier vista que use este mismo listado con grupos grandes.
            DB::statement('SET SESSION group_concat_max_len = 1000000');

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

            // Fecha en la que se descontinuó cada ítem, para el filtro por año en
            // /inventario/descontinuado — un mismo id_inventario puede tener más de un
            // registro en `inventario_desc` (se descontinuó, se reasignó, se volvió a
            // descontinuar), así que se toma la más reciente por ítem.
            $descontinuadoJoin = DB::raw("(
                SELECT id_inventario, MAX(fechareg) as fecha_descontinuacion
                FROM inventario_desc
                GROUP BY id_inventario
            ) as idesc");

            $listado = Inventario::select(
                'inventario.id_user',
                'inventario.id_area',
                'inventario.id_bloque',
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
                            'fecha_descontinuacion', idesc.fecha_descontinuacion,
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
                ->leftJoin('categoria as c', 'inventario.id_categoria', '=', 'c.id')
                ->leftJoin($ultimoReporteJoin, 'ur.id_inventario', '=', 'inventario.id')
                ->leftJoin($descontinuadoJoin, 'idesc.id_inventario', '=', 'inventario.id')
                // Sin este filtro, ítems lógicamente eliminados (activo=0) seguían apareciendo
                // en el listado — es lo que hacía que /inventario/liberado mostrara 52 grupos
                // en vez de los 2 reales (el legacy sí filtraba `activo=1`, ver reasignar.php).
                // Excepción: al descontinuar (estado 5) `descontinuarInventario` pone
                // `activo=0` a propósito (así se oculta de las vistas normales) — filtrar acá
                // por activo=1 dejaría /inventario/descontinuado sin poder mostrar NINGÚN
                // ítem descontinuado desde que existe esa lógica (confirmado: los 79 ítems
                // descontinuados en 2026 tienen todos activo=0). Por eso el filtro se salta
                // cuando se está pidiendo justo ese estado.
                ->when(!in_array(5, $datos['estado'] ?? []), function ($query) {
                    $query->where('inventario.activo', 1);
                })
                ->with([
                    'usuario:id_user,nombre,apellido',
                    'area:id,nombre',
                    'bloque:id,nombre',
                    'categoria:id,nombre,tipo_categoria'
                ])
                ->when($search, function ($query, $search) {
                    // El frontend usa este mismo filtro `s` tanto para la búsqueda libre por
                    // descripción como para "buscar por código" (findItemByCodigo, escaneo/
                    // ingreso manual del código físico del ítem) — sin el OR sobre
                    // `inventario.codigo`, un código válido nunca hace match (la descripción
                    // no lo contiene) y siempre responde "no se encontró", aunque el ítem exista.
                    // También sobre `inventario.id`: el "código" que ve el usuario en la UI
                    // (columna "Código" de Mis Inventarios, buscador de hoja de vida) es el
                    // id del ítem cuando no tiene código físico impreso — igual que en el
                    // legado (`historial.php`: `$codigo = $datos_articulo['id']` cuando el
                    // artículo no tiene código propio) — así que buscar por ese id debe
                    // encontrar el grupo, no solo por descripción/código físico.
                    $query->where(function ($q) use ($search) {
                        $q->where('inventario.descripcion', 'like', "%{$search}%")
                            ->orWhere('inventario.codigo', 'like', "%{$search}%")
                            ->orWhereRaw('CAST(inventario.id AS CHAR) LIKE ?', ["%{$search}%"]);
                    });
                })
                // Áreas Comunes: al filtrar por bloque (sin área puntual elegida), el
                // frontend manda id_area = áreas de ese bloque + id_bloque = [ese bloque],
                // para traer tanto lo asignado a un área del bloque como lo asignado
                // directo al bloque (id_area NULL) — de ahí el OR en vez de dos `when`
                // independientes, que se combinarían con AND.
                ->when(($datos['id_area'] ?? null) && ($datos['id_bloque'] ?? null), function ($query) use ($datos) {
                    $query->where(function ($q) use ($datos) {
                        $q->whereIn('inventario.id_area', $datos['id_area'])
                            ->orWhereIn('inventario.id_bloque', $datos['id_bloque']);
                    });
                })->when(($datos['id_area'] ?? null) && !($datos['id_bloque'] ?? null), function ($query) use ($datos) {
                    $query->whereIn('inventario.id_area', $datos['id_area']);
                })->when(!($datos['id_area'] ?? null) && ($datos['id_bloque'] ?? null), function ($query) use ($datos) {
                    $query->whereIn('inventario.id_bloque', $datos['id_bloque']);
                })->when($datos['id_categoria'] ?? null, function ($query) use ($datos) {
                    $query->whereIn('inventario.id_categoria', $datos['id_categoria']);
                })->when($datos['tipo_categoria'] ?? null, function ($query) use ($datos) {
                    $query->where('c.tipo_categoria', $datos['tipo_categoria']);
                })->when($datos['estado'] ?? null, function ($query) use ($datos) {
                    $query->whereIn('inventario.estado', $datos['estado']);
                })->when($datos['estado_not_in'] ?? null, function ($query) use ($datos) {
                    $query->whereNotIn('inventario.estado', $datos['estado_not_in']);
                })->when($datos['id_usuario'] ?? null, function ($query) use ($datos) {
                    $query->where('inventario.id_user', $datos['id_usuario']);
                })->when($datos['anio_descontinuado'] ?? null, function ($query) use ($datos) {
                    $query->whereYear('idesc.fecha_descontinuacion', $datos['anio_descontinuado']);
                })
                // u.nombre se agrega al GROUP BY solo para satisfacer ONLY_FULL_GROUP_BY: es
                // funcionalmente dependiente de id_user (join 1:1 por PK), no cambia los grupos.
                ->groupBy('inventario.id_user', 'inventario.id_area', 'inventario.id_bloque', 'inventario.descripcion', 'inventario.id_categoria', 'u.nombre')
                ->when($sort === 'usuario', function ($query) use ($dir) {
                    $query->orderBy('u.nombre', $dir);
                })
                ->when($sort === 'cantidad', function ($query) use ($dir) {
                    $query->orderByRaw("COUNT(inventario.id) {$dir}");
                })
                // Sin orden explícito no había ORDER BY (orden indefinido de la BD); por
                // defecto se ordena alfabéticamente por descripción del inventario.
                ->when(!in_array($sort, ['usuario', 'cantidad'], true), function ($query) use ($dir) {
                    $query->orderBy('inventario.descripcion', $dir);
                })
                ->paginate($perPage);

            // convertir string a JSON real
            $listado->transform(function ($item) {
                $item->items = json_decode($item->items) ?? [];

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
                ->leftJoin('bloques as b', 'inventario.id_bloque', '=', 'b.id')
                ->leftJoin('categoria as c', 'inventario.id_categoria', '=', 'c.id')
                ->where('inventario.activo', 1)
                ->whereNotIn('inventario.estado', [4, 5])
                ->when($filtros['id_usuario'] ?? null, fn ($q, $v) => $q->where('inventario.id_user', $v))
                // Áreas Comunes: id_bloque junto con id_area trae también lo asignado
                // directo al bloque (id_area NULL) — ver mismo patrón/comentario en
                // obtenerListadoInventario más arriba.
                ->when(($filtros['id_area'] ?? null) && ($filtros['id_bloque'] ?? null), function ($q) use ($filtros) {
                    $q->where(function ($q2) use ($filtros) {
                        $q2->whereIn('inventario.id_area', $filtros['id_area'])
                            ->orWhereIn('inventario.id_bloque', $filtros['id_bloque']);
                    });
                })
                ->when(($filtros['id_area'] ?? null) && !($filtros['id_bloque'] ?? null), fn ($q) => $q->whereIn('inventario.id_area', $filtros['id_area']))
                ->when(!($filtros['id_area'] ?? null) && ($filtros['id_bloque'] ?? null), fn ($q) => $q->whereIn('inventario.id_bloque', $filtros['id_bloque']))
                ->when($filtros['id_categoria'] ?? null, fn ($q, $v) => $q->whereIn('inventario.id_categoria', $v))
                ->when($filtros['tipo_categoria'] ?? null, fn ($q, $v) => $q->where('c.tipo_categoria', $v))
                ->when($filtros['estado'] ?? null, fn ($q, $v) => $q->whereIn('inventario.estado', $v))
                ->when($filtros['s'] ?? null, function ($q, $s) {
                    // El "código" que ve el usuario (columna "Código" de Mis Inventarios,
                    // buscador de hoja de vida) es el id del ítem cuando no tiene código
                    // físico impreso — igual que el legado (`historial.php`:
                    // `$codigo = $datos_articulo['id']`) — así que este buscador general
                    // también debe encontrar el grupo por código físico o por id, no solo
                    // por descripción/usuario/categoría/área.
                    $q->where(function ($q) use ($s) {
                        $q->where('inventario.descripcion', 'like', "%{$s}%")
                            ->orWhere('inventario.codigo', 'like', "%{$s}%")
                            ->orWhereRaw('CAST(inventario.id AS CHAR) LIKE ?', ["%{$s}%"])
                            ->orWhereRaw("CONCAT(u.nombre, ' ', u.apellido) LIKE ?", ["%{$s}%"])
                            ->orWhere('u.documento', 'like', "%{$s}%")
                            ->orWhere('c.nombre', 'like', "%{$s}%")
                            ->orWhere('a.nombre', 'like', "%{$s}%");
                    });
                });

            // Modo detalle: ítems sueltos de un grupo (query 2, usado por "Inspeccionar"),
            // o de TODOS los grupos que matcheen los filtros (query 2b, usado por la
            // exportación a Excel — bandera 'individual', sin restringir a una descripción).
            if (!empty($filtros['descripcion']) || !empty($filtros['individual'])) {
                $listado = $query
                    ->when(!empty($filtros['descripcion']), fn ($q) => $this->whereDescripcion($q, 'inventario.descripcion', $filtros['descripcion']))
                    ->select(
                        'inventario.*',
                        'e.nombre as estado_nombre',
                        DB::raw("CONCAT(u.nombre, ' ', u.apellido) as nom_user"),
                        'a.nombre as nom_area',
                        'b.nombre as nom_bloque',
                        'c.nombre as categoria_nombre',
                        // Áreas Comunes: último check semestral registrado (migración de
                        // chek_zonas) — ver InventarioServices::registrarCheckInventario.
                        DB::raw("(SELECT fechareg FROM inventario_check WHERE id_inventario = inventario.id ORDER BY id DESC LIMIT 1) AS ultimo_check"),
                        // `inventario_check.periodo` guarda el id real de `periodos` (no un
                        // ordinal) — se resuelve acá al `numero` legible ("I", "II"...) para
                        // no mostrar el id crudo en el frontend.
                        DB::raw("(SELECT p.numero FROM inventario_check ic JOIN periodos p ON p.id = ic.periodo WHERE ic.id_inventario = inventario.id ORDER BY ic.id DESC LIMIT 1) AS ultimo_check_periodo"),
                        DB::raw("(SELECT CONCAT(ae.anio_inicio, ' — ', ae.anio_fin) FROM inventario_check ic JOIN anio_escolar ae ON ae.id = ic.id_anio WHERE ic.id_inventario = inventario.id ORDER BY ic.id DESC LIMIT 1) AS ultimo_check_anio"),
                        // Ids crudos (id_anio real, periodos.id real) del último check —
                        // para que el frontend pueda comparar exacto contra el año/periodo
                        // elegido y decidir si "ya tiene check EN ESE año/periodo" (no
                        // "alguna vez tuvo un check"), sin parsear los textos ya formateados
                        // de arriba.
                        DB::raw("(SELECT id_anio FROM inventario_check WHERE id_inventario = inventario.id ORDER BY id DESC LIMIT 1) AS ultimo_check_anio_id"),
                        DB::raw("(SELECT periodo FROM inventario_check WHERE id_inventario = inventario.id ORDER BY id DESC LIMIT 1) AS ultimo_check_periodo_id"),
                        // Áreas Comunes: reporte correctivo y mantenimiento preventivo
                        // pendientes (sin solución), cada uno INDEPENDIENTE de
                        // `inventario.estado` — un ítem puede tener un mantenimiento
                        // pendiente (tipo_reporte=2) y, aparte, quedar reportado después
                        // (tipo_reporte=1), sin que reportarlo cancele el mantenimiento que
                        // ya tenía. `inventario.estado`/`observacion` solo reflejan la
                        // ÚLTIMA acción, por eso hace falta ir directo a `reportes` para
                        // saber si hay uno, el otro, o ambos a la vez — mismo criterio que
                        // `$idsConPendiente` en registrarCheckInventario.
                        DB::raw("(SELECT descripcion FROM reportes r WHERE r.id_inventario = inventario.id AND r.tipo_reporte = 1 AND r.estado = 2 AND r.id_reporte IS NULL AND NOT EXISTS (SELECT 1 FROM reportes sol WHERE sol.id_reporte = r.id AND sol.estado = 3) ORDER BY r.id DESC LIMIT 1) AS reporte_pendiente"),
                        DB::raw("(SELECT descripcion FROM reportes r WHERE r.id_inventario = inventario.id AND r.tipo_reporte = 2 AND r.estado = 6 AND r.id_reporte IS NULL AND NOT EXISTS (SELECT 1 FROM reportes sol WHERE sol.id_reporte = r.id AND sol.estado = 3) ORDER BY r.id DESC LIMIT 1) AS mantenimiento_pendiente"),
                        // Id real de la fila de `reportes` de cada pendiente — lo necesita
                        // solucionarReporte() (POST /api/inventario/reportes/solucionar) para
                        // saber CUÁL reporte se está resolviendo desde la tarjeta.
                        DB::raw("(SELECT id FROM reportes r WHERE r.id_inventario = inventario.id AND r.tipo_reporte = 1 AND r.estado = 2 AND r.id_reporte IS NULL AND NOT EXISTS (SELECT 1 FROM reportes sol WHERE sol.id_reporte = r.id AND sol.estado = 3) ORDER BY r.id DESC LIMIT 1) AS reporte_pendiente_id"),
                        DB::raw("(SELECT id FROM reportes r WHERE r.id_inventario = inventario.id AND r.tipo_reporte = 2 AND r.estado = 6 AND r.id_reporte IS NULL AND NOT EXISTS (SELECT 1 FROM reportes sol WHERE sol.id_reporte = r.id AND sol.estado = 3) ORDER BY r.id DESC LIMIT 1) AS mantenimiento_pendiente_id")
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
                        'inventario.id_bloque',
                        'inventario.descripcion',
                        DB::raw("CAST(SUBSTRING_INDEX(GROUP_CONCAT(inventario.id_categoria ORDER BY inventario.id DESC), ',', 1) AS UNSIGNED) as id_categoria"),
                        DB::raw("SUBSTRING_INDEX(GROUP_CONCAT(c.nombre ORDER BY inventario.id DESC), ',', 1) as categoria_nombre"),
                        DB::raw("SUBSTRING_INDEX(GROUP_CONCAT(e.nombre ORDER BY inventario.id DESC), ',', 1) as estado_nombre"),
                        DB::raw("CONCAT(u.nombre, ' ', u.apellido) as nom_user"),
                        'a.nombre as nom_area',
                        'b.nombre as nom_bloque',
                        DB::raw('COUNT(inventario.id) as cantidad')
                    )
                    ->groupBy(
                        'inventario.id_user',
                        'inventario.id_area',
                        'inventario.id_bloque',
                        'inventario.descripcion',
                        'u.nombre',
                        'u.apellido',
                        'a.nombre',
                        'b.nombre'
                    )
                    ->orderBy('inventario.descripcion')
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
     * Compara una columna de descripción ignorando espacios/tabs/saltos de línea sueltos
     * al inicio o fin. Los datos migrados del sistema legacy traen descripciones sucias
     * (algunas con \r\n de Windows al final — ver p.ej. los "Aire acondicionado #S..."),
     * y ni un '=' estricto ni el TRIM() de MySQL (que solo recorta espacios, no \r\n\t)
     * las matcheaban contra el valor ya limpio que llega en la petición (TrimStrings,
     * el middleware global de Laravel, sí recorta \r\n\t con el trim() de PHP). Esto hacía
     * que "Inspeccionar" y las acciones de grupo (editar descripción, incrementar/disminuir
     * cantidad) no encontraran ningún ítem para esos grupos.
     */
    private function whereDescripcion($query, string $columna, string $descripcion)
    {
        $normalizarSql = "TRIM(REPLACE(REPLACE(REPLACE({$columna}, '\\r', ''), '\\n', ''), '\\t', ''))";
        $normalizado = trim(str_replace(["\r", "\n", "\t"], '', $descripcion));

        return $query->whereRaw("{$normalizarSql} = ?", [$normalizado]);
    }

    /**
     * Items visibles de un grupo del listado consolidado (activo + estado válido).
     */
    private function itemsDeGrupo(string $descripcion, int $idArea, int $idUsuario)
    {
        return $this->whereDescripcion(Inventario::query(), 'descripcion', $descripcion)
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

            $checks = InventarioCheck::where('id_inventario', $idInventario)
                ->with([
                    'anioEscolar:id,anio_inicio,anio_fin',
                    'responsable:id_user,nombre,apellido',
                    // `periodo` es el id real de `periodos`, no un ordinal — se resuelve acá
                    // al `numero` legible ("I", "II"...) para la hoja de vida.
                    'periodoInfo:id,numero',
                ])
                ->orderByDesc('id')
                ->get();

            return [
                'error' => false,
                'data' => [
                    'item' => $item,
                    'reportes' => $query(1),
                    'mantenimientos' => $query(2),
                    'checks' => $checks,
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
     * Agrega al inventario los artículos de una solicitud de compra (tabla `solicitudes`).
     * Cada unidad = una fila en `inventario` (el inventario cuenta por filas). Valida que
     * la cantidad a ingresar no supere lo solicitado, descontando lo ya ingresado para esa
     * compra (se rastrea con `id_compra` = id de la solicitud y `detalles` = id del
     * `solicitud_productos`). Todo en una transacción.
     *
     * @param int $idSolicitud id de la solicitud final
     * @param array $articulos [{id_producto, cantidad, id_area, id_usuario, id_categoria, estado?, precio?, fecha_compra?}]
     * @param int $idLog usuario que ejecuta la acción
     * @return array{data: array|null, error: bool, message: string}
     */
    public function agregarArticulosAInventario(int $idSolicitud, array $articulos, int $idLog): array
    {
        try {
            $solicitud = Solicitud::find($idSolicitud);

            if (!$solicitud) {
                return [
                    'error' => true,
                    'data' => null,
                    'message' => 'Solicitud no encontrada',
                ];
            }

            $resumen = [];
            $ingresadoEnRequest = [];
            $creados = [];

            DB::transaction(function () use ($articulos, $idSolicitud, $idLog, &$resumen, &$ingresadoEnRequest, &$creados) {
                foreach ($articulos as $articulo) {
                    $producto = SolicitudProducto::where('id', $articulo['id_producto'])
                        ->where('id_solicitud', $idSolicitud)
                        ->first();

                    if (!$producto) {
                        throw new \Exception("El artículo #{$articulo['id_producto']} no pertenece a esta solicitud");
                    }

                    $disponible = (int) $producto->cantidad;
                    $yaIngresado = Inventario::where('id_compra', $idSolicitud)
                        ->where('detalles', (string) $producto->id)
                        ->count() + ($ingresadoEnRequest[$producto->id] ?? 0);

                    $cantidad = (int) $articulo['cantidad'];
                    $restante = $disponible - $yaIngresado;

                    if ($cantidad > $restante) {
                        throw new \Exception(
                            "El artículo \"{$producto->producto}\" ya tiene {$yaIngresado} de {$disponible} ingresado(s) al inventario; solo puede ingresar {$restante} más."
                        );
                    }

                    $estado = $articulo['estado'] ?? 1;
                    $precio = $articulo['precio'] ?? $producto->precio;
                    $fechaCompra = $articulo['fecha_compra'] ?? now()->toDateString();
                    $itemsArticulo = [];

                    for ($i = 0; $i < $cantidad; $i++) {
                        $itemsArticulo[] = Inventario::create([
                            'descripcion' => substr($producto->producto, 0, 200),
                            'marca' => $articulo['marca'] ?? null,
                            'modelo' => $articulo['modelo'] ?? null,
                            'precio' => $precio,
                            'estado' => $estado,
                            'activo' => 1,
                            'fecha_compra' => $fechaCompra,
                            'id_user' => $articulo['id_usuario'],
                            'id_area' => $articulo['id_area'],
                            'id_categoria' => $articulo['id_categoria'] ?? null,
                            'user_log' => $idLog,
                            'confirmado' => 1,
                            'id_compra' => $idSolicitud,
                            'detalles' => (string) $producto->id,
                        ]);
                    }

                    $this->registrarLog($itemsArticulo, $estado, $idLog, $articulo['id_area']);
                    $creados = array_merge($creados, $itemsArticulo);

                    $ingresadoEnRequest[$producto->id] = ($ingresadoEnRequest[$producto->id] ?? 0) + $cantidad;

                    $resumen[] = [
                        'id_producto' => $producto->id,
                        'producto' => $producto->producto,
                        'solicitado' => $disponible,
                        'ingresado' => $yaIngresado + $cantidad,
                        'restante' => $disponible - ($yaIngresado + $cantidad),
                    ];
                }
            });

            return [
                'error' => false,
                'data' => [
                    'articulos_creados' => count($creados),
                    'resumen' => $resumen,
                ],
                'message' => 'Artículos agregados al inventario correctamente',
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
    /**
     * Crea $cantidad filas idénticas (una unidad física = una fila, ver nota de
     * agregarArticulosAInventario) en una sola transacción — permite cargar varias
     * unidades del mismo artículo en un solo llamado en vez de repetir la petición.
     */
    public function agregarInventario(array $inventario, int $cantidad = 1)
    {
        try {
            $creados = DB::transaction(function () use ($inventario, $cantidad) {
                $items = [];
                for ($i = 0; $i < $cantidad; $i++) {
                    $items[] = Inventario::create($inventario);
                }

                $this->registrarLog($items, $inventario['estado'], null);

                return $items;
            });

            return [
                'error' => false,
                'data' => array_map(fn ($item) => $item->toArray(), $creados),
                'message' => count($creados) > 1
                    ? 'Se agregaron '.count($creados).' artículos al inventario'
                    : 'Inventario agregado',
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
     * Áreas Comunes: reclasifica ítems de inventario YA EXISTENTES hacia una
     * categoría de tipo_categoria=3 (Área Común). Solo cambia la categoría —
     * el frontend encuentra los ítems filtrando por bloque/área (igual que
     * `/inventario/listado`), así que cada ítem ya está en el área/bloque
     * correcto; no hace falta (ni conviene) reasignarlos todos a un único
     * destino, cada uno conserva su id_area/id_bloque actual.
     */
    public function reclasificarAreaComun(array $ids, int $idCategoria, int $idLog)
    {
        try {
            $categoria = Categoria::find($idCategoria);

            if (!$categoria || (int) $categoria->tipo_categoria !== 3) {
                return [
                    'error' => true,
                    'message' => 'La categoría seleccionada no es de Área Común',
                ];
            }

            $actualizados = Inventario::whereIn('id', $ids)->update([
                'id_categoria' => $idCategoria,
                'user_log' => $idLog,
            ]);

            return [
                'error' => false,
                'message' => "{$actualizados} ítem(s) reclasificado(s) como Área Común",
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Áreas Comunes: migración del "check" semestral de zonas del SAMI legacy
     * (chek_zonas) — certifica que un ítem fue revisado en un periodo
     * institucional. Se omite (no bloquea el resto del lote) cuando el ítem
     * tiene un reporte o mantenimiento preventivo GENUINAMENTE pendiente
     * (reportes.estado IN [2,6] SIN una solución ya vinculada — al
     * solucionar, `solucionarReporteInventario` no muta el estado del
     * reporte original, crea una fila nueva con `id_reporte` apuntando de
     * vuelta; el original se queda en estado 2/6 para siempre, así que hay
     * que descartar los que ya tienen esa fila de solución, mismo criterio
     * que ya usa `mostrarReportesDeInventario`), o cuando ya se registró un
     * check para ese mismo año Y ese mismo periodo puntual — un año puede
     * tener varios checks, uno por periodo, sin restricción contra cuál sea
     * el periodo institucional "vigente" en este momento.
     */
    public function registrarCheckInventario(array $ids, ?int $idAnio, ?int $periodo, int $idResponsable): array
    {
        try {
            $items = Inventario::whereIn('id', $ids)->get(['id', 'descripcion', 'id_area', 'id_bloque', 'id_categoria', 'id_user']);
            $marcados = [];
            $omitidos = [];

            $idsConPendiente = Reportes::whereIn('id_inventario', $ids)
                ->whereIn('estado', [2, 6])
                ->whereNull('id_reporte')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('reportes as sol')
                        ->whereColumn('sol.id_reporte', 'reportes.id')
                        ->where('sol.estado', 3);
                })
                ->pluck('id_inventario')
                ->unique()
                ->all();

            // `inventario_check.periodo`/`reportes.periodo` guardan el id real de la fila
            // de `periodos` (no un ordinal) — ver ReportarInventarioRequest. Un mismo año
            // puede tener varios checks, uno por periodo — el único duplicado real es el
            // mismo año Y el mismo periodo (chequeado más abajo, `$yaChequeado`); no hay
            // restricción contra el periodo institucional "vigente": si el año pedido
            // todavía no tiene check para ESE periodo puntual, se deja crear sin importar
            // cuál sea el periodo activo ahora mismo.
            foreach ($items as $item) {
                if (in_array($item->id, $idsConPendiente)) {
                    $omitidos[] = [
                        'id' => $item->id,
                        'descripcion' => $item->descripcion,
                        'motivo' => 'Tiene un reporte o mantenimiento pendiente',
                    ];
                    continue;
                }

                $yaChequeado = InventarioCheck::where('id_inventario', $item->id)
                    ->when($idAnio, fn ($q) => $q->where('id_anio', $idAnio))
                    ->when($periodo, fn ($q) => $q->where('periodo', $periodo))
                    ->exists();

                if ($yaChequeado) {
                    $omitidos[] = [
                        'id' => $item->id,
                        'descripcion' => $item->descripcion,
                        'motivo' => 'Ya se registró un check en este periodo',
                    ];
                    continue;
                }

                InventarioCheck::create([
                    'id_inventario' => $item->id,
                    'id_anio' => $idAnio,
                    'periodo' => $periodo,
                    'id_user' => $idResponsable,
                    'fechareg' => now(),
                ]);
                $marcados[] = $item->id;
            }

            $this->notificarAreaComun(
                $items->whereIn('id', $marcados)->map(fn ($item) => ['inventario' => $item, 'fecha' => null])->all(),
                'Check semestral registrado',
                $idResponsable
            );

            return [
                'error' => false,
                'data' => [
                    'marcados' => $marcados,
                    'omitidos' => $omitidos,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Áreas Comunes: mueve un ítem YA EXISTENTE a otro bloque/área — a diferencia
     * de reclasificarAreaComun (que solo cambia la categoría), esto cambia la
     * ubicación real del ítem. `$idArea` null = queda asignado directo al bloque.
     */
    public function moverItemAreaComun(int $id, int $idBloque, ?int $idArea, int $idLog): array
    {
        try {
            $item = Inventario::find($id);

            if (!$item) {
                return ['error' => true, 'message' => 'Ítem no encontrado'];
            }

            if ($idArea !== null) {
                $area = Areas::find($idArea);
                if (!$area || (int) $area->id_bloque !== $idBloque) {
                    return ['error' => true, 'message' => 'El área seleccionada no pertenece a ese bloque'];
                }
            }

            $item->update([
                'id_bloque' => $idBloque,
                'id_area' => $idArea,
                'user_log' => $idLog,
            ]);

            return ['error' => false, 'message' => 'Ítem movido correctamente'];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Áreas Comunes: historial COMPLETO de checks semestrales (no solo el
     * "último check" que ya trae obtenerListadoConsolidado) — cada fila de
     * `inventario_check`, filtrable por ítem/bloque/área/año/periodo/responsable.
     * Sirve tanto para "historial de un ítem" (filtrando por id_inventario) como
     * para el historial general por año/periodo, en la misma consulta.
     */
    public function historialChecks(array $filtros, int $perPage = 15): array
    {
        try {
            $checks = InventarioCheck::query()
                ->with([
                    'inventario:id,descripcion,id_area,id_bloque',
                    'inventario.area:id,nombre,id_bloque',
                    'inventario.area.bloque:id,nombre',
                    'inventario.bloque:id,nombre',
                    'anioEscolar:id,anio_inicio,anio_fin',
                    'responsable:id_user,nombre,apellido',
                    'periodoInfo:id,numero',
                ])
                ->when($filtros['id_inventario'] ?? null, fn ($q, $v) => $q->where('id_inventario', $v))
                ->when($filtros['id_anio'] ?? null, fn ($q, $v) => $q->where('id_anio', $v))
                ->when($filtros['periodo'] ?? null, fn ($q, $v) => $q->where('periodo', $v))
                ->when($filtros['id_responsable'] ?? null, fn ($q, $v) => $q->where('id_user', $v))
                // El ítem puede tener el bloque directo (iv.id_bloque) o solo un área puntual
                // cuyo propio id_bloque lo determina (la mayoría de los reclasificados a Áreas
                // Comunes nunca llegan a tener iv.id_bloque propio, ver reclasificarAreaComun /
                // el mismo COALESCE que usa obtenerListadoConsolidado más abajo en este archivo).
                ->when($filtros['id_bloque'] ?? null, fn ($q, $v) => $q->whereHas(
                    'inventario',
                    fn ($q2) => $q2->where('id_bloque', $v)->orWhereHas('area', fn ($q3) => $q3->where('id_bloque', $v))
                ))
                ->when($filtros['id_area'] ?? null, fn ($q, $v) => $q->whereHas('inventario', fn ($q2) => $q2->where('id_area', $v)))
                ->when($filtros['s'] ?? null, fn ($q, $s) => $q->whereHas('inventario', fn ($q2) => $q2->where('descripcion', 'like', "%{$s}%")))
                ->orderByDesc('id')
                ->paginate($perPage);

            return ['error' => false, 'data' => $checks];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Áreas Comunes: % de cumplimiento del check semestral — cuántas áreas comunes
     * existentes (tipo_categoria=3, activas, no descontinuadas) tienen AL MENOS un check
     * registrado que matchee los filtros, sobre el total de áreas comunes que matchean
     * esos mismos filtros de ubicación. Mismos filtros que historialChecks (bloque/área
     * para ubicación, año/periodo para acotar el check en sí) — así el indicador siempre
     * refleja lo mismo que la tabla de abajo en /inventario/areas-comunes/historial-checks.
     */
    public function indicadorChecksAreasComunes(array $filtros): array
    {
        try {
            $base = Inventario::query()
                ->join('categoria as c', 'c.id', '=', 'inventario.id_categoria')
                ->where('c.tipo_categoria', 3)
                ->where('inventario.activo', 1)
                ->where('inventario.estado', '!=', 5)
                // Mismo fallback que historialChecks: el bloque puede venir directo o a
                // través del área puntual del ítem (COALESCE, ver arriba en este archivo).
                ->when($filtros['id_bloque'] ?? null, fn ($q, $v) => $q->where(function ($q2) use ($v) {
                    $q2->where('inventario.id_bloque', $v)
                        ->orWhereExists(function ($q3) use ($v) {
                            $q3->select(DB::raw(1))
                                ->from('areas as a')
                                ->whereColumn('a.id', 'inventario.id_area')
                                ->where('a.id_bloque', $v);
                        });
                }))
                ->when($filtros['id_area'] ?? null, fn ($q, $v) => $q->where('inventario.id_area', $v));

            $totalAreas = $base->clone()->count('inventario.id');

            $conCheck = $base->clone()
                ->whereExists(function ($q) use ($filtros) {
                    $q->select(DB::raw(1))
                        ->from('inventario_check as ic')
                        ->whereColumn('ic.id_inventario', 'inventario.id')
                        ->when($filtros['id_anio'] ?? null, fn ($q2, $v) => $q2->where('ic.id_anio', $v))
                        ->when($filtros['periodo'] ?? null, fn ($q2, $v) => $q2->where('ic.periodo', $v));
                })
                ->count('inventario.id');

            return [
                'error' => false,
                'data' => [
                    'total_areas' => $totalAreas,
                    'con_check' => $conCheck,
                    'porcentaje' => $totalAreas > 0 ? round($conCheck / $totalAreas * 100, 1) : 0,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => null,
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

                $actor = InventarioEmailHelper::nombreUsuario($id_log);
                $fecha = now()->format('d/m/Y H:i');
                $nombreEstado = InventarioEmailHelper::nombreEstado(5, 'Descontinuado');

                $titulo = "Notificación | Inventario Descontinuado";
                $contenido = "Se han descontinuado los siguientes elementos:\n\n";

                foreach ($result['data'] as $inv) {
                    $contenido .= InventarioEmailHelper::detalle($inv, $nombreEstado, $actor, InventarioEmailHelper::nombreUsuario($inv->id_user), $fecha) . "\n\n";
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
                $actor = InventarioEmailHelper::nombreUsuario($id_log);
                $fecha = now()->format('d/m/Y H:i');
                $nombreEstado = InventarioEmailHelper::nombreEstado(4, 'Liberado');

                $titulo = "Notificación | Inventario Liberado";
                $contenido = "Se han liberado los siguientes elementos:\n\n";
                $destinatarios = $this->mailTo;

                foreach ($result['data'] as $inv) {
                    // El responsable/reportador se resuelven ANTES del liberado (el bulk
                    // update ya vació id_user en BD, pero $inv sigue en memoria con el valor
                    // previo) — es a propósito: quien debe enterarse es quien tenía el ítem
                    // asignado, no "nadie" (que es lo que quedaría después de liberar).
                    $responsable = Usuario::find($inv->id_user)?->correo;
                    $ultimoReporte = $inv->reportes()->latest('id')->first();
                    $reportador = $ultimoReporte?->id_user ? Usuario::find($ultimoReporte->id_user)?->correo : null;

                    $contenido .= InventarioEmailHelper::detalle($inv, $nombreEstado, $actor, InventarioEmailHelper::nombreUsuario($inv->id_user), $fecha) . "\n\n";

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
    public function asignarInventario(array $ids, int $id_area, int $id_usuario, ?int $id_log = null)
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

            $actor = InventarioEmailHelper::nombreUsuario($id_log);
            $responsableNombre = InventarioEmailHelper::nombreUsuario($id_usuario);
            $fecha = now()->format('d/m/Y H:i');
            $nombreEstado = InventarioEmailHelper::nombreEstado(1, 'Asignado');

            $titulo = "Notificación | Inventario Asignado";
            $contenido = "Se han asignado los siguientes elementos:\n\n";

            foreach ($inventario_liberado as $inv) {
                // Refresca cada ítem: el bulk update de arriba ya cambió su área/usuario en
                // BD, pero $inv sigue en memoria con los valores ANTERIORES (liberado, sin
                // área) — el correo debe mostrar el área/responsable NUEVOS a los que quedó
                // asignado, no los que tenía antes de asignarlo.
                $inv->refresh();
                $contenido .= InventarioEmailHelper::detalle($inv, $nombreEstado, $actor, $responsableNombre, $fecha) . "\n\n";
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

                // 2 = ya reportado, 5 = descontinuado — un ítem con mantenimiento
                // preventivo pendiente (6) SÍ se puede reportar (ej. un daño nuevo,
                // distinto del mantenimiento ya programado); reportarlo no lo toca, solo
                // cambia inventario.estado a 2 y crea el reporte de daño aparte — mismo
                // criterio que puedeReportar() en InventarioListado/parts/ItemsModal.tsx.
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
                $reportadorCorreo = Usuario::find($id_log)?->correo;
                $reportadorNombre = InventarioEmailHelper::nombreUsuario($id_log);
                $fecha = now()->format('d/m/Y H:i');
                // Nombre del estado al que quedó el ítem (2 = Reportado) — no vía la
                // relación Inventario::estado(), que colisiona con la columna `estado`
                // del propio modelo (mismo criterio que el resto del módulo, que
                // resuelve este nombre con un join a `estado`, nunca con la relación).
                $nombreEstado = InventarioEmailHelper::nombreEstado(2, 'Reportado');

                foreach ($resultado['data'] as $item) {
                    $responsable = Usuario::find($item->id_user);

                    $titulo = "Notificación | Inventario Reportado";
                    $contenido = "Se ha reportado el siguiente ítem de inventario:\n\n"
                        . InventarioEmailHelper::detalle($item, $nombreEstado, $reportadorNombre, InventarioEmailHelper::nombreUsuario($item->id_user), $fecha)
                        . "\n\nDescripción del reporte: {$descripcion}";

                    $this->mailService->sendGeneric($this->destinatarios($responsable?->correo, $reportadorCorreo), $titulo, $contenido);
                }

                $this->notificarAreaComun(
                    $resultado['data']->map(fn ($item) => ['inventario' => $item, 'fecha' => null])->all(),
                    'Reporte de daño',
                    $id_log
                );
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
        ?string $estado_solucion = null,
        ?int $id_area = null,
        ?int $id_bloque = null,
        ?int $id_responsable = null
    ): array {
        try {

            // 'todos' es un tercer modo (además de 'pendiente'/'solucionado') exclusivo del
            // historial de inventario (/inventario/reportes): muestra el histórico completo
            // (reportados y ya solucionados juntos), a diferencia de las bandejas de trabajo
            // (reportado/mantenimiento pendiente), que siguen sin mandar `estado_solucion` y
            // por lo tanto conservan el comportamiento por defecto ('pendiente').
            $modoSolucion = $estado_solucion === 'solucionado'
                ? 'solucionado'
                : ($estado_solucion === 'todos' ? 'todos' : 'pendiente');

            $query = DB::table('inventario as iv')
                ->join('reportes as rp', 'rp.id_inventario', '=', 'iv.id')
                ->leftJoin('usuarios as u', 'u.id_user', '=', 'iv.id_user')
                ->leftJoin('areas as ar', 'ar.id', '=', 'rp.id_area')
                ->leftJoin('categoria as c', 'c.id', '=', 'iv.id_categoria')
                ->leftJoin('anio_escolar as ae', 'ae.id', '=', 'rp.id_anio')
                ->where('iv.activo', 1)
                ->when($modoSolucion === 'todos', function ($q) {
                    // Solo exige que sea el reporte original (no una fila de solución) — sin
                    // filtrar por iv.estado ni por si ya tiene o no una solución asociada.
                    $q->whereNull('rp.id_reporte');
                })
                ->when($modoSolucion === 'solucionado', function ($q) {
                    $q->whereNotIn('iv.estado', [4, 5])
                        ->whereNull('rp.id_reporte')
                        ->whereExists(function ($query) {
                            $query->select(DB::raw(1))
                                ->from('reportes as rpe')
                                ->whereColumn('rpe.id_reporte', 'rp.id')
                                ->where('rpe.estado', 3);
                        });
                })
                ->when($modoSolucion === 'pendiente', function ($q) use ($estado, $tipo_reporte) {
                    // El estado real (2 para reportado, 6 para mantenimiento) lo aporta el
                    // filtro `estado` del caller — cuando viene, replicamos el comportamiento
                    // legacy de exigir iv.estado = rp.estado (antes se relajó a un simple
                    // NOT IN [4,5], lo que dejaba colar inventario cuyo estado real ya había
                    // cambiado por otra vía pero conservaba un reporte sin resolver, algo que
                    // el listado viejo nunca mostraba). Sin `estado` explícito (ej. la pestaña
                    // general "Reportes") se mantiene el filtro amplio.
                    //
                    // Excepción: mantenimiento (tipo_reporte 2). A diferencia de "reportado",
                    // inventario.estado NO se mantiene confiablemente en 6 mientras el
                    // mantenimiento sigue pendiente — datos reales: de 230 mantenimientos
                    // realmente abiertos (sin solución) hoy, 0 conservan iv.estado=6 (otras
                    // acciones sobre el ítem cambian su estado sin pasar por
                    // solucionarReporteInventario). Exigir esa igualdad dejaba
                    // /inventario/mantenimiento sin NINGÚN pendiente. Para mantenimiento
                    // basta con que el ítem siga vivo (no liberado/descontinuado); el filtro
                    // de abajo (`rp.estado = $estado`, más adelante en la query) ya garantiza
                    // que sigue siendo justo ese tipo de reporte sin resolver.
                    $q->when($estado && $tipo_reporte !== 2, function ($q) use ($estado) {
                        $q->where('iv.estado', $estado);
                    }, function ($q) {
                        $q->whereNotIn('iv.estado', [4, 5]);
                    })
                        ->whereNull('rp.id_reporte')
                        ->whereNotExists(function ($query) {
                            $query->select(DB::raw(1))
                                ->from('reportes as rpe')
                                ->whereColumn('rpe.id_reporte', 'rp.id')
                                ->where('rpe.estado', 3);
                        });
                })
                ->select(
                    'iv.*',
                    // Sobrescribe columnas de iv.* que colisionan de nombre con las que
                    // realmente importan del reporte (`rp`) — sin estos overrides explícitos
                    // (evaluados después de 'iv.*' en la lista de SELECT) el frontend recibía
                    // datos del ÍTEM en vez del REPORTE/mantenimiento puntual de cada fila:
                    // la misma fecha de creación y la misma descripción en todo el historial
                    // de un ítem, sin importar cuándo se programó/realizó cada mantenimiento.
                    'rp.fechareg as fechareg',
                    'rp.descripcion as descripcion',
                    'rp.periodo as periodo',
                    // `rp.periodo` guarda el id real de `periodos` (no un ordinal) — se
                    // resuelve acá al `numero` legible para mostrar en el frontend.
                    DB::raw("(SELECT numero FROM periodos WHERE id = rp.periodo) AS periodo_numero"),
                    'rp.id_anio as id_anio',
                    // Nombre propio del ítem (antes de que 'descripcion' se sobrescribiera con
                    // la del reporte) — lo necesita la columna "Artículo"/"Inventario".
                    'iv.descripcion as inventario_descripcion',
                    DB::raw("(SELECT e.nombre FROM estado e WHERE e.id = iv.estado) AS nom_estado"),
                    DB::raw("(SELECT a.nombre FROM areas a WHERE a.id = iv.id_area) AS AREA"),
                    // Áreas Comunes: bloque actual del ítem — directo (iv.id_bloque) si no
                    // tiene área puntual, o el bloque de su propia área (la mayoría de los
                    // ítems reclasificados conservan su área y nunca llegan a tener
                    // iv.id_bloque propio — ver InventarioServices::reclasificarAreaComun).
                    DB::raw("(SELECT b.nombre FROM bloques b WHERE b.id = COALESCE(iv.id_bloque, (SELECT a2.id_bloque FROM areas a2 WHERE a2.id = iv.id_area))) AS nom_bloque"),
                    DB::raw("(SELECT CONCAT(u2.nombre, ' ', u2.apellido) FROM usuarios u2 WHERE u2.id_user = rp.id_user) AS usuario"),
                    DB::raw("(SELECT r.fechareg FROM reportes r WHERE r.id_inventario = iv.id AND r.estado = 2 ORDER BY r.id DESC LIMIT 1) AS fecha_reporte"),
                    DB::raw("(SELECT r.id FROM reportes r WHERE r.id_inventario = iv.id ORDER BY r.id DESC LIMIT 1) AS id_reporte"),
                    // Descripción de la SOLUCIÓN (la fila con id_reporte = rp.id y estado 3),
                    // no la del reporte original — para la columna "Respuesta" del historial.
                    // COALESCE con observacion: soluciones migradas del legacy no siempre
                    // traen descripcion, pero sí observacion con el mismo texto.
                    DB::raw("(SELECT COALESCE(s.descripcion, s.observacion) FROM reportes s WHERE s.id_reporte = rp.id AND s.estado = 3 ORDER BY s.id DESC LIMIT 1) AS respuesta"),
                    // Fecha de la solución — junto con fechareg (fecha del reporte), permite
                    // calcular el tiempo de respuesta como hacía el historial legacy
                    // (`historial/index.php`: diff entre fecha de reporte y fecha_respuesta).
                    // fecha_respuesta, no fechareg: es la columna que
                    // solucionarReporteInventario() siempre rellena (fechareg de la fila de
                    // solución no se setea salvo en la rama de mantenimiento preventivo).
                    DB::raw("(SELECT s.fecha_respuesta FROM reportes s WHERE s.id_reporte = rp.id AND s.estado = 3 ORDER BY s.id DESC LIMIT 1) AS fecha_solucion"),
                    DB::raw("CONCAT(u.nombre, ' ', u.apellido) AS nom_usuario"),
                    DB::raw("CONCAT(ae.anio_inicio, ' - ', ae.anio_fin) AS anio_escolar"),
                    'c.tipo_categoria',
                    'c.nombre as nom_categoria',
                    'ar.nombre as nom_area',
                    'rp.id as reporte_id',
                    // 1 = correctivo (reporte de daño), 2 = preventivo (mantenimiento) — se
                    // filtraba por esta columna más abajo pero no se devolvía, así que el
                    // frontend no podía distinguir el tipo fila por fila.
                    'rp.tipo_reporte'
                )
                // Sin distinct(): cada fila ya es única por rp.id (PK de `reportes`), y todos
                // los leftJoin de arriba (usuarios/areas/categoria/anio_escolar) son sobre
                // columnas únicas, así que no pueden generar filas repetidas. distinct() sobre
                // un SELECT con subconsultas correlacionadas (fecha_reporte/id_reporte/respuesta)
                // fuerza a MySQL a materializar TODO el resultado antes de poder aplicar
                // LIMIT/OFFSET — anulaba la paginación real y era la causa de la lentitud del
                // historial de mantenimiento.
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
                ->when($id_area, function ($q) use ($id_area) {
                    $q->where('iv.id_area', $id_area);
                })
                // Bloque del ítem: directo (iv.id_bloque) o el de su propia área — mismo
                // criterio que nom_bloque más abajo (ver reclasificarAreaComun).
                ->when($id_bloque, function ($q) use ($id_bloque) {
                    $q->where(function ($q2) use ($id_bloque) {
                        $q2->where('iv.id_bloque', $id_bloque)
                            ->orWhereIn('iv.id_area', function ($sub) use ($id_bloque) {
                                $sub->select('id')->from('areas')->where('id_bloque', $id_bloque);
                            });
                    });
                })
                // Responsable del ítem (iv.id_user) — no confundir con `id_user`, que
                // filtra quien REPORTÓ (rp.id_user).
                ->when($id_responsable, function ($q) use ($id_responsable) {
                    $q->where('iv.id_user', $id_responsable);
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
                // rp.fechareg (fecha real del reporte que ya matchea todos los filtros de
                // arriba) en vez de fecha_reporte — esa subconsulta está hardcodeada a
                // estado=2 (reportes de daño) y para mantenimiento (estado 6) siempre da
                // NULL, dejando el orden del historial de mantenimiento indefinido.
                ->orderByDesc('rp.fechareg');

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
        string $descripcion,
        ?int $id_anio = null,
        ?int $id_periodo = null
    ): array {
        try {
            $resultado = DB::transaction(function () use ($id_reporte, $id_resp, $fecha_respuesta, $descripcion, $id_anio, $id_periodo) {

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

                $datosSolucion = [
                    'id_reporte'        => $reporte->id,
                    'id_inventario'     => $reporte->id_inventario,
                    'id_area'           => $reporte->id_area,
                    'id_user'           => $reporte->id_user,
                    'id_log'            => $id_resp,
                    'id_resp'           => $id_resp,
                    'fecha_respuesta'   => $fecha_respuesta ?? now(),
                    'descripcion'       => $descripcion,
                    'estado'            => 3, // Solucionado
                    // Sin esto la solución quedaba con tipo_reporte NULL — debe conservar el
                    // mismo tipo del reporte original (2 = mantenimiento preventivo, 1 = daño).
                    'tipo_reporte'      => $reporte->tipo_reporte,
                    // Muchos reportes correctivos originales son data migrada sin año/periodo
                    // (id_anio/periodo null) — permitir que quien soluciona los indique acá
                    // en vez de heredar un null del reporte original.
                    'periodo'        => $id_periodo ?? $reporte->periodo,
                    'id_anio'           => $id_anio ?? $reporte->id_anio,
                ];

                // Mantenimiento preventivo (tipo_reporte 2) solucionado sin fecha explícita
                // (así llega desde la tabla de mantenimientos pendientes): la fecha de la
                // solución es la fecha PROGRAMADA del mantenimiento (fechareg del reporte
                // original), no "ahora" — el mantenimiento ya ocurrió en esa fecha, el
                // técnico solo lo está registrando/confirmando después.
                if ($reporte->tipo_reporte === 2 && $fecha_respuesta === null) {
                    $datosSolucion['fecha_respuesta'] = $reporte->fechareg;
                    $datosSolucion['fechareg'] = $reporte->fechareg;
                }

                $solucion = Reportes::create($datosSolucion);

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
                // Correo con el mismo contenido/destinatarios que el legacy
                // (ControlReportes::solucionarReporteControl): a quien reportó, al
                // responsable actual del ítem y a quien solucionó — más el correo fijo de
                // sistemas (ya incluido por defecto en $this->mailTo).
                $reporteFresco = Reportes::with('inventario.usuario', 'inventario.area', 'inventario.categoria')->find($id_reporte);
                $inventario = $reporteFresco?->inventario;
                $responsable = $inventario?->usuario?->correo;
                $reportador = Usuario::find($reporteFresco?->id_user)?->correo;
                $solucionador = Usuario::find($id_resp)?->correo;
                $fechaRespuesta = $resultado['data']->fecha_respuesta ?? null;
                $fechaRespuestaTexto = $fechaRespuesta instanceof \Carbon\Carbon
                    ? $fechaRespuesta->format('d/m/Y H:i')
                    : (string) $fechaRespuesta;

                $titulo = "Notificación | Reporte Solucionado";

                if ($inventario) {
                    $nombreEstado = InventarioEmailHelper::nombreEstado((int) $inventario->estado, 'Arreglado');
                    $contenido = "Se ha solucionado el reporte #{$id_reporte} del siguiente artículo:\n\n"
                        . InventarioEmailHelper::detalle(
                            $inventario,
                            $nombreEstado,
                            InventarioEmailHelper::nombreUsuario($id_resp),
                            InventarioEmailHelper::nombreUsuario($inventario->id_user),
                            $fechaRespuestaTexto
                        )
                        . "\nMarca: {$inventario->marca}\n"
                        . "Código: {$inventario->codigo}\n"
                        . "Observación: {$descripcion}\n\n"
                        . "En caso de no recibir nuevamente el reporte de este inventario se tomará como satisfecha la solución al reporte.";
                } else {
                    $contenido = "Se ha solucionado el reporte #{$id_reporte}.\n\nObservación: {$descripcion}";
                }

                $this->mailService->sendGeneric($this->destinatarios($responsable, $reportador, $solucionador), $titulo, $contenido);
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
     * Reportes ya solucionados (estado 3) a la espera de visto bueno administrativo.
     * Equivalente a `visto.php` del sistema legacy.
     */
    public function reportesPendientesVistoBueno(?string $search = null, ?int $per_page = null): array
    {
        try {
            $query = Reportes::with(['inventario.area', 'usuario'])
                ->where('estado', 3)
                ->where('visto_bueno', false)
                ->when($search, function ($q) use ($search) {
                    $q->whereHas('inventario', function ($iq) use ($search) {
                        $iq->where('descripcion', 'like', "%{$search}%")
                            ->orWhere('codigo', 'like', "%{$search}%")
                            ->orWhere('marca', 'like', "%{$search}%");
                    });
                })
                ->orderByDesc('fecha_respuesta');

            $reportes = $per_page ? $query->paginate($per_page) : $query->get();

            return [
                'error' => false,
                'message' => 'Reportes obtenidos correctamente.',
                'data' => $reportes,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    public function vistoBuenoReporte(int $id): array
    {
        $reporte = Reportes::find($id);

        if (!$reporte) {
            return [
                'error' => true,
                'message' => 'No se encontró el reporte.',
                'data' => null,
            ];
        }

        $reporte->update(['visto_bueno' => true]);

        return [
            'error' => false,
            'message' => 'Visto bueno concedido correctamente.',
            'data' => $reporte,
        ];
    }

    public function vistoBuenoGeneral(): array
    {
        Reportes::where('estado', 3)->where('visto_bueno', false)->update(['visto_bueno' => true]);

        return [
            'error' => false,
            'message' => 'Visto bueno concedido a todos los reportes solucionados.',
            'data' => null,
        ];
    }

    /**
     * Programa mantenimientos preventivos para los inventarios dados. A cada
     * inventario se le asigna una fecha aleatoria dentro de [fecha_inicio, fecha_fin],
     * en día hábil (lunes a viernes) y entre 07:30 y 15:45.
     *
     * @param bool $conSolucion Si es true, además crea la solución del mantenimiento
     *                          en la misma fecha indicada + 45 minutos.
     * @param int|null $id_tecnico Responsable explícito de los mantenimientos (id_resp). Si
     *                             no se indica, se mantiene el fallback histórico: el dueño
     *                             del equipo para el reporte, y quien programa para la
     *                             solución (si $conSolucion).
     */
    public function programarMantenimientoPreventivo(
        array $ids,
        string $fecha_inicio,
        string $fecha_fin,
        int $id_log,
        string $descripcion,
        ?int $id_anio,
        ?int $periodo,
        bool $conSolucion = false,
        ?int $id_tecnico = null
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

            // Evita duplicar: un mismo equipo no debería terminar con más de un
            // mantenimiento preventivo (pendiente o ya solucionado) por año+periodo — sin
            // este filtro se podía volver a programar un equipo que ya tuvo su
            // mantenimiento en ese periodo (su estado ya había cambiado a otra cosa desde
            // entonces), inflando el conteo de "realizados" por encima del total de
            // equipos en el indicador.
            if ($periodo) {
                $idsConMantenimiento = DB::table('reportes')
                    ->whereIn('id_inventario', $inventarios->pluck('id'))
                    ->where('tipo_reporte', 2)
                    ->whereNull('id_reporte')
                    ->where('id_anio', $idAnio)
                    ->where('periodo', $periodo)
                    ->pluck('id_inventario');

                $inventarios = $inventarios->reject(
                    fn ($inventario) => $idsConMantenimiento->contains($inventario->id)
                )->values();
            }

            if ($inventarios->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'Los equipos seleccionados ya tienen un mantenimiento preventivo registrado para ese año y periodo.',
                    'data' => []
                ];
            }

            $creados = [];
            $inventariosActualizados = [];

            DB::transaction(function () use (
                $inventarios,
                $fecha_inicio,
                $fecha_fin,
                $id_log,
                $descripcion,
                $idAnio,
                $periodo,
                $conSolucion,
                $id_tecnico,
                &$creados,
                &$inventariosActualizados
            ) {
                $inicio = Carbon::parse($fecha_inicio);
                $fin = Carbon::parse($fecha_fin);

                foreach ($inventarios as $inventario) {
                    $fecha = $this->fechaMantenimientoAleatoria($inicio, $fin);
                    $idRespReporte = $id_tecnico ?? $inventario->id_user;

                    $reporte = Reportes::create([
                        'id_inventario' => $inventario->id,
                        'id_area' => $inventario->id_area,
                        'observacion' => 'Mantenimiento Preventivo',
                        'estado' => 6,
                        'id_user' => $inventario->id_user,
                        'id_log' => $id_log,
                        'id_resp' => $idRespReporte,
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
                    $inventariosActualizados[] = $inventario;

                    if ($conSolucion) {
                        Reportes::create([
                            'id_reporte' => $reporte->id,
                            'id_inventario' => $inventario->id,
                            'id_area' => $inventario->id_area,
                            'observacion' => 'Mantenimiento Preventivo Realizado',
                            'estado' => 3,
                            'id_user' => $inventario->id_user,
                            'id_log' => $id_log,
                            'id_resp' => $id_tecnico ?? $id_log,
                            'tipo_reporte' => 2,
                            'descripcion' => $descripcion,
                            'fecha_respuesta' => $fecha->copy()->addMinutes(45),
                            'id_anio' => $idAnio,
                            'periodo' => $periodo,
                        ]);
                    }

                    $creados[] = ['inventario' => $inventario, 'id_resp' => $idRespReporte, 'fecha' => $fecha];
                }

                // Sin esto, el cambio a estado 6 (mantenimiento preventivo) de arriba no
                // quedaba en inventario_log — el historial de inventario nunca reflejaba que
                // estos ítems entraron a mantenimiento.
                $this->registrarLog($inventariosActualizados, 6, $id_log);
            });

            $this->notificarMantenimientoProgramado($creados, $descripcion, $fecha_inicio, $fecha_fin, $id_log);

            $this->notificarAreaComun(
                array_map(fn ($c) => ['inventario' => $c['inventario'], 'fecha' => $c['fecha']], $creados),
                'Mantenimiento preventivo programado',
                $id_log
            );

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
     * PDF de checklist de mantenimiento preventivo — recreación del legacy
     * (imprimir/mantenimientosSistemas/mantenimientosEquipos.php), sirve tanto para
     * Sistemas como Operativos (la única variación real es el checklist de columnas,
     * que depende de si la categoría es "Computadores" o no — ver
     * MantenimientoChecklistPdfService).
     *
     * @param int[] $ids IDs de inventario a incluir (todos de la misma categoría —
     *                   el checklist de columnas se decide por la categoría del primero).
     */
    public function generarMantenimientoPdf(array $ids, bool $conSolucion, int $idLog): array
    {
        try {
            $equipos = Inventario::whereIn('id', $ids)->with(['area', 'categoria'])->get();

            if ($equipos->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron equipos para generar el PDF.',
                    'data' => [],
                ];
            }

            $categoria = $equipos->first()->categoria;
            $tipoCategoriaLabel = $categoria?->tipo_categoria === 2 ? 'Operativos' : 'Sistemas';
            $esComputadores = $categoria && str_contains(strtolower($categoria->nombre ?? ''), 'computador');

            $responsable = Usuario::find($idLog);
            $responsableNombre = trim(($responsable->nombre ?? '') . ' ' . ($responsable->apellido ?? '')) ?: 'N/A';
            $logoPath = app(MarcaDominioService::class)->resolverRutaLocalPorCorreo($responsable?->correo);

            $contenido = app(MantenimientoChecklistPdfService::class)->generate([
                'logo_path' => $logoPath,
                'tipo_categoria_label' => $tipoCategoriaLabel,
                'categoria_nombre' => $categoria?->nombre ?? 'Inventario',
                'es_computadores' => $esComputadores,
                'responsable_nombre' => $responsableNombre,
                'con_solucion' => $conSolucion,
                'equipos' => $equipos->map(fn ($e) => [
                    'id' => $e->id,
                    'descripcion' => $e->descripcion ?? '',
                    'area' => $e->area?->nombre ?? '',
                ])->all(),
            ]);

            return [
                'error' => false,
                'message' => 'PDF generado correctamente',
                'data' => [
                    'contenido' => $contenido,
                    'nombre_archivo' => 'mantenimiento_' . now()->format('Y-m-d_H-i-s') . '.pdf',
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Un correo por responsable (no uno por equipo) resumiendo cuántos mantenimientos se le
     * programaron en este lote — evita spam cuando se programan muchos equipos a la vez.
     * Falla en silencio (no revierte la programación) si el envío da error.
     */
    private function notificarMantenimientoProgramado(array $creados, string $descripcion, string $fechaInicio, string $fechaFin, ?int $idLog = null): void
    {
        if (empty($creados)) {
            return;
        }

        try {
            $porResponsable = [];
            foreach ($creados as $item) {
                $porResponsable[$item['id_resp']][] = $item;
            }

            $actor = InventarioEmailHelper::nombreUsuario($idLog);
            $nombreEstado = InventarioEmailHelper::nombreEstado(6, 'Mantenimiento preventivo programado');

            foreach ($porResponsable as $idResp => $items) {
                $responsable = Usuario::find($idResp);
                $responsableNombre = InventarioEmailHelper::nombreUsuario($idResp);

                $titulo = 'Notificación | Mantenimiento preventivo programado';
                $contenido = "Se te asignó como responsable de " . count($items) . " mantenimiento(s) preventivo(s), "
                    . "con fecha estimada entre {$fechaInicio} y {$fechaFin}.\n\nDescripción: {$descripcion}\n\n";

                foreach ($items as $item) {
                    $fecha = $item['fecha'] instanceof \Carbon\Carbon ? $item['fecha']->format('d/m/Y H:i') : (string) $item['fecha'];
                    $contenido .= InventarioEmailHelper::detalle($item['inventario'], $nombreEstado, $actor, $responsableNombre, $fecha) . "\n\n";
                }

                $this->mailService->sendGeneric($this->destinatarios($responsable?->correo), $titulo, $contenido);
            }
        } catch (\Throwable $e) {
            Log::error('No se pudo notificar la programación de mantenimiento: ' . $e->getMessage());
        }
    }

    /**
     * % de cumplimiento de mantenimiento preventivo por categoría: equipos con al menos un
     * mantenimiento (tipo_reporte=2, reporte original) registrado en el periodo filtrado,
     * sobre el total de equipos activos de esa categoría. Replica el indicador del SAMI
     * legado (historial_mantenimiento.php / historialMantAires.php), agregado también por
     * categoría en vez de un único porcentaje global.
     */
    public function indicadorMantenimiento(?int $tipoCategoria, ?int $idAnio, ?int $idPeriodo, ?int $idCategoria = null): array
    {
        try {
            $categorias = Categoria::when($tipoCategoria, fn ($q) => $q->where('tipo_categoria', $tipoCategoria))
                ->when($idCategoria, fn ($q) => $q->where('id', $idCategoria))
                ->orderBy('nombre')
                ->get();

            $porCategoria = $categorias->map(function ($categoria) use ($idAnio, $idPeriodo) {
                $totalEquipos = Inventario::where('id_categoria', $categoria->id)
                    ->where('activo', 1)
                    ->where('estado', '!=', 5)
                    ->count();

                // "Realizados" = tienen una solución vinculada (estado 3), no solo
                // programados — antes contaba cualquier reporte original sin importar si
                // seguía pendiente, inflando la cifra que la tarjeta llama "realizados".
                // También se exige que el ÍTEM siga activo y no descontinuado (mismo
                // filtro que total_equipos): sin esto, un equipo con mantenimiento
                // realizado que luego fue descontinuado/desactivado se seguía contando acá
                // pero ya no en el denominador, pudiendo superar el 100%.
                $totalMantenimientos = DB::table('reportes as r')
                    ->join('inventario as i', 'i.id', '=', 'r.id_inventario')
                    ->where('i.id_categoria', $categoria->id)
                    ->where('i.activo', 1)
                    ->where('i.estado', '!=', 5)
                    ->where('r.tipo_reporte', 2)
                    ->whereNull('r.id_reporte')
                    ->whereExists(function ($q) {
                        $q->select(DB::raw(1))
                            ->from('reportes as s')
                            ->whereColumn('s.id_reporte', 'r.id')
                            ->where('s.estado', 3);
                    })
                    ->when($idAnio, fn ($q) => $q->where('r.id_anio', $idAnio))
                    ->when($idPeriodo, fn ($q) => $q->where('r.periodo', $idPeriodo))
                    ->distinct()
                    ->count('r.id_inventario');

                // Salvaguarda de visualización: "realizados" nunca debe superar el total de
                // equipos de la categoría — no cambia lo que se cuenta arriba, solo evita
                // que un caso no previsto muestre un porcentaje incoherente (>100%).
                $totalMantenimientos = min($totalMantenimientos, $totalEquipos);

                return [
                    'id_categoria' => $categoria->id,
                    'nombre' => $categoria->nombre,
                    'total_equipos' => $totalEquipos,
                    'total_mantenimientos' => $totalMantenimientos,
                    'porcentaje' => $totalEquipos > 0 ? round($totalMantenimientos / $totalEquipos * 100, 1) : 0,
                ];
            })->values();

            $totalEquipos = (int) $porCategoria->sum('total_equipos');
            $totalMantenimientos = (int) $porCategoria->sum('total_mantenimientos');

            return [
                'error' => false,
                'message' => 'Indicador de mantenimiento obtenido',
                'data' => [
                    'total_equipos' => $totalEquipos,
                    'total_mantenimientos' => $totalMantenimientos,
                    'porcentaje' => $totalEquipos > 0 ? round($totalMantenimientos / $totalEquipos * 100, 1) : 0,
                    'por_categoria' => $porCategoria,
                ],
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Mantenimientos preventivos programados por mes dentro de un año escolar — la
     * "gráfica de comportamiento del indicador" que el legado solo insinuaba (tabla
     * `indicadores_gestion` fuera de este módulo) sin implementarla realmente acá.
     */
    public function graficaMantenimientoPorMes(?int $tipoCategoria, ?int $idAnio, ?int $idCategoria = null): array
    {
        try {
            $porMes = DB::table('reportes as r')
                ->join('inventario as i', 'i.id', '=', 'r.id_inventario')
                ->join('categoria as c', 'c.id', '=', 'i.id_categoria')
                ->where('r.tipo_reporte', 2)
                ->whereNull('r.id_reporte')
                ->when($tipoCategoria, fn ($q) => $q->where('c.tipo_categoria', $tipoCategoria))
                ->when($idCategoria, fn ($q) => $q->where('i.id_categoria', $idCategoria))
                ->when($idAnio, fn ($q) => $q->where('r.id_anio', $idAnio))
                ->select(
                    DB::raw('MONTH(r.fechareg) as mes'),
                    DB::raw('COUNT(DISTINCT r.id_inventario) as total')
                )
                ->groupBy(DB::raw('MONTH(r.fechareg)'))
                ->orderBy('mes')
                ->get();

            return [
                'error' => false,
                'message' => 'Gráfica de mantenimiento obtenida',
                'data' => ['por_mes' => $porMes],
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => null,
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
