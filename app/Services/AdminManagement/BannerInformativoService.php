<?php

namespace App\Services\AdminManagement;

use App\Models\BannerInformativo;
use Illuminate\Support\Facades\Cache;

class BannerInformativoService
{
    // banner_informativo vive en admin_management (ver el modelo) — sin cache, CADA
    // GET /api/banner-informativo (login + montaje de AppLayout, en cada carga de
    // página) abre su propia connection TCP+auth aparte de la principal, solo para leer
    // una fila que rara vez cambia. Con esto, la inmensa mayoría de esas lecturas ni
    // siquiera tocan MySQL. 60s: suficientemente corto para que un banner nuevo se
    // sienta "inmediato", suficientemente largo para amortiguar carga concurrente real.
    private const CACHE_KEY = 'banner_informativo_activo';
    private const CACHE_TTL = 60;

    public function obtener(): BannerInformativo
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => BannerInformativo::actual());
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

        // put() en vez de forget(): así el próximo GET (público o del propio panel admin,
        // justo después de guardar) ya sirve el valor nuevo desde cache en vez de gastar
        // una lectura "en vano" a MySQL que igual iba a devolver esto mismo.
        Cache::put(self::CACHE_KEY, $banner, self::CACHE_TTL);

        return $banner;
    }
}
