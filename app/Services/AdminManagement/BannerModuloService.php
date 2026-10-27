<?php

namespace App\Services\AdminManagement;

use App\Models\BannerInformativo;
use App\Models\BannerModulo;
use App\Services\Cloudinary\CloudinaryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/** Banners por módulo (ver la migración create_banner_modulo_table). */
class BannerModuloService
{
    public function __construct(private CloudinaryService $cloudinary) {}

    // Se lee en cada carga del sistema general: mismo cache corto que el banner global
    // (ver BannerInformativoService), así casi ninguna lectura toca admin_management.
    private const CACHE_KEY = 'banners_modulo';
    private const CACHE_TTL = 60;

    public function listar(): Collection
    {
        return BannerModulo::orderBy('modulo')->get();
    }

    /** Activos, sin vencer y que aplican al dominio del visitante (null = solo los globales). */
    public function activos(?string $dominioVisitante): Collection
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => BannerModulo::where('activo', true)->get())
            ->filter(fn (BannerModulo $b) => (!$b->expira_en || $b->expira_en->isFuture())
                && (!$b->dominio || $b->dominio === $dominioVisitante))
            ->values();
    }

    /** Crea o actualiza el banner del módulo (uno por módulo). */
    public function guardar(string $modulo, array $datos, int $idUser): BannerModulo
    {
        $banner = BannerModulo::firstOrNew(['modulo' => $modulo]);

        // Imagen reemplazada o quitada: se borra la anterior en Cloudinary (igual que el global).
        if ($banner->imagen_public_id && $banner->imagen_public_id !== ($datos['imagen_public_id'] ?? null)) {
            $this->cloudinary->deleteFile($banner->imagen_public_id, BannerInformativo::tipoRecurso($banner->imagen));
        }

        $banner->fill($datos + ['actualizado_por' => $idUser])->save();
        Cache::forget(self::CACHE_KEY);

        return $banner;
    }

    public function eliminar(int $id): bool
    {
        $banner = BannerModulo::find($id);
        if (!$banner) {
            return false;
        }
        if ($banner->imagen_public_id) {
            $this->cloudinary->deleteFile($banner->imagen_public_id, BannerInformativo::tipoRecurso($banner->imagen));
        }
        $banner->delete();
        Cache::forget(self::CACHE_KEY);

        return true;
    }
}
