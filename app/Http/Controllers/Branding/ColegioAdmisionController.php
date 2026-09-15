<?php

namespace App\Http\Controllers\Branding;

use App\Http\Controllers\Controller;
use App\Services\branding\ColegioAdmisionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Administración de los colegios de admisión (slug de URL -> connection de BD + marca) —
 * gatea directo por perfil (Super Admin=1 únicamente), mismo criterio que
 * MarcaDominioController: no es una opción granular de `cron_opciones`, es un pedido
 * explícito de "solo este rol" (agregar/quitar un colegio cambia a qué base de datos
 * apunta tráfico público, no es un ajuste cosmético más).
 *
 * `branding()` es la única acción pública (sin gating) — es cómo el frontend descubre si
 * un slug existe y qué logo/color mostrar antes de tener nada más (ver
 * ResolveColegioAdmision, que si necesita el slug para el resto de rutas de admisiones).
 */
class ColegioAdmisionController extends Controller
{
    private const PERFILES_PERMITIDOS = [1];

    public function __construct(private ColegioAdmisionService $service)
    {
    }

    private function ensureAdmin(Request $request): void
    {
        if (!in_array($request->user()->perfil, self::PERFILES_PERMITIDOS, true)) {
            abort($this->error('No tienes permiso para administrar los colegios de admisión', 403));
        }
    }

    public function listar(Request $request)
    {
        $this->ensureAdmin($request);

        return $this->apiResponse($this->service->listar());
    }

    public function crear(Request $request)
    {
        $this->ensureAdmin($request);

        $request->validate([
            'slug' => ['required', 'string', 'max:190'],
            'nombre' => ['required', 'string', 'max:190'],
            'descripcion' => ['nullable', 'string', 'max:190'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'connection' => ['required', 'string', 'max:100'],
            'tipo_calendario' => ['nullable', 'in:A,B'],
            'logo' => ['nullable', 'file', 'image', 'max:5120'],
            'logo_secundario' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        return $this->apiResponse($this->service->crear(
            $request->only(['slug', 'nombre', 'descripcion', 'color', 'connection', 'tipo_calendario']),
            $request->file('logo'),
            $request->file('logo_secundario'),
        ));
    }

    public function actualizar(Request $request)
    {
        $this->ensureAdmin($request);

        $request->validate([
            'id' => ['required', 'integer'],
            'slug' => ['sometimes', 'string', 'max:190'],
            'nombre' => ['sometimes', 'string', 'max:190'],
            'descripcion' => ['nullable', 'string', 'max:190'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'connection' => ['sometimes', 'string', 'max:100'],
            'tipo_calendario' => ['sometimes', 'in:A,B'],
            'logo' => ['nullable', 'file', 'image', 'max:5120'],
            'logo_secundario' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        return $this->apiResponse($this->service->actualizar(
            $request->integer('id'),
            $request->only(['slug', 'nombre', 'descripcion', 'color', 'connection', 'tipo_calendario']),
            $request->file('logo'),
            $request->file('logo_secundario'),
        ));
    }

    public function cambiarEstado(Request $request)
    {
        $this->ensureAdmin($request);

        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'activo' => ['required', 'boolean'],
        ]);

        return $this->apiResponse($this->service->cambiarEstado($request->input('ids'), $request->boolean('activo')));
    }

    public function eliminar(Request $request)
    {
        $this->ensureAdmin($request);

        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        return $this->apiResponse($this->service->eliminar($request->input('ids')));
    }

    /** Pública, sin JWT — misma lógica de rate limit que AuthController::brandingPreview. */
    public function branding(Request $request, string $slug)
    {
        $ip = $request->ip();
        $rateLimitKey = "colegio_admision_branding_{$ip}";
        $attempts = Cache::increment($rateLimitKey);

        if ($attempts === 1) {
            Cache::put($rateLimitKey, 1, now()->addMinute());
        }

        if ($attempts > 60) {
            return response()->json(['error' => true, 'message' => 'Demasiadas solicitudes.'], 429);
        }

        $marca = $this->service->resolverBrandingPorSlug($slug);

        if (!$marca) {
            return response()->json(['error' => true, 'message' => 'Colegio no encontrado.'], 404);
        }

        return response()->json([
            'error' => false,
            'data' => [
                'nombre_marca' => $marca['nombre'],
                'descripcion_marca' => $marca['descripcion'],
                'color_marca' => $marca['color'],
                'logo_marca' => $marca['logo'],
                'logo_secundario_marca' => $marca['logo_secundario'],
                'tipo_calendario' => $marca['tipo_calendario'],
            ],
        ]);
    }
}
