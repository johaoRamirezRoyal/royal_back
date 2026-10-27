<?php

namespace App\Http\Controllers\AdminManagement;

use App\Http\Controllers\Controller;
use App\Models\BannerInformativo;
use App\Services\AdminManagement\BannerModuloService;
use App\Support\Permiso;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Banners por módulo — mismo acceso que BannerInformativoController (Super Admin). La
 * imagen se sube con el mismo endpoint del banner global (POST banner-informativo/imagen).
 */
class BannerModuloController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]))];
    }

    public function __construct(private BannerModuloService $service) {}

    public function listar()
    {
        return $this->success('Banners por módulo obtenidos correctamente', $this->service->listar());
    }

    public function guardar(Request $request)
    {
        $cloudName = preg_quote((string) config('cloudinary.cloud_name'), '/');

        // Mismas reglas que el banner global (ver BannerInformativoController::actualizar).
        $request->validate([
            'modulo' => 'required|string|max:100',
            'mensaje' => 'nullable|string|max:500',
            'imagen' => ['nullable', 'string', 'max:500', 'regex:/^https:\/\/res\.cloudinary\.com\/' . $cloudName . '\/image\/upload\/.*\/banner\/[A-Za-z0-9._-]+$/'],
            'imagen_public_id' => ['nullable', 'string', 'max:255', 'regex:/^banner\/[A-Za-z0-9._-]+$/'],
            'dominio' => 'nullable|string|max:190',
            'variante' => 'required|string|in:' . implode(',', BannerInformativo::VARIANTES),
            'tamano' => 'required|string|in:' . implode(',', BannerInformativo::TAMANOS),
            'activo' => 'required|boolean',
            'expira_en' => 'nullable|date',
            'mostrar_modal' => 'nullable|boolean',
        ]);

        if ($request->boolean('activo') && !trim((string) $request->input('mensaje'))) {
            return $this->error('El mensaje es obligatorio para activar el banner', 422);
        }

        $banner = $this->service->guardar(trim((string) $request->input('modulo')), [
            'mensaje' => $request->input('mensaje'),
            'imagen' => $request->filled('imagen') ? (string) $request->input('imagen') : null,
            'imagen_public_id' => $request->filled('imagen_public_id') ? (string) $request->input('imagen_public_id') : null,
            // Mismo formato que compara el endpoint público (trim + minúsculas).
            'dominio' => $request->filled('dominio') ? mb_strtolower(trim((string) $request->input('dominio'))) : null,
            'variante' => $request->input('variante'),
            'tamano' => $request->input('tamano'),
            'activo' => $request->boolean('activo'),
            'expira_en' => $request->input('expira_en'),
            'mostrar_modal' => $request->boolean('mostrar_modal'),
        ], $request->user()->id_user);

        return $this->success('Banner del módulo guardado correctamente', $banner);
    }

    public function eliminar(int $id)
    {
        return $this->service->eliminar($id)
            ? $this->success('Banner del módulo eliminado', null)
            : $this->error('El banner no existe', 404);
    }
}
