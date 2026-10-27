<?php
namespace App\Http\Controllers\Categorias;

use App\Http\Controllers\Controller;
use App\Services\Categorias\CategoriasServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Support\Permiso;
use App\Http\Controllers\Concerns\VerificaPermisos;

class CategoriasController extends Controller{
    use VerificaPermisos;

    // Categorías de inventario. El listado (GET) es de lectura compartida entre los tres
    // módulos que lo consumen — Inventario (12), Mis Inventarios (16) y Préstamos (17),
    // igual que en InventariosController — así que se acepta cualquiera de las tres.
    // Crear/editar categorías son acciones administrativas: solo 12.
    private const OPCION_INVENTARIO = Permiso::INVENTARIO;
    private const OPCION_MIS_INVENTARIOS = Permiso::MIS_INVENTARIOS;
    private const OPCION_PRESTAMOS = Permiso::PRESTAMOS_INVENTARIO;
    // "Mis áreas" (filtro de categoría de Área Común).
    private const OPCION_MIS_AREAS_NIVEL = Permiso::MIS_AREAS_NIVEL;
    private const OPCION_MIS_AREAS_RESPONSABLE = Permiso::MIS_AREAS_RESPONSABLE;

    protected $categoria_services;

    public function __construct(
        CategoriasServices $categoriasServices,
    ) {
        $this->categoria_services = $categoriasServices;
    }

    public function obtenerTodasLasCategorias(Request $request){
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_MIS_INVENTARIOS, self::OPCION_PRESTAMOS, self::OPCION_MIS_AREAS_NIVEL, self::OPCION_MIS_AREAS_RESPONSABLE)) {
            return $rechazo;
        }

        $categoria = $this->categoria_services->obtenerTodasLasCategorias();
        $code = match (true) {
            $categoria['error'] && str_contains($categoria['message'], 'SQL') => 500,
            $categoria['error'] => 400,
            default => 200,
        };

        return response()->json($categoria, $code);
    }

    public function agregarNuevaCategoria(Request $request){
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $datos = $request->all();

        $validator = Validator::make($datos,[
            "nombre" => "required|string",
            "tipo_categoria" => "required|integer|exists:subcategoria_inventario,id",
            "activo" => "numeric",
        ]);

        if($validator->fails()){
            return response()->json([
                    "error" => true,
                    "message" => $validator->errors()->first(),
            ], 422);
        }

        $categoria = $this->categoria_services->agregarNuevaCategoria($datos);

        $status = match (true) {
            $categoria['error'] && str_contains($categoria['message'], "SQL") => 500,
            $categoria['error'] => 400,
            default => 200,
        };

        return response()->json($categoria, $status);
    }

    public function actualizarCategoria(Request $request){
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO)) {
            return $rechazo;
        }

        $datos = $request->except("ids");
        $ids = $request->input("ids");

        $validator = Validator::make($request->all(), [
            "ids" => "required|array|min:1",
            "ids.*" => "integer|distinct|exists:categoria,id",

            "nombre" => "sometimes|string",
            "tipo_categoria" => "sometimes|integer|exists:subcategoria_inventario,id",
            "activo" => "sometimes|numeric",
        ]);

        if ($validator->fails()) {
            return response()->json([
                "error" => true,
                "message" => $validator->errors()->first(),
            ], 422);
        }

        $actualizacion = $this->categoria_services->actualizarCategoria($datos, $ids);
        $status = match (true) {
            $actualizacion['error'] && str_contains($actualizacion['message'], "SQL") => 500,
            $actualizacion['error'] => 400,
            default => 200,
        };

        return response()->json($actualizacion, $status);
    }
}