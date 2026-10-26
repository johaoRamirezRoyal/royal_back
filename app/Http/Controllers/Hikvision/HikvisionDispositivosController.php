<?php

namespace App\Http\Controllers\Hikvision;

use App\Http\Controllers\Controller;
use App\Services\Hikvisionattendance\DispositivosService;
use App\Support\Permiso;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;

/**
 * Administración de las terminales Hikvision del colegio ("Terminales de acceso"). Guarda
 * credenciales de los equipos, así que es solo del Super Admin (la ruta repite la regla).
 */
class HikvisionDispositivosController extends Controller implements HasMiddleware
{
    public function __construct(private DispositivosService $service) {}

    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]))];
    }

    public function crear(Request $request)
    {
        return $this->guardar($request, null);
    }

    public function actualizar(Request $request, int $id)
    {
        return $this->guardar($request, $id);
    }

    public function eliminar(int $id)
    {
        return $this->apiResponse($this->service->eliminar($id));
    }

    private function guardar(Request $request, ?int $id)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:100'],
            'protocolo' => ['required', 'in:http,https'],
            'host' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9.-]+$/'],
            'puerto' => ['required', 'integer', 'between:1,65535'],
            'usuario' => ['required', 'string', 'max:100'],
            'password' => [$id === null ? 'required' : 'nullable', 'string', 'max:100'],
            'principal' => ['boolean'],
            'activo' => ['boolean'],
        ], [
            'host.regex' => 'La dirección solo puede tener letras, números, puntos y guiones (sin http:// ni puerto).',
            'puerto.between' => 'El puerto debe estar entre 1 y 65535.',
            'password.required' => 'Escribe la contraseña de la terminal.',
        ], [
            'host' => 'dirección',
            'usuario' => 'usuario',
            'password' => 'contraseña',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => true, 'message' => $validator->errors()->first()], 422);
        }

        return $this->apiResponse($this->service->guardar(
            $request->only(['nombre', 'protocolo', 'host', 'puerto', 'usuario', 'password', 'principal', 'activo']),
            $id,
        ));
    }
}
