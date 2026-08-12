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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $search = $request->input('s', null);
        $datos = $request->only(['id_area', 'id_categoria', 'estado', 'id_usuario']);
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
        $resultado = $this->inventario_services->editarDescripcionGrupo(
            $request->input('descripcion'),
            $request->input('nueva_descripcion'),
            $request->input('id_area'),
            $request->input('id_usuario')
        );

        return $this->apiResponse($resultado);
    }

    public function incrementarCantidadGrupo(GestionarCantidadListadoInventarioRequest $request){
        $resultado = $this->inventario_services->incrementarCantidadGrupo(
            $request->input('descripcion'),
            $request->input('id_area'),
            $request->input('id_usuario'),
            $request->input('cantidad')
        );

        return $this->apiResponse($resultado);
    }

    public function disminuirCantidadGrupo(GestionarCantidadListadoInventarioRequest $request){
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
        $resultado = $this->inventario_services->actualizarInventario($id, $request->toInventarioUpdate());

        return $this->apiResponse($resultado);
    }

    public function historialInventario(int $id){
        $resultado = $this->inventario_services->historialInventario($id);

        return $this->apiResponse($resultado);
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