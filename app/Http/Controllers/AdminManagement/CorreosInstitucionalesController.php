<?php

namespace App\Http\Controllers\AdminManagement;

use App\Enums\Mails;
use App\Http\Controllers\Controller;
use App\Services\AdminManagement\CorreosInstitucionalesService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Support\Permiso;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Administración de `correos_institucionales` (destinatarios fijos de cada módulo, ver
 * App\Enums\Mails) — mismo patrón de acceso que BannerInformativoController (perfil Super
 * Admin + `admin.access`, ver rutas en routes/api/adminManagement.php). Edita la tabla de
 * la connection activa (la BD elegida en "Bases de datos").
 */
class CorreosInstitucionalesController extends Controller implements HasMiddleware
{
    /** Solo Super Admin. Mismo permiso que su ruta: el controller no depende de que la ruta lo tenga. */
    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]))];
    }

    public function __construct(
        private CorreosInstitucionalesService $service,
    ) {
    }

    public function listar()
    {
        return $this->apiResponse($this->service->listar());
    }

    public function crear(Request $request)
    {
        $datos = $request->validate([
            'grupo' => ['required', 'string', Rule::in(array_map(fn (Mails $m) => $m->name, Mails::cases()))],
            ...$this->reglasCorreo(),
        ], $this->mensajes());

        return $this->apiResponse($this->service->crear($datos));
    }

    public function actualizar(Request $request, int $id)
    {
        $datos = $request->validate($this->reglasCorreo(), $this->mensajes());

        return $this->apiResponse($this->service->actualizar($id, $datos));
    }

    public function eliminar(int $id)
    {
        return $this->apiResponse($this->service->eliminar($id));
    }

    private function reglasCorreo(): array
    {
        return [
            'correo' => 'required|email|max:190',
            'nombre' => 'nullable|string|max:190',
            'activo' => 'nullable|boolean',
        ];
    }

    private function mensajes(): array
    {
        return [
            'grupo.required' => 'El grupo es obligatorio.',
            'grupo.in' => 'El grupo no es válido.',
            'correo.required' => 'El correo es obligatorio.',
            'correo.email' => 'El correo no tiene un formato válido.',
        ];
    }
}
