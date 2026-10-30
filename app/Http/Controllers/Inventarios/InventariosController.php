<?php

namespace App\Http\Controllers\Inventarios;

use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\ActualizarInventarioRequest;
use App\Http\Requests\Inventario\EditarDescripcionListadoInventarioRequest;
use App\Http\Requests\Inventario\GestionarCantidadListadoInventarioRequest;
use App\Http\Requests\Inventario\ListadoInventarioRequest;
use App\Http\Requests\Inventario\MantenimientoIndicadorRequest;
use App\Http\Requests\Inventario\MostrarReportesInventarioRequest;
use App\Http\Requests\Inventario\RegistrarInventarioRequest;
use App\Http\Requests\Inventario\ReportarInventarioRequest;
use App\Http\Requests\Inventario\SolucionarReporteInventarioRequest;
use App\Models\CursosAltura\CursoAltura;
use App\Services\inventario\InventarioServices;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventariosController extends Controller
{
    use VerificaPermisos;

    // Opciones del frontend: 12 = /inventario/listado (+ reportado/liberado/reportes/
    // mantenimiento/areas), 16 = /inventario/mis-inventarios (autoservicio: solo lo
    // propio), 17 = /inventario/prestamos. `obtenerListadoInventario`/`reportarInventario`
    // se comparten con Mis Inventarios (un usuario reporta o consulta SU inventario
    // asignado sin necesitar el permiso administrativo completo); `listadoConsolidado` se
    // comparte con Préstamos (para elegir qué ítem prestar). El resto es exclusivo de 12.
    private const OPCION_INVENTARIO = Permiso::INVENTARIO;

    private const OPCION_MIS_INVENTARIOS = Permiso::MIS_INVENTARIOS;

    private const OPCION_PRESTAMOS = Permiso::PRESTAMOS_INVENTARIO;

    // Fijar la fecha de un reporte de daño (en vez de "ahora") exige esta opción.
    private const OPCION_FECHA_REPORTE = Permiso::INVENTARIO_FECHA_REPORTE;

    // cron_opciones bajo id_modulo=6 (Zonas) — Áreas Comunes tiene su propio par de
    // permisos, separado de OPCION_INVENTARIO; historialInventario() los acepta también
    // porque "Ver hoja de vida" ya se ofrece como acción de fila en Listado de Áreas
    // Comunes (ver AGENTS.md "Áreas Comunes").
    private const OPCION_ADMIN_AREAS_COMUNES = Permiso::AREAS_COMUNES_ADMIN;

    private const OPCION_USO_AREAS_COMUNES = Permiso::AREAS_COMUNES_USO;

    // "Mis áreas comunes" (autoservicio, `/inventario/areas-comunes/mis-areas`): sin
    // esta, alguien con SOLO 119 (sin 108/109) cargaría la página pero listadoConsolidado
    // y reportarInventario le devolverían 403 — Reportar es la única acción que esa
    // vista ofrece, así que ambos métodos la necesitan.
    private const OPCION_MIS_AREAS_COMUNES = Permiso::MIS_AREAS_COMUNES;

    // "Mis áreas" bajo Mis inventarios (141 nivel / 142 áreas a cargo): solo reportar y ver
    // la hoja de vida; el listado sale de AreasComunesController::misAreas.
    private const OPCION_MIS_AREAS_NIVEL = Permiso::MIS_AREAS_NIVEL;

    private const OPCION_MIS_AREAS_RESPONSABLE = Permiso::MIS_AREAS_RESPONSABLE;

    protected $inventario_services;

    public function __construct(
        InventarioServices $inventarioServices,
    ) {
        $this->inventario_services = $inventarioServices;
    }

    public function agregarInventario(RegistrarInventarioRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $inventario_data = $request->toInventarioCreate();

        $agregar = $this->inventario_services->agregarInventario($inventario_data, $request->cantidad());

        if ($agregar['error']) {
            return response()->json([
                'error' => true,
                'message' => $agregar['message'],
                'data' => $agregar['data'],
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => 'Se ha agregado el articulo al inventario',
            'data' => $agregar['data'],
        ]);
    }

    public function obtenerListadoInventario(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_MIS_INVENTARIOS)) {
            return $rechazo;
        }

        $per_page = $request->input('per-page', 10); // Número de elementos por página, por defecto 10
        $search = $request->input('s', null);
        $datos = $request->only(['id_area', 'id_bloque', 'id_categoria', 'estado', 'estado_not_in', 'id_usuario', 'tipo_categoria', 'anio_descontinuado']);
        $sort = $request->input('sort'); // 'usuario' o 'cantidad'
        $dir = $request->input('dir', 'asc');
        $listado_inventario = $this->inventario_services->obtenerListadoInventario($per_page, $search, $datos, $sort, $dir);

        // 500, no 200: con 200 el frontend (apiFetch) no lo trata como error y "Mis
        // inventarios" mostraba la lista vacía sin ningún aviso.
        if ($listado_inventario['error']) {
            return response()->json([
                'error' => true,
                'message' => $listado_inventario['message'],
                'data' => $listado_inventario['data'],
            ], 500);
        }

        return response()->json([
            'error' => false,
            'message' => $listado_inventario['message'],
            'data' => $listado_inventario['data'],
        ]);
    }

    public function listadoConsolidado(ListadoInventarioRequest $request)
    {
        // Áreas Comunes también lo usa (tabla de ítems de Listado/Mis áreas) — ver
        // AGENTS.md "Áreas Comunes".
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_PRESTAMOS, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES, self::OPCION_MIS_AREAS_COMUNES)) {
            return $rechazo;
        }

        $filtros = $request->only(['id_usuario', 'id_area', 'id_bloque', 'id_bloque_area', 'id_categoria', 'tipo_categoria', 'estado', 's', 'descripcion', 'individual', 'sort', 'dir']);
        // `per-page` es el que manda el DataTable remoto de /inventario/listado.
        $per_page = $request->input('per-page', $request->input('per_page', 15));

        $listado = $this->inventario_services->obtenerListadoConsolidado($filtros, $per_page);

        if ($listado['error']) {
            return response()->json([
                'error' => true,
                'message' => $listado['message'],
                'data' => $listado['data'],
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => $listado['message'],
            'data' => $listado['data'],
        ]);
    }

    // GET /inventario/listado-consolidado/conteo — Total / Liberados / Reportados / Mant. preventivo
    // con los mismos filtros del listado (SAMI legacy: conteoPorEstadoControl).
    public function conteoPorEstado(ListadoInventarioRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        return $this->apiResponse(
            $this->inventario_services->conteoPorEstado($request->only(['id_usuario', 'id_area', 'id_bloque_area', 'id_categoria', 'tipo_categoria', 's']))
        );
    }

    public function editarDescripcionGrupo(EditarDescripcionListadoInventarioRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->editarDescripcionGrupo(
            $request->input('descripcion'),
            $request->input('nueva_descripcion'),
            $request->input('id_area'),
            $request->input('id_usuario')
        );

        return $this->apiResponse($resultado);
    }

    public function incrementarCantidadGrupo(GestionarCantidadListadoInventarioRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->incrementarCantidadGrupo(
            $request->input('descripcion'),
            $request->input('id_area'),
            $request->input('id_usuario'),
            $request->input('cantidad')
        );

        return $this->apiResponse($resultado);
    }

    public function disminuirCantidadGrupo(GestionarCantidadListadoInventarioRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->disminuirCantidadGrupo(
            $request->input('descripcion'),
            $request->input('id_area'),
            $request->input('id_usuario'),
            $request->input('cantidad'),
            $request->input('id_log')
        );

        return $this->apiResponse($resultado);
    }

    public function actualizarInventario(ActualizarInventarioRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->actualizarInventario($id, $request->toInventarioUpdate());

        return $this->apiResponse($resultado);
    }

    public function historialInventario(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES, self::OPCION_MIS_AREAS_NIVEL, self::OPCION_MIS_AREAS_RESPONSABLE)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->historialInventario($id);

        return $this->apiResponse($resultado);
    }

    // GET /inventario/{id}/hoja-vida/pdf — mismos permisos que historialInventario (ambos
    // alimentan la misma "hoja de vida" del frontend).
    public function hojaVidaPdf(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->generarHojaVidaPdf($id);

        if ($resultado['error']) {
            return $this->apiResponse($resultado);
        }

        return response($resultado['data']['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resultado['data']['nombre_archivo'].'"',
        ]);
    }

    // GET /inventario/reportes/{id}/pdf — "Descargar Reporte" en /inventario/reportado.
    public function reportePdf(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->generarReportePdf($id);

        if ($resultado['error']) {
            return $this->apiResponse($resultado);
        }

        return response($resultado['data']['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resultado['data']['nombre_archivo'].'"',
        ]);
    }

    public function descontinuarInventario(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES)) {
            return $rechazo;
        }

        $data = $request->all();

        $validator = Validator::make($data, [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct|exists:inventario,id',
            'id_log' => 'integer|exists:usuarios,id_user',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $descontinuar = $this->inventario_services->descontinuarInventario($data['ids'], $data['id_log']);

        $status = match (true) {
            $descontinuar['error'] && str_contains($descontinuar['message'], 'SQL') => 500,
            $descontinuar['error'] => 400,
            default => 200,
        };

        return response()->json($descontinuar, $status);
    }

    public function liberarInventario(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $data = $request->only('ids', 'id_log');

        $validator = Validator::make($data, [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct|exists:inventario,id',
            'id_log' => 'integer|exists:usuarios,id_user',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $liberar = $this->inventario_services->liberarInventario($data['ids'], $data['id_log']);

        return $this->apiResponse($liberar);
    }

    public function asignarInventario(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $data = $request->only('ids', 'id_area', 'id_user');

        $validator = Validator::make($data, [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct|exists:inventario,id',
            'id_area' => 'integer|distinct|exists:areas,id',
            'id_user' => 'integer|distinct|exists:usuarios,id_user',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $asignar = $this->inventario_services->asignarInventario($data['ids'], $data['id_area'], $data['id_user'], $request->user()->id_user ?? null);

        return $this->apiResponse($asignar);
    }

    public function reportarInventario(ReportarInventarioRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_MIS_INVENTARIOS, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES, self::OPCION_MIS_AREAS_COMUNES, self::OPCION_MIS_AREAS_NIVEL, self::OPCION_MIS_AREAS_RESPONSABLE)) {
            return $rechazo;
        }

        if ($request->has('fechareg') && ($rechazo = $this->sinAcceso($request, self::OPCION_FECHA_REPORTE))) {
            return $rechazo;
        }

        $data = $request->toReportarInventario();

        $resultado = $this->inventario_services->reportarInventario(
            $data['ids'],
            $data['id_log'],
            $data['descripcion'],
            $data['id_anio'],
            $data['id_periodo'],
            $data['fechareg'],
            $data['id_operativo'] ? (int) $data['id_operativo'] : null
        );

        return $this->apiResponse($resultado);
    }

    public function mostrarReportesDeInventario(MostrarReportesInventarioRequest $request)
    {
        // Reporte de Áreas Comunes (/inventario/areas-comunes/reportes) pega contra este
        // mismo endpoint — ver AGENTS.md "Áreas Comunes".
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->mostrarReportesDeInventario(
            $request->input('id_inventario'),
            $request->input('id_user'),
            $request->input('id_anio'),
            $request->input('id_periodo'),
            $request->input('search'),
            $request->input('estado'),
            $request->input('tipo_categoria'),
            $request->input('per_page'),
            $request->input('tipo_reporte'),
            $request->input('sin_solucion'),
            $request->input('id_categoria'),
            $request->input('estado_solucion'),
            $request->input('id_area'),
            $request->input('id_bloque'),
            $request->input('id_responsable'),
            $request->input('sort') === 'fechareg' ? $request->input('dir') : null
        );

        return $this->apiResponse($resultado);
    }

    // GET /inventario/reportes/estadisticas — gráficas del historial (/inventario/reportes),
    // con los mismos filtros que el listado.
    public function estadisticasReportes(MostrarReportesInventarioRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES)) {
            return $rechazo;
        }

        return $this->apiResponse($this->inventario_services->estadisticasReportes([
            'id_user' => $request->input('id_user'),
            'id_anio' => $request->input('id_anio'),
            'id_periodo' => $request->input('id_periodo'),
            'search' => $request->input('search', $request->input('s')),
            'tipo_categoria' => $request->input('tipo_categoria'),
            'tipo_reporte' => $request->input('tipo_reporte'),
            'id_categoria' => $request->input('id_categoria'),
            'estado_solucion' => $request->input('estado_solucion'),
            'id_area' => $request->input('id_area'),
        ]));
    }

    public function solucionarReporteInventario(SolucionarReporteInventarioRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES)) {
            return $rechazo;
        }

        $data = $request->toSolucionarReporte();

        $resultado = $this->inventario_services->solucionarReporteInventario(
            $data['id_reporte'],
            $data['id_resp'],
            $data['fecha_respuesta'],
            $data['descripcion'],
            $data['id_anio'],
            $data['id_periodo']
        );

        return $this->apiResponse($resultado);
    }

    public function reportesPendientesVistoBueno(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->reportesPendientesVistoBueno(
            $request->input('search', $request->input('s')),
            // El DataTable remoto manda `per-page` (con guion) y `s`; antes solo se leían
            // `per_page`/`search`, así que nunca paginaba y cargaba TODOS los reportes
            // solucionados en memoria (OOM en JsonResponse). Siempre se pagina.
            (int) $request->input('per_page', $request->input('per-page', 10))
        );

        return $this->apiResponse($resultado);
    }

    public function vistoBuenoReporte(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->vistoBuenoReporte($id);

        return $this->apiResponse($resultado);
    }

    public function vistoBuenoGeneral(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->vistoBuenoGeneral();

        return $this->apiResponse($resultado);
    }

    public function programarMantenimientoPreventivo(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES)) {
            return $rechazo;
        }

        $data = $request->all();

        $validator = Validator::make($data, [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct|exists:inventario,id',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
            'descripcion' => 'required|string',
            'id_anio' => 'required|integer|exists:anio_escolar,id',
            // El id real de una fila de `periodos`, igual que en ReportarInventarioRequest.
            'periodo' => 'required|integer|exists:periodos,id',
            'id_log' => 'required|integer|exists:usuarios,id_user',
            'con_solucion' => 'boolean',
            'id_tecnico' => 'nullable|integer|exists:usuarios,id_user',
            'id_operativo' => CursoAltura::reglaOperativo(),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $resultado = $this->inventario_services->programarMantenimientoPreventivo(
            $data['ids'],
            $data['fecha_inicio'],
            $data['fecha_fin'],
            $data['id_log'],
            $data['descripcion'],
            $data['id_anio'],
            $data['periodo'],
            $data['con_solucion'] ?? false,
            $data['id_tecnico'] ?? null,
            isset($data['id_operativo']) ? (int) $data['id_operativo'] : null
        );

        return $this->apiResponse($resultado);
    }

    // POST /inventario/mantenimiento/pdf — checklist de mantenimiento preventivo (mismos
    // equipos recién programados), sirve para Sistemas y Operativos por igual.
    public function generarMantenimientoPdf(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct|exists:inventario,id',
            'con_solucion' => 'boolean',
            'id_log' => 'required|integer|exists:usuarios,id_user',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $data = $validator->validated();

        $resultado = $this->inventario_services->generarMantenimientoPdf(
            $data['ids'],
            $data['con_solucion'] ?? false,
            $data['id_log']
        );

        if ($resultado['error']) {
            return $this->apiResponse($resultado);
        }

        return response($resultado['data']['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$resultado['data']['nombre_archivo'].'"',
        ]);
    }

    // GET /inventario/mantenimiento/periodos?id_anio= — periodos con mantenimiento programado
    // en ese año (selector del modal de la guía de mantenimiento).
    public function periodosMantenimiento(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'id_anio' => 'required|integer',
            'id_categoria' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->inventario_services->periodosConMantenimiento(
            (int) $request->input('id_anio'),
            $request->filled('id_categoria') ? (int) $request->input('id_categoria') : null
        ));
    }

    // POST /inventario/mantenimiento/guia-pdf — guía de mantenimiento (checklist en blanco)
    // de lo programado en un año, periodo y categoría.
    public function guiaMantenimientoPdf(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'id_anio' => 'required|integer|exists:anio_escolar,id',
            'id_periodo' => 'required|integer|exists:periodos,id',
            'id_categoria' => 'required|integer|exists:categoria,id',
            'fecha' => 'required|date',
            'solucionado' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $data = $validator->validated();

        $resultado = $this->inventario_services->generarGuiaMantenimientoPdf(
            $data['id_anio'],
            $data['id_periodo'],
            $data['id_categoria'],
            $request->user()->id_user,
            $data['fecha'],
            (bool) ($data['solucionado'] ?? false)
        );

        if ($resultado['error']) {
            return $this->apiResponse($resultado);
        }

        return response($resultado['data']['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resultado['data']['nombre_archivo'].'"',
        ]);
    }

    // GET /inventario/mantenimiento/sin-mantenimiento — equipos de la categoría sin
    // mantenimiento preventivo generado en el año y periodo indicados (pestaña del historial).
    public function sinMantenimiento(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'id_anio' => 'required|integer',
            'id_periodo' => 'required|integer',
            'tipo_categoria' => 'nullable|integer',
            'id_categoria' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        $resultado = $this->inventario_services->inventariosSinMantenimiento(
            (int) $request->input('id_anio'),
            (int) $request->input('id_periodo'),
            $request->filled('tipo_categoria') ? (int) $request->input('tipo_categoria') : null,
            $request->filled('id_categoria') ? (int) $request->input('id_categoria') : null,
            $request->input('search', $request->input('s')),
            (int) $request->input('per_page', $request->input('per-page', 20))
        );

        return $this->apiResponse($resultado);
    }

    // GET /inventario/mantenimiento/indicador — % de cumplimiento por categoría (equipos
    // con mantenimiento registrado en el periodo / total de equipos activos).
    public function indicadorMantenimiento(MantenimientoIndicadorRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->indicadorMantenimiento(
            $request->input('tipo_categoria'),
            $request->input('id_anio'),
            $request->input('id_periodo'),
            $request->input('id_categoria')
        );

        return $this->apiResponse($resultado);
    }

    // GET /inventario/mantenimiento/grafica — mantenimientos programados por mes.
    public function graficaMantenimiento(MantenimientoIndicadorRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->graficaMantenimientoPorMes(
            $request->input('tipo_categoria'),
            $request->input('id_anio'),
            $request->input('id_categoria')
        );

        return $this->apiResponse($resultado);
    }
}
