<?php

namespace App\Http\Controllers\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\ActualizarInventarioRequest;
use App\Http\Requests\Inventario\EditarDescripcionListadoInventarioRequest;
use App\Http\Requests\Inventario\GestionarCantidadListadoInventarioRequest;
use App\Http\Requests\Inventario\ListadoInventarioRequest;
use App\Http\Requests\Inventario\MostrarReportesInventarioRequest;
use App\Http\Requests\Inventario\RegistrarInventarioRequest;
use App\Http\Requests\Inventario\ReportarInventarioRequest;
use App\Http\Requests\Inventario\SolucionarReporteInventarioRequest;
use App\Services\inventario\InventarioServices as InventarioServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventariosController extends Controller
{
    // Opciones del frontend: 12 = /inventario/listado (+ reportado/liberado/reportes/
    // mantenimiento/areas), 16 = /inventario/mis-inventarios (autoservicio: solo lo
    // propio), 17 = /inventario/prestamos. `obtenerListadoInventario`/`reportarInventario`
    // se comparten con Mis Inventarios (un usuario reporta o consulta SU inventario
    // asignado sin necesitar el permiso administrativo completo); `listadoConsolidado` se
    // comparte con Préstamos (para elegir qué ítem prestar). El resto es exclusivo de 12.
    private const OPCION_INVENTARIO = 12;
    private const OPCION_MIS_INVENTARIOS = 16;
    private const OPCION_PRESTAMOS = 17;

    protected $inventario_services;

    public function __construct(
        InventarioServices $inventarioServices,
        private UsuariosServices $usuariosService,
    ) {
        $this->inventario_services = $inventarioServices;
    }

    /**
     * Chequeo server-side del permiso, no solo ocultar la ruta en el frontend.
     */
    private function sinAcceso(Request $request, int ...$opciones): ?JsonResponse
    {
        $perfil = $request->user()->perfil;

        foreach ($opciones as $opcion) {
            if ($this->usuariosService->tienePermiso($opcion, $perfil)['permiso'] ?? false) {
                return null;
            }
        }

        return $this->error('No tienes permiso para esta acción', 403);
    }

    public function agregarInventario(RegistrarInventarioRequest $request){
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $inventario_data = $request->toInventarioCreate();

        $agregar = $this->inventario_services->agregarInventario($inventario_data);

        if($agregar['error']){
            return response()->json([
                'error' => true,
                'message' => $agregar['message'],
                'data' => $agregar['data']
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => "Se ha agregado el articulo al inventario",
            "data" => $agregar['data']
        ]);
    }

    public function obtenerListadoInventario(Request $request){
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_MIS_INVENTARIOS)) {
            return $rechazo;
        }

        $per_page = $request->input('per-page', 10); // Número de elementos por página, por defecto 10
        $search = $request->input('s', null);
        $datos = $request->only(['id_area', 'id_categoria', 'estado', 'estado_not_in', 'id_usuario']);
        $sort = $request->input('sort'); // 'usuario' o 'cantidad'
        $dir = $request->input('dir', 'asc');
        $listado_inventario = $this->inventario_services->obtenerListadoInventario($per_page, $search, $datos, $sort, $dir);

        if($listado_inventario['error']){
            return response()->json([
                'error' => true,
                'message' => $listado_inventario['message'],
                'data' => $listado_inventario['data']
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => $listado_inventario['message'],
            'data' => $listado_inventario['data']
        ]);
    }

    public function listadoConsolidado(ListadoInventarioRequest $request){
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_PRESTAMOS)) {
            return $rechazo;
        }

        $filtros = $request->only(['id_usuario', 'id_area', 'id_categoria', 'tipo_categoria', 'estado', 's', 'descripcion']);
        $per_page = $request->input('per_page', 15);

        $listado = $this->inventario_services->obtenerListadoConsolidado($filtros, $per_page);

        if($listado['error']){
            return response()->json([
                'error' => true,
                'message' => $listado['message'],
                'data' => $listado['data']
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => $listado['message'],
            'data' => $listado['data']
        ]);
    }

    public function editarDescripcionGrupo(EditarDescripcionListadoInventarioRequest $request){
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

    public function incrementarCantidadGrupo(GestionarCantidadListadoInventarioRequest $request){
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

    public function disminuirCantidadGrupo(GestionarCantidadListadoInventarioRequest $request){
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

    public function actualizarInventario(ActualizarInventarioRequest $request, int $id){
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->actualizarInventario($id, $request->toInventarioUpdate());

        return $this->apiResponse($resultado);
    }

    public function historialInventario(Request $request, int $id){
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $resultado = $this->inventario_services->historialInventario($id);

        return $this->apiResponse($resultado);
    }

    public function descontinuarInventario(Request $request){
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $data = $request->all();

        $validator = Validator::make($data, [
            "ids" => "required|array|min:1",
            "ids.*" => "integer|distinct|exists:inventario,id",
            "id_log" => "integer|exists:usuarios,id_user", 
        ]);

        if($validator->fails()){
            return response()->json([
                "error" => true,
                "message" => $validator->errors()->first(),
            ], 422);
        }

        $descontinuar = $this->inventario_services->descontinuarInventario($data['ids'], $data['id_log']);

        $status = match (true) {
            $descontinuar['error'] && str_contains($descontinuar['message'], "SQL") => 500,
            $descontinuar['error'] => 400,
            default => 200,
        };
        return response()->json($descontinuar, $status);
    }

    public function liberarInventario(Request $request) {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $data = $request->only('ids', 'id_log');

        $validator = Validator::make($data, [
            "ids" => "required|array|min:1",
            "ids.*" => "integer|distinct|exists:inventario,id",
            "id_log" => "integer|exists:usuarios,id_user"
            ]);

        if($validator->fails()){
            return response()->json([
                "error" => true,
                "message" => $validator->errors()->first(),
            ], 422);
        }

        $liberar = $this->inventario_services->liberarInventario($data['ids'], $data['id_log']);

        return $this->apiResponse($liberar);
    }

    public function asignarInventario(Request $request){
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $data = $request->only('ids', 'id_area', 'id_user');

        $validator = Validator::make($data, [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct|exists:inventario,id',
            'id_area' => "integer|distinct|exists:areas,id",
            'id_user' => "integer|distinct|exists:usuarios,id_user"
        ]);

        if($validator->fails()){
            return response()->json([
                "error" => true,
                "message" => $validator->errors()->first(),
            ], 422);
        }

        $asignar = $this->inventario_services->asignarInventario($data['ids'], $data['id_area'], $data['id_user']);

        return $this->apiResponse($asignar);
    }

    public function reportarInventario(ReportarInventarioRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_MIS_INVENTARIOS)) {
            return $rechazo;
        }

        $data = $request->toReportarInventario();

        $resultado = $this->inventario_services->reportarInventario(
            $data['ids'],
            $data['id_log'],
            $data['descripcion'],
            $data['id_anio'],
            $data['id_periodo']
        );

        return $this->apiResponse($resultado);
    }

    public function mostrarReportesDeInventario(MostrarReportesInventarioRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
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
            $request->input('estado_solucion')
        );

        return $this->apiResponse($resultado);
    }

    public function solucionarReporteInventario(SolucionarReporteInventarioRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $data = $request->toSolucionarReporte();

        $resultado = $this->inventario_services->solucionarReporteInventario(
            $data['id_reporte'],
            $data['id_resp'],
            $data['fecha_respuesta'],
            $data['descripcion']
        );

        return $this->apiResponse($resultado);
    }

    public function programarMantenimientoPreventivo(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $data = $request->all();

        $validator = Validator::make($data, [
            "ids" => "required|array|min:1",
            "ids.*" => "integer|distinct|exists:inventario,id",
            "fecha_inicio" => "required|date",
            "fecha_fin" => "required|date|after_or_equal:fecha_inicio",
            "descripcion" => "required|string",
            "id_anio" => "required|integer|exists:anio_escolar,id",
            "periodo" => "required|integer|in:1,2",
            "id_log" => "required|integer|exists:usuarios,id_user",
            "con_solucion" => "boolean",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "error" => true,
                "message" => $validator->errors()->first(),
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
            $data['con_solucion'] ?? false
        );

        return $this->apiResponse($resultado);
    }
}