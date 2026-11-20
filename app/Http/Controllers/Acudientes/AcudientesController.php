<?php

namespace App\Http\Controllers\Acudientes;

use App\Exceptions\MailRateLimitException;
use App\Http\Controllers\Concerns\VerificaPermisos;
use App\Http\Controllers\Controller;
use App\Services\Acudientes\AcudientesService;
use App\Services\Usuarios\UsuariosServices;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Módulo Acudientes (opción 175): listar, crear, editar, activar/desactivar y enviar el acceso al
 * portal para padres. Vincular hijos usa /vinculacion-acudientes (acepta la 131 o la 175).
 * Reemplaza «Acudientes» de Admisiones (112, que solo listaba y cambiaba el estado).
 */
class AcudientesController extends Controller
{
    use VerificaPermisos;

    public function __construct(
        private AcudientesService $service,
        private UsuariosServices $usuarios,
    ) {
    }

    /** GET /api/acudientes?s=&estado=&solo_autoregistrados=&page=&per-page=&sort=&dir= */
    public function index(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::ACUDIENTES)) {
            return $rechazo;
        }

        $response = $this->usuarios->mostrarAcudientesPaginados(
            (int) $request->input('per-page', 10),
            $request->input('s'),
            $request->input('estado'),
            $request->input('sort', 'nombre'),
            $request->input('dir', 'asc'),
            $request->boolean('solo_autoregistrados'),
        );

        return $response['error'] ? $this->error($response['message']) : $this->paginatedResponse($response);
    }

    /** POST /api/acudientes { documento, nombre, apellido, correo, telefono? } */
    public function store(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::ACUDIENTES)) {
            return $rechazo;
        }

        $datos = $request->validate(AcudientesService::reglas(), AcudientesService::mensajes());

        return $this->apiResponse($this->service->crear($datos, $request->user()->id_user));
    }

    /** PUT /api/acudientes/{id} { documento, nombre, apellido, correo, telefono? } */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::ACUDIENTES)) {
            return $rechazo;
        }

        $datos = $request->validate(AcudientesService::reglas($id), AcudientesService::mensajes());

        return $this->apiResponse($this->service->actualizar($id, $datos));
    }

    /** PUT /api/acudientes/estado { ids: [], estado: activo|inactivo } */
    public function estado(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::ACUDIENTES)) {
            return $rechazo;
        }

        $datos = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'estado' => ['required', Rule::in(['activo', 'inactivo'])],
        ]);

        $response = $this->usuarios->actualizarEstadoAcudientes($datos['ids'], $datos['estado']);

        return $response['error'] ? $this->error($response['message']) : $this->success($response['message']);
    }

    /** POST /api/acudientes/{id}/acceso-portal: correo con el enlace para crear su contraseña. */
    public function accesoPortal(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::ACUDIENTES)) {
            return $rechazo;
        }

        try {
            return $this->apiResponse($this->service->enviarAccesoPortal($id));
        } catch (MailRateLimitException) {
            // 429: el frontend detiene el envío en lote. El límite es por hora y lo comparten todos
            // los correos del sistema (códigos de inicio de sesión incluidos).
            return $this->error('El servidor de correo alcanzó su límite de envíos por hora. Intenta de nuevo más tarde.', 429);
        }
    }

    /** POST /api/acudientes/importar { archivo }: carga masiva desde Excel (ver AcudientesService::importarExcel). */
    public function importar(Request $request): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::ACUDIENTES)) {
            return $rechazo;
        }

        $request->validate(['archivo' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:5120']], [
            'archivo.required' => 'Elige el archivo de Excel.',
            'archivo.mimes' => 'El archivo debe ser de Excel (.xlsx, .xls) o .csv.',
            'archivo.max' => 'El archivo pesa más de 5 MB.',
        ]);

        return $this->apiResponse($this->service->importarExcel($request->file('archivo')->getRealPath(), $request->user()->id_user));
    }
}
