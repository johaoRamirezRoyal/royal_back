<?php

namespace App\Http\Controllers\Cafeteria;

use App\Enums\Cafeteria\EstadoCafeteria;
use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cafeteria\AlmacenesPuntoRequest;
use App\Http\Requests\Cafeteria\AlmacenRequest;
use App\Http\Requests\Cafeteria\CafeteriaRequest;
use App\Http\Requests\Cafeteria\HorariosRequest;
use App\Http\Requests\Cafeteria\PuntoVentaRequest;
use App\Http\Requests\Cafeteria\RolOperativoRequest;
use App\Http\Requests\Cafeteria\TerminalRequest;
use App\Services\Cafeteria\EstructuraCafeteriaService;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Estructura de la cafetería (opción 176): cafeterías, puntos de venta, terminales, horarios,
 * almacenes y funciones del personal. Los catálogos y la lista de funciones también los lee
 * quien tiene Personal (177). Reglas en EstructuraCafeteriaService.
 */
class EstructuraCafeteriaController extends Controller
{
    use VerificaPermisos;

    public function __construct(private EstructuraCafeteriaService $service)
    {
    }

    /** GET /api/cafeteria/catalogos — cafeterías, funciones, días, estados y tipos (176 o 177). */
    public function catalogos(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CAFETERIA_ESTRUCTURA, Permiso::CAFETERIA_PERSONAL)) {
            return $rechazo;
        }

        return $this->apiResponse($this->service->catalogos());
    }

    /** GET /api/cafeteria/cafeterias */
    public function index(Request $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->listarCafeterias());
    }

    /** GET /api/cafeteria/cafeterias/{cafeteria} — con puntos, terminales, horarios y almacenes. */
    public function show(Request $request, int $cafeteria): JsonResponse
    {
        return $this->con($request, fn () => $this->service->detalleCafeteria($cafeteria));
    }

    /** POST /api/cafeteria/cafeterias { codigo, nombre, descripcion? } — nace en BORRADOR. */
    public function store(CafeteriaRequest $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->crearCafeteria($request->validated(), $request->user()->id_user));
    }

    /** PUT /api/cafeteria/cafeterias/{cafeteria} { codigo, nombre, descripcion? } */
    public function update(CafeteriaRequest $request, int $cafeteria): JsonResponse
    {
        return $this->con($request, fn () => $this->service->actualizarCafeteria($cafeteria, $request->validated(), $request->user()->id_user));
    }

    /** PUT /api/cafeteria/cafeterias/{cafeteria}/estado { estado } — solo transiciones válidas. */
    public function estado(Request $request, int $cafeteria): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CAFETERIA_ESTRUCTURA)) {
            return $rechazo;
        }

        $datos = $request->validate(
            ['estado' => ['required', Rule::enum(EstadoCafeteria::class)]],
            ['estado.required' => 'Elige el estado', 'estado.enum' => 'El estado no es válido'],
        );

        return $this->apiResponse($this->service->cambiarEstado($cafeteria, EstadoCafeteria::from($datos['estado']), $request->user()->id_user));
    }

    /** PUT /api/cafeteria/cafeterias/{cafeteria}/horarios { horarios: [...] } */
    public function horariosCafeteria(HorariosRequest $request, int $cafeteria): JsonResponse
    {
        return $this->con($request, fn () => $this->service->guardarHorariosCafeteria($cafeteria, $request->validated()['horarios']));
    }

    /** POST /api/cafeteria/cafeterias/{cafeteria}/puntos-venta */
    public function storePuntoVenta(PuntoVentaRequest $request, int $cafeteria): JsonResponse
    {
        return $this->con($request, fn () => $this->service->crearPuntoVenta($cafeteria, $request->validated()));
    }

    /** PUT /api/cafeteria/puntos-venta/{puntoVenta} */
    public function updatePuntoVenta(PuntoVentaRequest $request, int $puntoVenta): JsonResponse
    {
        return $this->con($request, fn () => $this->service->actualizarPuntoVenta($puntoVenta, $request->validated()));
    }

    /** PUT /api/cafeteria/puntos-venta/{puntoVenta}/horarios { horarios: [...] } */
    public function horariosPunto(HorariosRequest $request, int $puntoVenta): JsonResponse
    {
        return $this->con($request, fn () => $this->service->guardarHorariosPunto($puntoVenta, $request->validated()['horarios']));
    }

    /** PUT /api/cafeteria/puntos-venta/{puntoVenta}/almacenes { almacenes: [{ id_almacen, es_predeterminado }] } */
    public function almacenesPunto(AlmacenesPuntoRequest $request, int $puntoVenta): JsonResponse
    {
        return $this->con($request, fn () => $this->service->guardarAlmacenesPunto($puntoVenta, $request->validated()['almacenes']));
    }

    /** POST /api/cafeteria/puntos-venta/{puntoVenta}/terminales */
    public function storeTerminal(TerminalRequest $request, int $puntoVenta): JsonResponse
    {
        return $this->con($request, fn () => $this->service->crearTerminal($puntoVenta, $request->validated()));
    }

    /** PUT /api/cafeteria/terminales/{terminal} */
    public function updateTerminal(TerminalRequest $request, int $terminal): JsonResponse
    {
        return $this->con($request, fn () => $this->service->actualizarTerminal($terminal, $request->validated()));
    }

    /** GET /api/cafeteria/almacenes?id_cafeteria={id|central} */
    public function almacenes(Request $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->listarAlmacenes($request->only('id_cafeteria')));
    }

    /** POST /api/cafeteria/almacenes */
    public function storeAlmacen(AlmacenRequest $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->crearAlmacen($request->validated(), $request->user()->id_user));
    }

    /** PUT /api/cafeteria/almacenes/{almacen} */
    public function updateAlmacen(AlmacenRequest $request, int $almacen): JsonResponse
    {
        return $this->con($request, fn () => $this->service->actualizarAlmacen($almacen, $request->validated()));
    }

    /** POST /api/cafeteria/roles-operativos */
    public function storeRol(RolOperativoRequest $request): JsonResponse
    {
        return $this->con($request, fn () => $this->service->crearRol($request->validated()));
    }

    /** PUT /api/cafeteria/roles-operativos/{rol} */
    public function updateRol(RolOperativoRequest $request, int $rol): JsonResponse
    {
        return $this->con($request, fn () => $this->service->actualizarRol($rol, $request->validated()));
    }

    /** Todas las acciones de estructura exigen la 176 (igual que su ruta). */
    private function con(Request $request, callable $accion): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CAFETERIA_ESTRUCTURA)) {
            return $rechazo;
        }

        return $this->apiResponse($accion());
    }
}
