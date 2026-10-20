<?php

namespace App\Http\Controllers\Branding;

use App\Http\Controllers\Controller;
use App\Services\branding\MarcaDominioService;
use Illuminate\Http\Request;
use App\Support\Permiso;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Administración de qué logo corresponde a cada dominio de correo (multi-tenant) — se
 * gatea directo por perfil (Super Admin=1 únicamente), no por `cron_opciones`: es un pedido
 * explícito de "solo este rol", no una opción granular más del sistema de permisos habitual
 * (ver docs/sistema-permisos.md para cuándo sí conviene una opción).
 */
class MarcaDominioController extends Controller implements HasMiddleware
{
    /** Solo Super Admin. Mismo permiso que su ruta: el controller no depende de que la ruta lo tenga. */
    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]))];
    }

    public function __construct(
        private MarcaDominioService $service,
    ) {
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
            'descripcion' => ['nullable', 'string', 'max:190'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['required', 'file', 'image', 'max:5120'],
            'imagen' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        return $this->apiResponse($this->service->crear(
            $request->only(['dominio', 'nombre', 'descripcion', 'color']),
            $request->file('logo'),
            $request->file('imagen'),
        ));
    }

    public function actualizar(Request $request)
    {
        $request->validate([
            'id' => ['required', 'integer'],
            'dominio' => ['sometimes', 'string', 'max:190'],
            'nombre' => ['nullable', 'string', 'max:190'],
            'descripcion' => ['nullable', 'string', 'max:190'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'file', 'image', 'max:5120'],
            'imagen' => ['nullable', 'file', 'image', 'max:5120'],
            'quitar_imagen' => ['sometimes', 'boolean'],
        ]);

        return $this->apiResponse($this->service->actualizar(
            $request->integer('id'),
            $request->only(['dominio', 'nombre', 'descripcion', 'color']),
            $request->file('logo'),
            $request->file('imagen'),
            $request->boolean('quitar_imagen'),
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
