<?php

namespace App\Http\Controllers\AdminManagement;

use App\Http\Controllers\Controller;
use App\Services\AdminManagement\BasesDatosService;
use Illuminate\Http\Request;

/** Panel para el Super Admin: qué bases de datos usa la app, si responden, y renombrarlas. */
class BasesDatosController extends Controller
{
    private const PERFILES_PERMITIDOS = [1];

    public function __construct(
        private BasesDatosService $service,
        Request $request,
    ) {
        if (!in_array($request->user()->perfil, self::PERFILES_PERMITIDOS, true)) {
            abort($this->error('No tienes permiso para ver las bases de datos', 403));
        }
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
}
