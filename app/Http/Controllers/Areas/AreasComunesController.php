<?php

namespace App\Http\Controllers\Areas;

use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Models\Areas\Areas;
use App\Models\Areas\Bloque;
use App\Models\Inventario\Inventario;
use App\Services\Areas\AreasServices;
use App\Services\inventario\InventarioServices;
use App\Services\Usuarios\PermisosPerfil;
use App\Support\Permiso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Acciones administrativas masivas de Áreas Comunes que cruzan `areas`/`inventario`
 * — se quedan aparte de AreasController/InventariosController porque su permiso no
 * es OPCION_INVENTARIO (12) sino "Administrador areas comunes" (108, cron_opciones
 * bajo id_modulo=6 "Zonas" — ver migración 2026_09_10_150000_seed_opciones_areas_comunes).
 */
class AreasComunesController extends Controller
{
    use VerificaPermisos;

    private const OPCION_ADMIN_AREAS_COMUNES = Permiso::AREAS_COMUNES_ADMIN;

    private const OPCION_USO_AREAS_COMUNES = Permiso::AREAS_COMUNES_USO;

    // "Mis áreas comunes" (autoservicio) — sumada a registrarCheck por consistencia con
    // InventariosController::listadoConsolidado/reportarInventario, aunque hoy la
    // vista de autoservicio (`misAreas`) ya no ofrece el check, solo Reportar.
    private const OPCION_MIS_AREAS_COMUNES = Permiso::MIS_AREAS_COMUNES;

    // "Mis áreas" bajo Mis inventarios (/inventario/mis-areas) — ver misAreas().
    private const OPCION_MIS_AREAS_NIVEL = Permiso::MIS_AREAS_NIVEL;

    private const OPCION_MIS_AREAS_RESPONSABLE = Permiso::MIS_AREAS_RESPONSABLE;

    public function __construct(
        private AreasServices $areasService,
        private InventarioServices $inventarioService,
    ) {}

