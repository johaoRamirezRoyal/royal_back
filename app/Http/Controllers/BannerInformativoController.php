<?php

namespace App\Http\Controllers;

use App\Services\AdminManagement\BannerInformativoService;
use App\Services\branding\MarcaDominioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Lectura pública del banner informativo (aviso/mantenimiento) — sin auth, porque se
 * muestra tanto en el login (sin sesión) como en el sistema general ya autenticado. La
 * edición vive aparte en AdminManagement\BannerInformativoController, detrás de
 * auth + admin.access + perfil Super Admin (ver ese controller).
 */
class BannerInformativoController extends Controller
{
    public function __construct(
        private BannerInformativoService $service,
        private MarcaDominioService $marcaDominioService,
    ) {
    }

    /**
     * `correo` (opcional, mismo parámetro que AuthController::brandingPreview) — el correo
     * o dominio del visitante, para decidir si un banner con alcance de colegio (`dominio`
     * no nulo) le aplica. Sin `correo` (ej. login antes de escribir el correo) solo se
     * muestran banners globales, nunca uno de colegio — fail-closed, igual que el resto de
     * multi-tenant por dominio en este backend.
     */
    public function obtener(Request $request)
    {
        $banner = $this->service->obtener();

        if (!$banner->activo) {
            return $this->success('Banner informativo obtenido correctamente', null);
        }

        if ($banner->expira_en && $banner->expira_en->isPast()) {
            return $this->success('Banner informativo obtenido correctamente', null);
        }

        if ($banner->dominio) {
            $dominioVisitante = $this->marcaDominioService->dominioDeCorreo($request->query('correo'));

            if ($dominioVisitante !== $banner->dominio) {
                return $this->success('Banner informativo obtenido correctamente', null);
            }
        }

        return $this->success('Banner informativo obtenido correctamente', [
            'mensaje' => $banner->mensaje,
            'imagen' => $banner->imagen,
            'variante' => $banner->variante,
            'tamano' => $banner->tamano,
            'expira_en' => $banner->expira_en,
            'mostrar_modal' => $banner->mostrar_modal,
        ]);
    }

    /**
     * GET /api/banner-informativo/imagen/{filename} — sirve la imagen/GIF del banner sin
     * token (se pide desde un <img src>, también en el login). Solo lee dentro de la
     * carpeta `banner/` del disco de uploads; `{filename}` no admite "/" (ver la ruta).
     */
    public function verImagen(string $filename)
    {
        if (str_contains($filename, '..')) {
            abort(404);
        }

        $disk = Storage::disk(config('filesystems.uploads_disk', 'public'));
        $ruta = 'banner/' . $filename;

        if (!$disk->exists($ruta)) {
            abort(404);
        }

        return $disk->response($ruta);
    }
}
