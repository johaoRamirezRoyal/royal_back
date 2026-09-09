<?php

namespace App\Services\AdminManagement;

use App\Models\BannerInformativo;

class BannerInformativoService
{
    public function obtener(): BannerInformativo
    {
        return BannerInformativo::actual();
    }

    public function actualizar(?string $mensaje, string $variante, string $tamano, bool $activo, int $idUser): BannerInformativo
    {
        $banner = BannerInformativo::actual();

        $banner->update([
            'mensaje' => $mensaje,
            'variante' => $variante,
            'tamano' => $tamano,
            'activo' => $activo,
            'actualizado_por' => $idUser,
        ]);

        return $banner;
    }
}
