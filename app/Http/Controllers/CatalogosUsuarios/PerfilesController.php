<?php

namespace App\Http\Controllers\CatalogosUsuarios;

use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogosUsuarios\GuardarPerfilRequest;
use App\Services\Perfiles\PerfilesServices;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD administrativo de `perfiles` (opción 224): crear, editar, activar/desactivar.
 * Nunca se borran filas (están referenciadas por usuarios y cron_permisos). El id de
 * Super Admin (Permiso::PERFIL_SUPER_ADMIN) nunca se edita ni se inactiva desde aquí.
 * La lectura para selects de otros módulos sigue en GET /api/usuarios/perfiles.
 */
class PerfilesController extends Controller
{
    use VerificaPermisos;

    public function __construct(private PerfilesServices $perfiles)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CATALOGOS_USUARIOS)) {
            return $rechazo;
        }

        return $this->apiResponse($this->perfiles->mostrarTodosPerfiles());
    }

    public function store(GuardarPerfilRequest $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CATALOGOS_USUARIOS)) {
            return $rechazo;
        }

        $datos = $request->validated();
        $datos['user_log'] = $request->user()->id_user;
        $datos['fechareg'] = now();

        return $this->apiResponse($this->perfiles->crear($datos));
    }

    public function update(GuardarPerfilRequest $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CATALOGOS_USUARIOS)) {
            return $rechazo;
        }

        $request->merge(['id_perfil' => $id])->validate([
            'id_perfil' => 'integer|not_in:' . Permiso::PERFIL_SUPER_ADMIN,
        ], ['id_perfil.not_in' => 'Super Admin no se edita desde aquí.']);

        $datos = $request->validated();
        $datos['user_log'] = $request->user()->id_user;

        return $this->apiResponse($this->perfiles->actualizar($id, $datos));
    }

    /** PUT /administrativo/perfiles/estado { ids: [], estado: activo|inactivo } */
    public function estado(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CATALOGOS_USUARIOS)) {
            return $rechazo;
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'not_in:' . Permiso::PERFIL_SUPER_ADMIN],
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
        ], [
            'ids.*.not_in' => 'Super Admin no se inactiva.',
        ]);

        return $this->apiResponse($this->perfiles->actualizarEstado($validated['ids'], $validated['estado']));
    }
}
