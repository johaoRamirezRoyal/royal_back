<?php

namespace App\Http\Controllers\Acudientes;

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

        $datos = $request->validate($this->reglas(), $this->mensajes());

        return $this->apiResponse($this->service->crear($datos, $request->user()->id_user));
    }

    /** PUT /api/acudientes/{id} { documento, nombre, apellido, correo, telefono? } */
    public function update(Request $request, int $id): JsonResponse
    {
        if ($rechazo = $this->sinAcceso($request, Permiso::ACUDIENTES)) {
            return $rechazo;
        }

        $datos = $request->validate($this->reglas($id), $this->mensajes());

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

        return $this->apiResponse($this->service->enviarAccesoPortal($id));
    }

    /** El documento y el correo no se repiten en la base (el login busca por correo). */
    private function reglas(?int $id = null): array
    {
        return [
            'documento' => ['required', 'string', 'max:50', Rule::unique('usuarios', 'documento')->ignore($id, 'id_user')],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:140', Rule::unique('usuarios', 'correo')->ignore($id, 'id_user')],
            'telefono' => ['nullable', 'string', 'max:20'],
        ];
    }

    private function mensajes(): array
    {
        return [
            'documento.required' => 'Escribe el número de documento.',
            'documento.unique' => 'Ya existe una cuenta con este documento.',
            'nombre.required' => 'Escribe los nombres.',
            'apellido.required' => 'Escribe los apellidos.',
            'correo.required' => 'Escribe el correo: con él entra al portal para padres.',
            'correo.email' => 'El correo no tiene un formato válido.',
            'correo.unique' => 'Ya existe una cuenta con este correo.',
            'telefono.max' => 'El teléfono admite hasta 20 caracteres.',
        ];
    }
}
