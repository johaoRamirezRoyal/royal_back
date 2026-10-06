<?php

namespace App\Http\Controllers\Inventarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventario\IndicadorGestionFiltroRequest;
use App\Http\Requests\Inventario\IndicadorGestionRequest;
use App\Services\inventario\IndicadoresGestionServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IndicadoresGestionController extends Controller
{
    // cron_opciones: 94 = "Indicadores de gestion Sistemas" (tipo_categoria 1),
    // 95 = "Indicadores de gestion Aires" (Operativos, tipo_categoria 2).
    private const OPCION_POR_TIPO = [1 => 94, 2 => 95];

    public function __construct(
        private IndicadoresGestionServices $servicio,
        private UsuariosServices $usuariosService,
    ) {
    }

    private function sinAcceso(Request $request, int $tipoCategoria): ?JsonResponse
    {
        $opcion = self::OPCION_POR_TIPO[$tipoCategoria] ?? null;

        if ($opcion && ($this->usuariosService->tienePermiso($opcion, $request->user()->perfil)['permiso'] ?? false)) {
            return null;
        }

        return $this->error('No tienes permiso para esta acción', 403);
    }

    // GET /indicadores-gestion — histórico de indicadores guardados.
    public function listar(IndicadorGestionFiltroRequest $request)
    {
        $tipo = (int) $request->input('tipo_categoria');

        if ($rechazo = $this->sinAcceso($request, $tipo)) {
            return $rechazo;
        }

        return $this->apiResponse($this->servicio->listar(
            $tipo,
            $request->input('id_anio'),
            $request->input('periodo'),
            $request->input('id_categoria')
        ));
    }

    // GET /indicadores-gestion/grafica — comportamiento del % por año/periodo.
    public function grafica(IndicadorGestionFiltroRequest $request)
    {
        $tipo = (int) $request->input('tipo_categoria');

        if ($rechazo = $this->sinAcceso($request, $tipo)) {
            return $rechazo;
        }

        return $this->apiResponse($this->servicio->grafica($tipo, $request->input('id_anio')));
    }

    // POST /indicadores-gestion — guarda el indicador de un periodo (único por año+categoría+periodo).
    public function crear(IndicadorGestionRequest $request)
    {
        $tipo = (int) DB::table('categoria')->where('id', $request->input('categoria'))->value('tipo_categoria');

        if ($rechazo = $this->sinAcceso($request, $tipo)) {
            return $rechazo;
        }

        return $this->apiResponse($this->servicio->crear($request->validated(), $request->user()->id_user ?? null));
    }

    // PUT /indicadores-gestion/{id}/analisis
    public function actualizarAnalisis(Request $request, int $id)
    {
        $data = $request->validate(['analisis' => ['nullable', 'string', 'max:500']]);

        $tipo = (int) DB::table('indicadores_gestion as ig')
            ->join('categoria as c', 'c.id', '=', 'ig.categoria')
            ->where('ig.id', $id)
            ->value('c.tipo_categoria');

        if ($rechazo = $this->sinAcceso($request, $tipo)) {
            return $rechazo;
        }

        return $this->apiResponse($this->servicio->actualizarAnalisis($id, $data['analisis'] ?? null));
    }
}
