<?php

namespace App\Http\Controllers\PerfilUsuario;

use App\Http\Controllers\Controller;
use App\Http\Requests\PerfilUsuario\InfoAdicionalUsuarioRequest;
use App\Http\Requests\PerfilUsuario\ProduccionIntelectualRequest;
use App\Services\PerfilUsuario\PerfilUsuarioService;
use Illuminate\Http\Request;

class PerfilUsuarioController extends Controller
{
    public function __construct(
        private PerfilUsuarioService $service
    ) {}

    // ── Hoja de Vida ───────────────────────────────────────────

    public function hojaDeVida(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->obtenerHojaDeVida($id_usuario));
    }

    // ── Info Adicional ──────────────────────────────────────────

    public function mostrarInformacionPerfilUsuario(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->mostrarInformacionPefilUsuario($id_usuario));
    }

    public function crearActualizarInfoAdicional(InfoAdicionalUsuarioRequest $request)
    {
        return $this->apiResponse($this->service->crearActualizarInfoAdicional($request->validated()));
    }

    public function actualizarInfoAdicional(InfoAdicionalUsuarioRequest $request)
    {
        $body = $request->validated();
        $id = $body['id'] ?? null;

        return $this->apiResponse($this->service->actualizarInfoAdicional($id, $body));
    }

    public function eliminarInfoAdicional(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarInfoAdicional($id));
    }

    public function verificarCompletitudPerfil(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->verificarCompletitudPerfil($id_usuario));
    }

    // ── Formación ───────────────────────────────────────────────

    public function obtenerFormacionesPorUsuario(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->obtenerFormacionesPorUsuario($id_usuario));
    }

    public function crearFormacion(Request $request)
    {
        $request->validate([
            'id_user' => 'required|integer',
            'tipo_formacion' => 'required|string|max:50',
            'programa' => 'required|string|max:150',
            'institucion' => 'required|string|max:300',
            'fecha_grado' => 'required|date',
            'fecha_expedicion_certi' => 'required|date',
            'duracion' => 'required|integer',
            'certificado' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $data = $request->except('certificado');
        $archivo = $request->file('certificado');

        return $this->apiResponse($this->service->crearFormacion($data, $archivo));
    }

    public function actualizarFormacion(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'id_user' => 'required|integer',
            'tipo_formacion' => 'required|string|max:50',
            'programa' => 'required|string|max:150',
            'institucion' => 'required|string|max:300',
            'fecha_grado' => 'required|date',
            'fecha_expedicion_certi' => 'required|date',
            'duracion' => 'required|integer',
            'certificado' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $body = $request->except('certificado');
        $id = $body['id'] ?? null;
        $archivo = $request->file('certificado');

        return $this->apiResponse($this->service->actualizarFormacion($id, $body, $archivo));
    }

    public function eliminarFormacion(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarFormacion($id));
    }

    public function obtenerFormacionesPorTipo(Request $request)
    {
        $id_usuario = $request->input('id_usuario');
        $tipo = $request->input('tipo_formacion');

        return $this->apiResponse($this->service->obtenerFormacionesPorTipo($id_usuario, $tipo));
    }

    public function eliminarFormacionesPorUsuario(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->eliminarFormacionesPorUsuario($id_usuario));
    }

    // ── Experiencia Laboral ─────────────────────────────────────

    public function obtenerExperienciasPorUsuario(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->obtenerExperienciasPorUsuario($id_usuario));
    }

    public function crearExperienciaLaboral(Request $request)
    {
        $request->validate([
            'id_user' => 'required|integer',
            'nombre_empresa' => 'nullable|string|max:350',
            'cargo' => 'nullable|string|max:200',
            'fecha_ingreso' => 'nullable|date',
            'fecha_retiro' => 'nullable|date',
            'certificado_trabajo' => 'nullable|file|mimes:pdf|max:10240',
            'fecha_certificado' => 'nullable|date',
        ]);

        $data = $request->except('certificado_trabajo');
        $archivo = $request->file('certificado_trabajo');

        return $this->apiResponse($this->service->crearExperienciaLaboral($data, $archivo));
    }

    public function actualizarExperienciaLaboral(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'id_user' => 'required|integer',
            'nombre_empresa' => 'nullable|string|max:350',
            'cargo' => 'nullable|string|max:200',
            'fecha_ingreso' => 'nullable|date',
            'fecha_retiro' => 'nullable|date',
            'certificado_trabajo' => 'nullable|file|mimes:pdf|max:10240',
            'fecha_certificado' => 'nullable|date',
        ]);

        $body = $request->except('certificado_trabajo');
        $id = $body['id'] ?? null;
        $archivo = $request->file('certificado_trabajo');

        return $this->apiResponse($this->service->actualizarExperienciaLaboral($id, $body, $archivo));
    }

    public function eliminarExperienciaLaboral(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarExperienciaLaboral($id));
    }

    public function obtenerExperienciasActivas(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->obtenerExperienciasActivas($id_usuario));
    }

    public function obtenerResumenExperiencias(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->obtenerResumenExperiencias($id_usuario));
    }

    public function eliminarExperienciasPorUsuario(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->eliminarExperienciasPorUsuario($id_usuario));
    }

    // ── Producción Intelectual ──────────────────────────────────

    public function crearProduccionIntelectual(Request $request)
    {
        $request->validate([
            'id_user' => 'required|integer',
            'tipo_produccion' => 'required|string|max:100',
            'denominacion' => 'required|string|max:100',
            'nombre' => 'required|string|max:200',
            'objetivo' => 'nullable|string|max:200',
            'descripcion_actividades' => 'required|string|max:500',
            'duracion' => 'required|string|max:100',
            'lugar' => 'required|string|max:250',
            'observacion' => 'required|string|max:300',
            'evidencia_pdf' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $data = $request->except('evidencia_pdf');
        $archivo = $request->file('evidencia_pdf');

        return $this->apiResponse($this->service->crearProduccionIntelectual($data, $archivo));
    }

    public function actualizarProduccionIntelectual(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'id_user' => 'required|integer',
            'tipo_produccion' => 'nullable|string|max:100',
            'denominacion' => 'nullable|string|max:100',
            'nombre' => 'nullable|string|max:200',
            'objetivo' => 'nullable|string|max:200',
            'descripcion_actividades' => 'nullable|string|max:500',
            'duracion' => 'nullable|string|max:100',
            'lugar' => 'nullable|string|max:250',
            'observacion' => 'nullable|string|max:300',
            'evidencia_pdf' => 'nullable|file|mimes:pdf|max:10240',
        ]);

        $body = $request->except('evidencia_pdf');
        $id = $body['id'] ?? null;
        $archivo = $request->file('evidencia_pdf');

        return $this->apiResponse($this->service->actualizarProduccionIntelectual($id, $body, $archivo));
    }

    public function eliminarProduccionIntelectual(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarProduccionIntelectual($id));
    }

    public function obtenerProduccionesPorUsuario(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->obtenerProduccionesPorUsuario($id_usuario));
    }

    public function eliminarProduccionesPorUsuario(Request $request)
    {
        $id_usuario = $request->input('id_usuario');

        return $this->apiResponse($this->service->eliminarProduccionesPorUsuario($id_usuario));
    }

}
