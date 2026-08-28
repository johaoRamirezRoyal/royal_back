<?php

namespace App\Http\Controllers\Branding;

use App\Http\Controllers\Controller;
use App\Services\branding\MarcaDominioService;
use Illuminate\Http\Request;

/**
 * Administración de qué logo corresponde a cada dominio de correo (multi-tenant) — se
 * gatea directo por perfil (Super Admin=1 únicamente), no por `cron_opciones`: es un pedido
 * explícito de "solo este rol", no una opción granular más del sistema de permisos habitual
 * (ver docs/sistema-permisos.md para cuándo sí conviene una opción).
 */
class MarcaDominioController extends Controller
{
    private const PERFILES_PERMITIDOS = [1];

    public function __construct(
        private MarcaDominioService $service,
        Request $request,
    ) {
        if (!in_array($request->user()->perfil, self::PERFILES_PERMITIDOS, true)) {
            abort($this->error('No tienes permiso para administrar las marcas por dominio', 403));
        }
    }

    public function listar(Request $request)
    {
        return $this->apiResponse($this->service->listar());
    }

    public function crear(Request $request)
    {
        $request->validate([
            'dominio' => ['required', 'string', 'max:190'],
            'nombre' => ['nullable', 'string', 'max:190'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['required', 'file', 'image', 'max:5120'],
        ]);

        return $this->apiResponse($this->service->crear($request->only(['dominio', 'nombre', 'color']), $request->file('logo')));
    }

    public function actualizar(Request $request)
    {
        $request->validate([
            'id' => ['required', 'integer'],
            'dominio' => ['sometimes', 'string', 'max:190'],
            'nombre' => ['nullable', 'string', 'max:190'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        return $this->apiResponse($this->service->actualizar(
            $request->integer('id'),
            $request->only(['dominio', 'nombre', 'color']),
            $request->file('logo'),
        ));
    }

    public function cambiarEstado(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'activo' => ['required', 'boolean'],
        ]);

        return $this->apiResponse($this->service->cambiarEstado($request->input('ids'), $request->boolean('activo')));
    }

    public function eliminar(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        return $this->apiResponse($this->service->eliminar($request->input('ids')));
    }
}
