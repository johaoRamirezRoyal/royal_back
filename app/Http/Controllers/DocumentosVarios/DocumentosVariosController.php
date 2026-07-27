<?php

namespace App\Http\Controllers\DocumentosVarios;

use App\Http\Controllers\Controller;
use App\Services\DocumentosVarios\DocumentosVariosService;
use Illuminate\Http\Request;

class DocumentosVariosController extends Controller
{
    public function __construct(
        private DocumentosVariosService $service
    ) {}

    public function obtenerDocumentosPorUsuario(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->obtenerDocumentosPorUsuario($id_usuario));
    }

    public function obtenerDocumentosPorTipo(Request $request)
    {
        $tipo_doc = $request->input('tipo_doc');
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->obtenerDocumentosPorTipo($tipo_doc, $id_usuario));
    }

    public function crearDocumento(Request $request)
    {
        $request->validate([
            'tipo_doc' => 'required|string|max:200',
            'id_user' => 'nullable|integer',
            'archivo' => 'required|file|max:20480',
        ]);

        $data = $request->except('archivo');
        $archivo = $request->file('archivo');

        return $this->apiResponse($this->service->crearDocumento($data, $archivo));
    }

    public function actualizarDocumento(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'tipo_doc' => 'sometimes|string|max:200',
            'id_user' => 'nullable|integer',
            'archivo' => 'nullable|file|max:20480',
        ]);

        $body = $request->except('archivo');
        $id = $body['id'] ?? null;
        $archivo = $request->file('archivo');

        return $this->apiResponse($this->service->actualizarDocumento($id, $body, $archivo));
    }

    public function eliminarDocumento(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarDocumento($id));
    }

    public function eliminarDocumentosPorUsuario(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->eliminarDocumentosPorUsuario($id_usuario));
    }

    public function eliminarDocumentosPorTipo(Request $request)
    {
        $tipo_doc = $request->input('tipo_doc');
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->eliminarDocumentosPorTipo($tipo_doc, $id_usuario));
    }

    public function contarDocumentosPorTipo(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->contarDocumentosPorTipo($id_usuario));
    }
}
