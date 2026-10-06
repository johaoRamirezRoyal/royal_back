<?php

namespace App\Http\Controllers\Reservas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservas\HoraRequest;
use App\Http\Requests\Reservas\SalonRequest;
use App\Models\Reservas\ConfiguracionReservas;
use App\Models\Reservas\Horas;
use App\Models\Reservas\Salones;
use App\Services\Reservas\ReservasServices;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalonesController extends Controller
{
    // Opción "/reservas/salones" (40) en el frontend: gatea administrar el catálogo de
    // salones (crear/editar/eliminar), NO listarlos — `listarSalones`/`listarHoras` los
    // sigue necesitando cualquier autenticado para reservar (CreateReservaModal, en
    // /reservas, no tiene ni tiene por qué tener este permiso: reservar es de acceso
    // general, administrar el catálogo de salones no).
    private const OPCION_SALONES = 40;

    public function __construct(
        private ReservasServices $reservasServices,
        private UsuariosServices $usuariosService,
    ) {}

    private function sinAcceso(Request $request): ?JsonResponse
    {
        $tienePermiso = $this->usuariosService->tienePermiso(self::OPCION_SALONES, $request->user()->perfil)['permiso'] ?? false;

        return $tienePermiso ? null : $this->error('No tienes permiso para administrar salones', 403);
    }

    /**
     * `token_encuesta`/`encuesta_titulo` vienen null salvo que el salón tenga asignada una
     * encuesta ACTIVA (encuestas_salon + encuestas.activo=1) — CreateReservaModal los usa
     * para decidir si ofrece "Mostrar encuesta" y para armar el QR (/encuestas/{token}).
     * Exponer el token a cualquier autenticado no abre nada nuevo: es el mismo que va
     * impreso en el QR público del salón.
     */
    public function listarSalones()
    {
        $salones = Salones::where('salones.activo', 1)
            ->leftJoin('encuestas_salon as es', 'es.id_salon', '=', 'salones.id')
            ->leftJoin('encuestas as e', fn ($j) => $j->on('e.id', '=', 'es.id_encuesta')->where('e.activo', 1))
            ->get([
                'salones.id', 'salones.nombre', 'salones.portatil', 'salones.sonido',
                DB::raw('CASE WHEN e.id IS NULL THEN NULL ELSE es.token_publico END AS token_encuesta'),
                'e.titulo as encuesta_titulo',
            ]);

        return $this->apiResponse([
            'error' => false,
            'message' => 'Salones obtenidos correctamente.',
            'data' => $salones,
        ]);
    }

    /**
     * GET /api/salones/select
     * Listado liviano (solo id + nombre, salones activos) para poblar cualquier select de
     * salón — a diferencia de listarSalones(), no trae portatil/sonido porque quien solo
     * necesita el nombre para mostrarlo (ej. un filtro) no debería cargar esos campos.
     * Lectura pública, igual que listarSalones/listarHoras: reservar es de acceso general.
     */
    public function salonesSelect()
    {
        return $this->apiResponse([
            'error' => false,
            'message' => 'Salones obtenidos correctamente.',
            'data' => Salones::activo()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function crearSalon(SalonRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse(
            $this->reservasServices->crearSalon($request->validated())
        );
    }

    public function actualizarSalon(SalonRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse(
            $this->reservasServices->actualizarSalon($request->validated(), $id)
        );
    }

    public function eliminarSalon(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse(
            $this->reservasServices->eliminarSalon($id)
        );
    }

    public function listarHoras()
    {
        return $this->apiResponse([
            'error' => false,
            'message' => 'Horas obtenidas correctamente.',
            'data' => Horas::where('activo', 1)->get(['id', 'horas'])
        ]);
    }

    // Mismo gate que el catálogo de salones (opción 40): "definir las horas de reserva"
    // es parte de la misma administración del módulo, no amerita una opción aparte.
    public function crearHora(HoraRequest $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse(
            $this->reservasServices->crearHora($request->validated())
        );
    }

    public function actualizarHora(HoraRequest $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse(
            $this->reservasServices->actualizarHora($request->validated(), $id)
        );
    }

    public function eliminarHora(Request $request, int $id)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        return $this->apiResponse(
            $this->reservasServices->eliminarHora($id)
        );
    }

    /**
     * Ventana de anticipación (días) para poder reservar — editable acá para no tocar el
     * servidor, mismo patrón que InstitucionAdminController::configuracion()/actualizarConfiguracion().
     * Lectura pública para cualquier autenticado (igual que listarSalones/listarHoras):
     * CreateReservaModal la necesita para calcular el rango de fechas reservable, y
     * reservar es de acceso general — solo actualizarConfiguracion() queda tras la
     * opción 40.
     */
    public function configuracion()
    {
        return $this->apiResponse([
            'error' => false,
            'message' => 'Configuración obtenida correctamente.',
            'data' => ConfiguracionReservas::actual(),
        ]);
    }

    public function actualizarConfiguracion(Request $request)
    {
        if ($rechazo = $this->sinAcceso($request)) {
            return $rechazo;
        }

        $request->validate(
            [
                'dias_min_anticipacion' => 'sometimes|integer|min:1|max:365',
                'dias_max_anticipacion' => 'sometimes|integer|min:1|max:365',
            ],
            [
                'dias_min_anticipacion.integer' => 'Los días mínimos de anticipación deben ser un número entero.',
                'dias_min_anticipacion.min' => 'Los días mínimos de anticipación deben ser al menos 1 (no se permite reservar el mismo día).',
                'dias_max_anticipacion.integer' => 'Los días máximos de anticipación deben ser un número entero.',
                'dias_max_anticipacion.min' => 'Los días máximos de anticipación deben ser al menos 1.',
            ],
        );

        $config = ConfiguracionReservas::actual();
        $datos = $request->only(['dias_min_anticipacion', 'dias_max_anticipacion']);

        $diasMin = $datos['dias_min_anticipacion'] ?? $config->dias_min_anticipacion;
        $diasMax = $datos['dias_max_anticipacion'] ?? $config->dias_max_anticipacion;

        if ($diasMax < $diasMin) {
            return $this->apiResponse([
                'error' => true,
                'message' => 'Los días máximos de anticipación no pueden ser menores que los mínimos.',
                'data' => [],
            ]);
        }

        $config->update($datos);

        return $this->apiResponse([
            'error' => false,
            'message' => 'Configuración actualizada correctamente.',
            'data' => $config->fresh(),
        ]);
    }
}
