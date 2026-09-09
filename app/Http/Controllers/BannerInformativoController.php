<?php

namespace App\Http\Controllers;

use App\Services\AdminManagement\BannerInformativoService;
use App\Services\branding\MarcaDominioService;
use Illuminate\Http\Request;

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

        if ($banner->dominio) {
            $dominioVisitante = $this->marcaDominioService->dominioDeCorreo($request->query('correo'));

            if ($dominioVisitante !== $banner->dominio) {
                return $this->success('Banner informativo obtenido correctamente', null);
            }
        }

        return $this->success('Banner informativo obtenido correctamente', [
            'mensaje' => $banner->mensaje,
            'variante' => $banner->variante,
            'tamano' => $banner->tamano,
        ]);
    }
}
