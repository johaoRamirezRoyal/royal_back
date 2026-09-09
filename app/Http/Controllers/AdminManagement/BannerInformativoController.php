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
            'variante' => 'required|string|in:' . implode(',', BannerInformativo::VARIANTES),
            'tamano' => 'required|string|in:' . implode(',', BannerInformativo::TAMANOS),
            'activo' => 'required|boolean',
        ]);

        if ($request->boolean('activo') && !trim((string) $request->input('mensaje'))) {
            return $this->error('El mensaje es obligatorio para activar el banner', 422);
        }

        $banner = $this->service->actualizar(
            $request->input('mensaje'),
            $request->input('variante'),
            $request->input('tamano'),
            $request->boolean('activo'),
            $request->user()->id_user,
        );

        return $this->success('Banner informativo actualizado correctamente', $banner);
    }
}