    public function asignarAreasBloque(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'id_bloque' => ['required', 'integer', 'exists:bloques,id'],
            'ids' => ['present', 'array'],
            'ids.*' => ['integer', 'exists:areas,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $resultado = $this->areasService->asignarAreasBloque(
            $request->input('id_bloque'),
            $request->input('ids', []),
        );

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    public function reclasificarInventario(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:inventario,id'],
            'id_categoria' => ['required', 'integer', 'exists:categoria,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $resultado = $this->inventarioService->reclasificarAreaComun(
            $request->input('ids'),
            $request->input('id_categoria'),
            $request->user()->id_user,
        );

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    /**
     * Check semestral de inventario (migración del legacy chek_zonas) — tanto
     * admin como uso diario de Áreas Comunes pueden registrarlo.
     */
    public function registrarCheck(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES, self::OPCION_MIS_AREAS_COMUNES)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:inventario,id'],
            'id_anio' => ['nullable', 'integer', 'exists:anio_escolar,id'],
            'periodo' => ['nullable', 'integer'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $resultado = $this->inventarioService->registrarCheckInventario(
            $request->input('ids'),
            $request->input('id_anio'),
            $request->input('periodo'),
            $request->user()->id_user,
        );

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    /** Mueve un ítem ya existente a otro bloque/área — ver InventarioServices::moverItemAreaComun. */
    public function moverItem(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'exists:inventario,id'],
            'id_bloque' => ['required', 'integer', 'exists:bloques,id'],
            'id_area' => ['nullable', 'integer', 'exists:areas,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $resultado = $this->inventarioService->moverItemAreaComun(
            $request->input('id'),
            $request->input('id_bloque'),
            $request->input('id_area'),
            $request->user()->id_user,
        );

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    /**
     * GET /bloques/inventario/mis-areas — ítems de Área Común que el usuario puede ver y
     * reportar desde "Mis áreas". El alcance se arma acá, no desde filtros del cliente:
     *   - 141: bloques activos de su nivel (lo directo en el bloque y lo de sus áreas).
     *   - 142: áreas de las que es responsable (tiene ítems con inventario.id_user = él,
     *     como lo deja "Asignar área" en /inventario/areas).
     * Con las dos, la unión.
     */
    public function misAreas(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_MIS_AREAS_NIVEL, self::OPCION_MIS_AREAS_RESPONSABLE)) {
            return $rechazo;
        }

        $usuario = $request->user();
        $idsBloque = [];
        $idsArea = [];

        if (PermisosPerfil::tiene($request, self::OPCION_MIS_AREAS_NIVEL) && $usuario->id_nivel) {
            $idsBloque = Bloque::where('id_nivel', $usuario->id_nivel)->where('activo', 1)->pluck('id')->all();
            $idsArea = Areas::whereIn('id_bloque', $idsBloque)->where('activo', 1)->pluck('id')->all();
        }

        if (PermisosPerfil::tiene($request, self::OPCION_MIS_AREAS_RESPONSABLE)) {
            $idsArea = array_merge($idsArea, Inventario::where('id_user', $usuario->id_user)
                ->whereIn('id_area', Areas::where('activo', 1)->select('id'))
                ->distinct()
                ->pluck('id_area')
                ->all());
        }

        if (empty($idsBloque) && empty($idsArea)) {
            return response()->json(['error' => false, 'message' => '', 'data' => ['data' => []]]);
        }

        $filtros = array_filter([
            'tipo_categoria' => 3,
            'individual' => 1,
            'solo_areas_activas' => 1,
            'id_bloque' => $idsBloque,
            'id_area' => array_values(array_unique($idsArea)),
            'id_categoria' => $request->input('id_categoria'),
            's' => $request->input('s'),
        ]);

        $resultado = $this->inventarioService->obtenerListadoConsolidado($filtros, 10000);

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    /** Historial completo de checks semestrales — ver InventarioServices::historialChecks. */
    public function historialChecks(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES)) {
            return $rechazo;
        }

        $filtros = $request->only(['id_inventario', 'id_bloque', 'id_area', 'id_anio', 'periodo', 'id_responsable', 's']);
        $perPage = (int) $request->input('per-page', 15);

        $resultado = $this->inventarioService->historialChecks($filtros, $perPage);

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    /** % de áreas comunes con al menos un check — ver InventarioServices::indicadorChecksAreasComunes. */
    public function indicadorChecks(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES)) {
            return $rechazo;
        }

        $filtros = $request->only(['id_bloque', 'id_area', 'id_anio', 'periodo']);

        $resultado = $this->inventarioService->indicadorChecksAreasComunes($filtros);

        return response()->json($resultado, $resultado['error'] ? 400 : 200);
    }

    /**
     * PDF "Checklist" de Historial de Checks — ver
     * InventarioServices::generarHistorialChecksPdf. `anio_label`/`periodo_label` los
     * manda el frontend ya resueltos (mismo `labelPeriodo` que usa la página), no se
     * recalculan acá.
     */
    public function historialChecksPdf(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_ADMIN_AREAS_COMUNES, self::OPCION_USO_AREAS_COMUNES)) {
            return $rechazo;
        }

        $validator = Validator::make($request->all(), [
            'id_bloque' => ['nullable', 'integer', 'exists:bloques,id'],
            'id_area' => ['nullable', 'integer', 'exists:areas,id'],
            'id_anio' => ['nullable', 'integer', 'exists:anio_escolar,id'],
            'periodo' => ['nullable', 'integer'],
            'anio_label' => ['required', 'string'],
            'periodo_label' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $filtros = $request->only(['id_bloque', 'id_area', 'id_anio', 'periodo']);

        $resultado = $this->inventarioService->generarHistorialChecksPdf(
            $filtros,
            $request->input('anio_label'),
            $request->input('periodo_label'),
            $request->user()->id_user,
        );

        if ($resultado['error']) {
            return response()->json($resultado, 400);
        }

        return response($resultado['data']['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$resultado['data']['nombre_archivo'].'"',
        ]);
    }
}
