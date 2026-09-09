<?php

namespace App\Http\Controllers;

use App\Services\AdminManagement\BannerInformativoService;

/**
 * Lectura pública del banner informativo (aviso/mantenimiento) — sin auth, porque se
 * muestra tanto en el login (sin sesión) como en el sistema general ya autenticado. La
 * edición vive aparte en AdminManagement\BannerInformativoController, detrás de
 * auth + admin.access + perfil Super Admin (ver ese controller).
 */
class BannerInformativoController extends Controller
{
    public function __construct(private BannerInformativoService $service)
    {
    }

    public function obtener()
    {
        $banner = $this->service->obtener();

        return $this->success('Banner informativo obtenido correctamente', $banner->activo ? [
            'mensaje' => $banner->mensaje,
            'variante' => $banner->variante,
        ] : null);
    }
}
