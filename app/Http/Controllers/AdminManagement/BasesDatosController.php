<?php

namespace App\Http\Controllers\AdminManagement;

use App\Http\Controllers\Controller;
use App\Services\AdminManagement\BasesDatosService;
use Illuminate\Http\Request;
use App\Support\Permiso;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Panel para el Super Admin: qué bases de datos usa la app, si responden, y renombrarlas. */
class BasesDatosController extends Controller implements HasMiddleware
{
    /** Solo Super Admin. Mismo permiso que su ruta: el controller no depende de que la ruta lo tenga. */
    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([], [Permiso::PERFIL_SUPER_ADMIN]))];
    }

    public function __construct(
        private BasesDatosService $service,
    ) {
    }

    public function listar(Request $request)
    {
        return $this->apiResponse($this->service->listar());
    }

    public function renombrar(Request $request)
    {
        $request->validate([
            'connection' => ['required', 'string'],
            'nombre' => ['required', 'string', 'max:190'],
        ]);

        return $this->apiResponse($this->service->renombrar($request->input('connection'), $request->input('nombre')));
    }

    public function restablecerNombre(Request $request)
    {
        $request->validate([
            'connection' => ['required', 'string'],
        ]);

        return $this->apiResponse($this->service->restablecerNombre($request->input('connection')));
    }

    public function cambiarCarpeta(Request $request)
    {
        $request->validate([
            'connection' => ['required', 'string'],
            'carpeta' => ['nullable', 'string', 'max:120', 'regex:' . BasesDatosService::PATRON_CARPETA],
        ], ['carpeta.regex' => 'La carpeta solo puede tener letras sin tildes, números, guiones y "/" entre carpetas.']);

        return $this->apiResponse($this->service->cambiarCarpeta($request->input('connection'), $request->input('carpeta')));
    }

    public function conexionActiva(Request $request)
    {
        return $this->apiResponse([
            'error' => false,
            'message' => 'Base de datos activa obtenida.',
            'data' => ['connection' => $this->service->conexionActiva($request->user()->id_user)],
        ]);
    }

    public function establecerConexionActiva(Request $request)
    {
        $request->validate([
            'connection' => ['required', 'string'],
        ]);

        return $this->apiResponse(
            $this->service->establecerConexionActiva($request->user()->id_user, $request->input('connection'))
        );
    }

    public function restablecerConexionActiva(Request $request)
    {
        return $this->apiResponse($this->service->restablecerConexionActiva($request->user()->id_user));
    }
}
