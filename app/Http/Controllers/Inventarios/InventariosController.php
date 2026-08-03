<?php

namespace App\Http\Controllers\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\MostrarReportesInventarioRequest;
use App\Http\Requests\Inventario\RegistrarInventarioRequest;
use App\Http\Requests\Inventario\ReportarInventarioRequest;
use App\Http\Requests\Inventario\SolucionarReporteInventarioRequest;
use App\Services\inventario\InventarioServices as InventarioServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InventariosController extends Controller
{
    protected $inventario_services;

    public function __construct(InventarioServices $inventarioServices)
    {
        $this->inventario_services = $inventarioServices;
    }

    public function agregarInventario(RegistrarInventarioRequest $request){
        
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
        $per_page = $request->input('per-page', 10); // Número de elementos por página, por defecto 10
        $search = $request->input('search', null);
        $datos = $request->only(['id_area', 'id_categoria', 'estado', 'id_usuario']);
        $listado_inventario = $this->inventario_services->obtenerListadoInventario($per_page, $search, $datos);

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

    public function descontinuarInventario(Request $request){
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

    public function mostrarReportesInventario(MostrarReportesInventarioRequest $request)
    {
        $resultado = $this->inventario_services->mostrarReportesDeInventario(
            $request->input('id_inventario'),
            $request->input('id_user'),
            $request->input('id_anio'),
            $request->input('id_periodo'),
            $request->input('s', $request->input('search')),
            $request->input('estado'),
            $request->input('tipo_categoria'),
            $request->input('per_page', $request->input('per-page')),
            $request->input('tipo_reporte'),
            $request->boolean('sin_solucion')
        );

        if ($resultado['error']) {
            return $this->apiResponse($resultado);
        }

        if ($resultado['data'] instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            return $this->paginatedResponse($resultado);
        }

        return $this->apiResponse($resultado);
    }

    public function solucionarReporteInventario(SolucionarReporteInventarioRequest $request)
    {
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