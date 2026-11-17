<?php

namespace App\Http\Controllers\CursosAltura;

use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Http\Requests\CursosAltura\CursoAlturaRequest;
use App\Models\CursosAltura\CursoAltura;
use App\Models\CursosAltura\EntidadSeguridadSocial;
use App\Models\CursosAltura\TrabajoAltura;
use App\Services\CursosAltura\CursosAlturaServices;
use App\Services\Usuarios\PermisosPerfil;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class CursosAlturaController extends Controller
{
    use VerificaPermisos;

    // 143 = CRUD de cursos (Gestión Humana).
    private const OPCION_GESTION = Permiso::CURSOS_ALTURA;

    // 150 = EPS/AFP/ARL de los trabajadores con curso y su catálogo de entidades.
    private const OPCION_SEGURIDAD_SOCIAL = Permiso::SEGURIDAD_SOCIAL;

    // Quienes reportan o programan mantenimientos de Áreas Comunes: consultan los
    // operativos vigentes y generan el permiso de alturas.
    private const OPCIONES_AREAS = [
        Permiso::INVENTARIO,
        Permiso::AREAS_COMUNES_ADMIN,
        Permiso::AREAS_COMUNES_USO,
        Permiso::MIS_AREAS_COMUNES,
        Permiso::MIS_AREAS_NIVEL,
        Permiso::MIS_AREAS_RESPONSABLE,
    ];

    public function __construct(
        private CursosAlturaServices $cursosAltura,
    ) {}

    /**
     * GET /api/cursos-altura?per-page=&s=
     */
    public function listar(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        $s = $request->input('s') ? trim($request->input('s')) : null;

        // Con la 150, cada fila trae también la EPS/AFP/ARL del trabajador (columna de la tabla).
        $conSeguridadSocial = PermisosPerfil::tiene($request, self::OPCION_SEGURIDAD_SOCIAL);

        return $this->paginatedResponse(
            $this->cursosAltura->listar(['s' => $s], (int) $request->input('per-page', 15), $conSeguridadSocial)
        );
    }

    /**
     * GET /api/cursos-altura/operativos — operativos a los que se les puede registrar curso.
     */
    public function operativos(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->operativos());
    }

    /**
     * GET /api/cursos-altura/operativos-vigentes?fecha= — vigentes en `fecha` (hoy por
     * defecto). También con 143: el editor de Trabajos en altura lo usa.
     */
    public function operativosVigentes(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION, ...self::OPCIONES_AREAS)) {
            return $rechazo;
        }

        $data = $request->validate(['fecha' => ['nullable', 'date']]);

        return $this->apiResponse($this->cursosAltura->operativosVigentes($data['fecha'] ?? null));
    }

    /**
     * GET /api/cursos-altura/trabajos?per-page=&s= — trabajos en altura (Gestión Humana).
     */
    public function listarTrabajos(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        $s = $request->input('s') ? trim($request->input('s')) : null;

        return $this->paginatedResponse(
            $this->cursosAltura->listarTrabajos(['s' => $s], (int) $request->input('per-page', 15))
        );
    }

    /**
     * GET /api/cursos-altura/trabajos/ubicaciones — bloques activos con sus áreas, para el
     * formulario del trabajo manual (Gestión Humana no tiene las opciones de /api/bloques).
     */
    public function ubicaciones(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->ubicaciones());
    }

    /**
     * POST /api/cursos-altura/trabajos — trabajo en altura sin reporte ni mantenimiento: tipo,
     * bloque, área?, elemento, descripción, fecha, al menos un trabajador con curso vigente
     * en esa fecha y los opcionales del permiso (altura, hora de inicio, fecha de finalización).
     */
    public function crearTrabajo(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        // La fecha primero: la regla de vigencia la necesita válida.
        $fecha = $request->validate(['fecha_trabajo' => ['required', 'date']])['fecha_trabajo'];
        $datos = $request->validate([
            ...$this->reglasTrabajo(manual: true),
            ...CursoAltura::reglasOperativos($fecha),
            'id_operativos' => ['required', 'array', 'min:1'],
        ], ['id_operativos.required' => 'Seleccione al menos un trabajador.']);

        return $this->apiResponse($this->cursosAltura->crearTrabajo(
            [...$datos, 'fecha_trabajo' => $fecha],
            array_map('intval', $datos['id_operativos']),
            $request->user()->id_user
        ));
    }

    /**
     * PUT /api/cursos-altura/trabajos/{id} — fecha, trabajadores y opcionales del permiso; en
     * uno manual también tipo, ubicación, elemento y descripción. Uno de reporte sin
     * trabajadores se borra. Cada trabajador debe tener curso vigente en la fecha del trabajo.
     */
    public function actualizarTrabajo(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        // La fecha primero: la regla de vigencia la necesita válida.
        $fecha = $request->validate(['fecha_trabajo' => ['required', 'date']])['fecha_trabajo'];
        $manual = TrabajoAltura::find($id)?->esManual() ?? false;
        $datos = $request->validate([...$this->reglasTrabajo($manual), ...CursoAltura::reglasOperativos($fecha)]);

        return $this->apiResponse($this->cursosAltura->actualizarTrabajo(
            $id,
            [...$datos, 'fecha_trabajo' => $fecha],
            array_map('intval', $datos['id_operativos'] ?? []),
            $request->user()->id_user
        ));
    }

    /**
     * PUT /api/cursos-altura/trabajos/{id}/estado — `finalizado` true/false, solo en los creados
     * en Gestión Humana (los de un reporte toman el estado del reporte).
     */
    public function cambiarEstadoTrabajo(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        $data = $request->validate(['finalizado' => ['required', 'boolean']]);

        return $this->apiResponse($this->cursosAltura->cambiarEstadoTrabajo($id, (bool) $data['finalizado'], $request->user()->id_user));
    }

    /**
     * DELETE /api/cursos-altura/trabajos/{id} — solo los creados en Gestión Humana.
     */
    public function eliminarTrabajo(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->eliminarTrabajo($id));
    }

    /**
     * GET /api/cursos-altura/trabajos/{id}/permiso/pdf — permiso de alturas de un trabajo
     * (de reporte o manual).
     */
    public function permisoTrabajoPdf(Request $request, int $id): JsonResponse|Response
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->respuestaPdf($this->cursosAltura->permisoTrabajoPdf($id));
    }

    /**
     * Reglas del trabajo en altura. Los opcionales del permiso valen para cualquiera; tipo,
     * bloque, área, elemento y descripción solo para uno manual (en uno de reporte salen del
     * reporte), y ahí son obligatorios salvo el área (sin ella, el permiso dice "General").
     *
     * @return array<string, array<int, mixed>>
     */
    private function reglasTrabajo(bool $manual): array
    {
        $opcionales = [
            'altura_aproximada' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'hora_inicio' => ['nullable', 'date_format:H:i'],
            'fecha_finalizacion' => ['nullable', 'date', 'after_or_equal:fecha_trabajo'],
        ];

        if (! $manual) {
            return $opcionales;
        }

        return [
            ...$opcionales,
            'tipo_trabajo' => ['required', 'string', 'max:100'],
            'id_bloque' => ['required', 'integer', Rule::exists('bloques', 'id')->where('activo', 1)],
            'id_area' => ['nullable', 'integer', Rule::exists('areas', 'id')->where('activo', 1)],
            'elemento' => ['required', 'string', 'max:190'],
            'descripcion' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * POST /api/cursos-altura
     */
    public function crear(CursoAlturaRequest $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->crear(
            $request->validated(),
            $request->user()->id_user,
            $request->file('certificado')
        ));
    }

    /**
     * POST /api/cursos-altura/{id} — POST y no PUT: PHP no parsea multipart en PUT.
     */
    public function actualizar(CursoAlturaRequest $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->actualizar(
            $id,
            $request->validated(),
            $request->user()->id_user,
            $request->file('certificado')
        ));
    }

    /**
     * DELETE /api/cursos-altura/{id} — baja lógica (activo=0).
     */
    public function eliminar(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->eliminar($id, $request->user()->id_user));
    }

    /**
     * GET /api/cursos-altura/entidades — catálogo de EPS, AFP y ARL.
     */
    public function entidades(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_SEGURIDAD_SOCIAL)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->entidades());
    }

    /**
     * POST /api/cursos-altura/entidades {tipo, nombre}
     */
    public function crearEntidad(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_SEGURIDAD_SOCIAL)) {
            return $rechazo;
        }

        $data = $request->validate([
            'tipo' => ['required', Rule::in(EntidadSeguridadSocial::TIPOS)],
            'nombre' => ['required', 'string', 'max:150', $this->nombreUnico($request->input('tipo'))],
        ], ['nombre.unique' => 'Ya existe una entidad con ese nombre.']);

        return $this->apiResponse($this->cursosAltura->crearEntidad($data['tipo'], trim($data['nombre']), $request->user()->id_user));
    }

    /**
     * PUT /api/cursos-altura/entidades/{id} {nombre} — renombrar.
     */
    public function actualizarEntidad(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_SEGURIDAD_SOCIAL)) {
            return $rechazo;
        }

        $tipo = EntidadSeguridadSocial::where('id', $id)->value('tipo');
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150', $this->nombreUnico($tipo)->ignore($id)],
        ], ['nombre.unique' => 'Ya existe una entidad con ese nombre.']);

        return $this->apiResponse($this->cursosAltura->actualizarEntidad($id, trim($data['nombre']), $request->user()->id_user));
    }

    /**
     * DELETE /api/cursos-altura/entidades/{id} — baja lógica: los trabajadores que la tienen la conservan.
     */
    public function eliminarEntidad(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_SEGURIDAD_SOCIAL)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->eliminarEntidad($id, $request->user()->id_user));
    }

    /** Nombre no repetido entre las entidades activas del tipo (una inactiva se reactiva al crearla). */
    private function nombreUnico(?string $tipo): Unique
    {
        return Rule::unique('entidades_seguridad_social', 'nombre')->where('tipo', $tipo)->where('activo', 1);
    }

    /**
     * GET /api/cursos-altura/seguridad-social/{idUser} — EPS/AFP/ARL del trabajador (data null si no tiene).
     */
    public function seguridadSocial(Request $request, int $idUser): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_SEGURIDAD_SOCIAL)) {
            return $rechazo;
        }

        return $this->apiResponse($this->cursosAltura->seguridadSocial($idUser));
    }

    /**
     * POST /api/cursos-altura/seguridad-social/{idUser} — multipart: id_eps|id_afp|id_arl y
     * documento_eps|documento_afp|documento_arl (opcionales; sin archivo se conserva el actual).
     */
    public function guardarSeguridadSocial(Request $request, int $idUser): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_SEGURIDAD_SOCIAL)) {
            return $rechazo;
        }

        $reglas = [];
        foreach (EntidadSeguridadSocial::TIPOS as $tipo) {
            $campo = strtolower($tipo);
            $reglas["id_{$campo}"] = ['nullable', 'integer',
                Rule::exists('entidades_seguridad_social', 'id')->where('tipo', $tipo)];
            $reglas["documento_{$campo}"] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'];
        }
        $data = $request->validate($reglas);

        return $this->apiResponse($this->cursosAltura->guardarSeguridadSocial(
            $idUser,
            $data,
            $request->user()->id_user
        ));
    }

    /**
     * GET /api/cursos-altura/permiso/{idReporte}/pdf — Permiso de Trabajo en Alturas.
     */
    public function permisoPdf(Request $request, int $idReporte): JsonResponse|Response
    {
        if ($rechazo = $this->sinAcceso($request, self::OPCION_GESTION, ...self::OPCIONES_AREAS)) {
            return $rechazo;
        }

        return $this->respuestaPdf($this->cursosAltura->permisoAlturasPdf($idReporte));
    }

    /** El PDF inline, o el error del service como JSON. */
    private function respuestaPdf(array $resultado): JsonResponse|Response
    {
        if ($resultado['error']) {
            return $this->apiResponse($resultado);
        }

        return response($resultado['data']['contenido'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$resultado['data']['nombre_archivo'].'"',
        ]);
    }
}
