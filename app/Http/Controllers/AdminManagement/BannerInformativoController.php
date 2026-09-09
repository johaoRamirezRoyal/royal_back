<?php

namespace App\Http\Controllers\AdminManagement;

use App\Http\Controllers\Controller;
use App\Models\BannerInformativo;
use App\Services\AdminManagement\BannerInformativoService;
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
        $request->validate([
            'mensaje' => 'nullable|string|max:500',
            'dominio' => 'nullable|string|max:190',
            'variante' => 'required|string|in:' . implode(',', BannerInformativo::VARIANTES),
            'tamano' => 'required|string|in:' . implode(',', BannerInformativo::TAMANOS),
            'activo' => 'required|boolean',
            'expira_en' => 'nullable|date',
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

        $banner = $this->service->actualizar(
            $request->input('mensaje'),
            $dominio,
            $request->input('variante'),
            $request->input('tamano'),
            $request->boolean('activo'),
            $request->input('expira_en'),
            $request->user()->id_user,
        );

        return $this->success('Banner informativo actualizado correctamente', $banner);
    }
}
