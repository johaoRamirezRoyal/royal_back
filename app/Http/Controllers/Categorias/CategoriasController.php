<?php
namespace App\Http\Controllers\Categorias;

use App\Http\Controllers\Controller;
use App\Services\Categorias\CategoriasServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoriasController extends Controller{
    // Categorías de inventario. El listado (GET) es de lectura compartida entre los tres
    // módulos que lo consumen — Inventario (12), Mis Inventarios (16) y Préstamos (17),
    // igual que en InventariosController — así que se acepta cualquiera de las tres.
    // Crear/editar categorías son acciones administrativas: solo 12.
    private const OPCION_INVENTARIO = 12;
    private const OPCION_MIS_INVENTARIOS = 16;
    private const OPCION_PRESTAMOS = 17;

    protected $categoria_services;

    public function __construct(
        CategoriasServices $categoriasServices,
        private UsuariosServices $usuariosService,
    ) {
        $this->categoria_services = $categoriasServices;
    }

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

    public function obtenerTodasLasCategorias(Request $request){
        if ($rechazo = $this->sinAcceso($request, self::OPCION_INVENTARIO, self::OPCION_MIS_INVENTARIOS, self::OPCION_PRESTAMOS)) {
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
            "tipo_categoria" => "required|numeric",
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
            "tipo_categoria" => "sometimes|numeric",
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