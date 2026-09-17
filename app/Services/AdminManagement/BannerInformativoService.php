<?php

namespace App\Services\AdminManagement;

use App\Models\BannerInformativo;
use App\Services\MailService;
use Illuminate\Support\Facades\Cache;

class BannerInformativoService
{
    public function __construct(private MailService $mailService) {}

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

    public function actualizar(?string $mensaje, ?string $dominio, string $variante, string $tamano, bool $activo, ?string $expiraEn, bool $enviarCorreo, ?string $destinatarioCorreo, bool $mostrarModal, int $idUser): BannerInformativo
    {
        $banner = BannerInformativo::actual();

        $banner->update([
            'mensaje' => $mensaje,
            'dominio' => $dominio,
            'variante' => $variante,
            'tamano' => $tamano,
            'activo' => $activo,
            'expira_en' => $expiraEn,
            'enviar_correo' => $enviarCorreo,
            'destinatario_correo' => $destinatarioCorreo,
            'mostrar_modal' => $mostrarModal,
            'actualizado_por' => $idUser,
        ]);

        // El envío en sí (si enviar_correo viene en true) lo dispara el controller DESPUÉS
        // de llamar este método — ver BannerInformativoController::actualizar — no acá, para
        // poder combinar en una sola respuesta si el guardado funcionó pero el correo falló
        // (ej. rate-limit del proveedor) en vez de que el frontend asuma éxito por asumir.

        // put() en vez de forget(): así el próximo GET (público o del propio panel admin,
        // justo después de guardar) ya sirve el valor nuevo desde cache en vez de gastar
        // una lectura "en vano" a MySQL que igual iba a devolver esto mismo.
        Cache::put(self::CACHE_KEY, $banner, self::CACHE_TTL);

        return $banner;
    }

    /**
     * Envía el mensaje YA GUARDADO del banner por correo — llamada por el controller tanto
     * después de un `actualizar()` con `enviar_correo=true` (se reenvía en CADA guardado
     * mientras esa casilla siga marcada, aunque el cambio sea solo el color; quien no quiera
     * eso debe desmarcarla antes de guardar un ajuste menor) como desde el botón aparte
     * "Reenviar correo ahora" del panel, para reenviar sin tocar ningún otro campo. Un solo
     * destinatario (alias de distribución del lado del proveedor, ver
     * BannerInformativo::DESTINATARIO_CORREO_DEFAULT) — no hay loop por-usuario que pueda
     * repetir el incidente de rate-limit de Noticias.
     */
    public function enviarCorreo(): array
    {
        $banner = BannerInformativo::actual();

        if (!trim((string) $banner->mensaje)) {
            return ['error' => true, 'message' => 'El banner no tiene mensaje para enviar.', 'data' => []];
        }

        $destinatario = $banner->destinatario_correo ?: BannerInformativo::DESTINATARIO_CORREO_DEFAULT;

        return $this->mailService->sendGeneric($destinatario, 'Aviso institucional', $banner->mensaje);
    }
}
