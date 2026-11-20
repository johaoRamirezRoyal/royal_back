<?php

namespace App\Http\Controllers\CatalogosUsuarios;

use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Http\Requests\CatalogosUsuarios\GuardarNivelRequest;
use App\Services\Niveles\NivelesServices;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD administrativo de `nivel` (clasificación general de usuario — no confundir con
 * `nivel_academico`, ya administrado en Configuración Académica). Opción 224, compartida
 * con PerfilesController. Nunca se borran filas (referenciadas por usuarios.id_nivel).
 * La lectura para selects de otros módulos sigue en GET /api/usuarios/niveles.
 */
class NivelesCatalogoController extends Controller
{
    use VerificaPermisos;

    public function __construct(private NivelesServices $niveles)
    {
    }

    public function index(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CATALOGOS_USUARIOS)) {
            return $rechazo;
        }

        return $this->apiResponse($this->niveles->mostrarTodosNiveles());
    }

    public function store(GuardarNivelRequest $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CATALOGOS_USUARIOS)) {
            return $rechazo;
        }

        $datos = $request->validated();
        $datos['user_log'] = $request->user()->id_user;
        $datos['fechareg'] = now();

        return $this->apiResponse($this->niveles->crear($datos));
    }

    public function update(GuardarNivelRequest $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CATALOGOS_USUARIOS)) {
            return $rechazo;
        }

        $datos = $request->validated();
        $datos['user_log'] = $request->user()->id_user;

        return $this->apiResponse($this->niveles->actualizar($id, $datos));
    }

    /** PUT /administrativo/niveles/estado { ids: [], activo: bool } */
    public function estado(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::CATALOGOS_USUARIOS)) {
            return $rechazo;
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'activo' => ['required', 'boolean'],
        ]);

        return $this->apiResponse($this->niveles->actualizarEstado($validated['ids'], $validated['activo']));
    }
}
