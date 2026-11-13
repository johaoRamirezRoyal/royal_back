<?php

namespace App\Http\Controllers\Branding;

use App\Http\Controllers\Controller;
use App\Services\branding\MarcaService;
use App\Support\Permiso;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Marcas (identidad de cada institución) y sus dominios de correo. Solo Super Admin, por
 * perfil y no por `cron_opciones` (ver docs/sistema-permisos.md).
 */
class MarcaController extends Controller implements HasMiddleware
{
    /** Solo Super Admin. Mismo permiso que su ruta: el controller no depende de que la ruta lo tenga. */
    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]))];
    }

    private const REGLAS = [
        'nombre' => ['required', 'string', 'max:190'],
        'descripcion' => ['nullable', 'string', 'max:190'],
        'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        'dominios' => ['nullable', 'array', 'max:50'],
        'dominios.*' => ['string', 'max:190'],
        'logo' => ['nullable', 'file', 'image', 'max:5120'],
        'logo_secundario' => ['nullable', 'file', 'image', 'max:5120'],
        'imagen' => ['nullable', 'file', 'image', 'max:5120'],
        'quitar' => ['nullable', 'array'],
        'quitar.*' => ['in:logo,logo_secundario,imagen'],
    ];

    public function __construct(private MarcaService $service)
    {
    }

    public function listar()
    {
        return $this->apiResponse($this->service->listar());
    }

    public function listarDominios()
    {
        return $this->apiResponse($this->service->listarDominios());
    }

    public function crear(Request $request)
    {
        $request->validate(self::REGLAS);

        return $this->apiResponse($this->guardar(null, $request));
    }

    public function actualizar(Request $request)
    {
        $request->validate(['id' => ['required', 'integer']] + self::REGLAS);

        return $this->apiResponse($this->guardar($request->integer('id'), $request));
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

    private function guardar(?int $id, Request $request): array
    {
        return $this->service->guardar(
            $id,
            $request->only(['nombre', 'descripcion', 'color']),
            $request->input('dominios', []),
            [
                'logo' => $request->file('logo'),
                'logo_secundario' => $request->file('logo_secundario'),
                'imagen' => $request->file('imagen'),
            ],
            $request->input('quitar', []),
        );
    }
}
