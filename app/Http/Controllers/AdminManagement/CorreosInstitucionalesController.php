<?php

namespace App\Http\Controllers\AdminManagement;

use App\Enums\Mails;
use App\Http\Controllers\Controller;
use App\Services\AdminManagement\CorreosInstitucionalesService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Administración de `correos_institucionales` (destinatarios fijos de cada módulo, ver
 * App\Enums\Mails) — mismo patrón de acceso que BannerInformativoController (perfil Super
 * Admin + `admin.access`, ver rutas en routes/api/adminManagement.php). Edita la tabla de
 * la connection activa (la BD elegida en "Bases de datos").
 */
class CorreosInstitucionalesController extends Controller
{
    private const PERFILES_PERMITIDOS = [1];

    public function __construct(
        private CorreosInstitucionalesService $service,
        Request $request,
    ) {
        if (!in_array($request->user()->perfil, self::PERFILES_PERMITIDOS, true)) {
            abort($this->error('No tienes permiso para gestionar los correos institucionales', 403));
        }
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
