<?php

namespace App\Http\Controllers\AdminManagement;

use App\Http\Controllers\Controller;
use App\Models\BannerInformativo;
use App\Services\AdminManagement\BannerInformativoService;
use App\Services\Cloudinary\CloudinaryService;
use Illuminate\Http\Request;

/**
 * Edición del banner informativo — mismo patrón de acceso que LlaveMaestraController
 * (perfil Super Admin + `admin.access`, ver rutas en routes/api/adminManagement.php).
 */
class BannerInformativoController extends Controller
{
    private const PERFILES_PERMITIDOS = [1];

    public function __construct(
        private BannerInformativoService $service,
        private CloudinaryService $cloudinary,
        Request $request,
    ) {
        if (!in_array($request->user()->perfil, self::PERFILES_PERMITIDOS, true)) {
            abort($this->error('No tienes permiso para gestionar el banner informativo', 403));
        }
    }

    public function obtener()
    {
        return $this->success('Banner informativo obtenido correctamente', $this->service->obtener());
    }

    public function actualizar(Request $request)
    {
        $cloudName = preg_quote((string) config('cloudinary.cloud_name'), '/');

        $request->validate([
            'mensaje' => 'nullable|string|max:500',
            // Solo URLs de Cloudinary dentro de la carpeta del banner (la que devuelve
            // subirImagen) — evita apuntar a (y luego borrar, ver
            // BannerInformativoService::actualizar) archivos ajenos.
            'imagen' => ['nullable', 'string', 'max:500', 'regex:/^https:\/\/res\.cloudinary\.com\/' . $cloudName . '\/image\/upload\/.*\/banner\/[A-Za-z0-9._-]+$/'],
            'imagen_public_id' => ['nullable', 'string', 'max:255', 'regex:/^banner\/[A-Za-z0-9._-]+$/'],
            'dominio' => 'nullable|string|max:190',
            'variante' => 'required|string|in:' . implode(',', BannerInformativo::VARIANTES),
            'tamano' => 'required|string|in:' . implode(',', BannerInformativo::TAMANOS),
            'activo' => 'required|boolean',
            'expira_en' => 'nullable|date',
            'enviar_correo' => 'nullable|boolean',
            'destinatario_correo' => 'nullable|email|max:190',
            'mostrar_modal' => 'nullable|boolean',
        ]);

        if ($request->boolean('activo') && !trim((string) $request->input('mensaje'))) {
            return $this->error('El mensaje es obligatorio para activar el banner', 422);
        }

        // A propósito NO se usa MarcaDominioService::dominioDeCorreo acá — exige un "@" en
        // el valor (piensa que recibe un correo), pero acá ya recibimos el dominio suelto
        // elegido en el selector del panel (ver MarcasDominio). Mismo trim+lowercase que
        // esa clase aplica internamente, para que ambos lados de la comparación en
        // BannerInformativoController::obtener (público) queden en el mismo formato.
        $dominio = $request->filled('dominio') ? mb_strtolower(trim((string) $request->input('dominio'))) : null;

        $enviarCorreo = $request->boolean('enviar_correo');

        $banner = $this->service->actualizar(
            $request->input('mensaje'),
            $dominio,
            $request->input('variante'),
            $request->input('tamano'),
            $request->boolean('activo'),
            $request->input('expira_en'),
            $enviarCorreo,
            $request->filled('destinatario_correo') ? trim((string) $request->input('destinatario_correo')) : null,
            $request->boolean('mostrar_modal'),
            $request->filled('imagen') ? (string) $request->input('imagen') : null,
            $request->filled('imagen_public_id') ? (string) $request->input('imagen_public_id') : null,
            $request->user()->id_user,
        );

        // Después de guardar (no antes, ver BannerInformativoService::actualizar): así el
        // correo sale con el mensaje/destinatario que se acaban de guardar, no los viejos.
        // Si el guardado en sí funcionó pero el correo falla, igual se devuelve 200 con el
        // banner ya actualizado — el mensaje solo avisa que el envío no salió, no revierte
        // el guardado (MailService::sendGeneric ya deja el detalle en los logs).
        $mensaje = 'Banner informativo actualizado correctamente';

        if ($enviarCorreo) {
            $resultadoCorreo = $this->service->enviarCorreo();
            $mensaje = $resultadoCorreo['error']
                ? 'Banner guardado, pero el correo no se pudo enviar: ' . $resultadoCorreo['message']
                : 'Banner guardado y correo enviado correctamente';
        }

        return $this->success($mensaje, $banner);
    }

    /**
     * Sube la imagen/GIF del banner a Cloudinary — devuelve `url` (para guardar en
     * `imagen`) y `public_id` (para guardar en `imagen_public_id`), ambos exigidos por
     * `actualizar()`. Antes se guardaba en disco local del VPS, pero sus límites de
     * upload_max_filesize/post_max_size eran más bajos que el max:8192 (8MB) de acá y
     * GIFs grandes fallaban con "The imagen failed to upload." antes de llegar a esta
     * validación.
     */
    public function subirImagen(Request $request)
    {
        $request->validate([
            'imagen' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:8192',
        ]);

        $resultado = $this->cloudinary->uploadFile($request->file('imagen'), 'banner');

        if ($resultado['error']) {
            return $this->error($resultado['message'], 422);
        }

        return $this->success('Imagen subida correctamente', [
            'url' => $resultado['data']['url'],
            'public_id' => $resultado['data']['public_id'],
        ]);
    }

    /** Envía el mensaje ya guardado del banner por correo — ver BannerInformativoService::enviarCorreo. */
    public function enviarCorreo()
    {
        $resultado = $this->service->enviarCorreo();

        if ($resultado['error']) {
            return $this->error($resultado['message'], 422);
        }

        return $this->success($resultado['message'], $resultado['data']);
    }
}
