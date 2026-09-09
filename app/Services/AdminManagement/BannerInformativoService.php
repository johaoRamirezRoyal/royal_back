<?php

namespace App\Services\AdminManagement;

use App\Models\BannerInformativo;

class BannerInformativoService
{
    public function obtener(): BannerInformativo
    {
        return BannerInformativo::actual();
    }

    public function actualizar(?string $mensaje, ?string $dominio, string $variante, string $tamano, bool $activo, ?string $expiraEn, int $idUser): BannerInformativo
    {
        $banner = BannerInformativo::actual();

        $banner->update([
            'mensaje' => $mensaje,
            'dominio' => $dominio,
            'variante' => $variante,
            'tamano' => $tamano,
            'activo' => $activo,
            'expira_en' => $expiraEn,
            'actualizado_por' => $idUser,
        ]);

        return $banner;
    }
}
