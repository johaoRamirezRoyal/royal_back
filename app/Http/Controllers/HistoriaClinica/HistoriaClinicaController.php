<?php

namespace App\Http\Controllers\HistoriaClinica;

use App\Http\Controllers\Controller;
use App\Http\Requests\HistoriaClinica\HceCognitivoLenguajeRequest;
use App\Http\Requests\HistoriaClinica\HceDesarrolloPsicomotorRequest;
use App\Http\Requests\HistoriaClinica\HceEmbarazoPartoRequest;
use App\Http\Requests\HistoriaClinica\HceEstructuraFamiliarRequest;
use App\Http\Requests\HistoriaClinica\HceHermanoRequest;
use App\Http\Requests\HistoriaClinica\HceHistoriaEscolarRequest;
use App\Http\Requests\HistoriaClinica\HceObservacionesFirmasRequest;
use App\Http\Requests\HistoriaClinica\HcePsicoafectivaRequest;
use App\Http\Requests\HistoriaClinica\HceRemisionRequest;
use App\Services\HistoriaClinica\HistoriaClinicaService;
use Illuminate\Http\Request;

class HistoriaClinicaController extends Controller
{
    public function __construct(
        private HistoriaClinicaService $service
    ) {}

    // ── Cognitivo Lenguaje ──────────────────────────────────────

    public function verCognitivoLenguaje(Request $request)
    {
        $id_inscripcion = $request->input('id_inscripcion');

        return $this->apiResponse($this->service->verCognitivoLenguaje($id_inscripcion));
    }

    public function añadirCognitivoLenguaje(HceCognitivoLenguajeRequest $request)
    {
        return $this->apiResponse($this->service->añadirCognitivoLenguaje($request->validated()));
    }

    public function actualizarCognitivoLenguaje(HceCognitivoLenguajeRequest $request)
    {
        $body = $request->validated();
        $id = $body['id'] ?? null;

        return $this->apiResponse($this->service->actualizarCognitivoLenguaje($id, $body));
    }

    public function eliminarCognitivoLenguaje(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarCognitivoLenguaje($id));
    }

    // ── Desarrollo Psicomotor ───────────────────────────────────

    public function verDesarrolloPsicomotor(Request $request)
    {
        $id_inscripcion = $request->input('id_inscripcion');

        return $this->apiResponse($this->service->verDesarrolloPsicomotor($id_inscripcion));
    }

    public function añadirDesarrolloPsicomotor(HceDesarrolloPsicomotorRequest $request)
    {
        return $this->apiResponse($this->service->añadirDesarrolloPsicomotor($request->validated()));
    }

    public function actualizarDesarrolloPsicomotor(HceDesarrolloPsicomotorRequest $request)
    {
        $body = $request->validated();
        $id = $body['id'] ?? null;

        return $this->apiResponse($this->service->actualizarDesarrolloPsicomotor($id, $body));
    }

    public function eliminarDesarrolloPsicomotor(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarDesarrolloPsicomotor($id));
    }

    // ── Embarazo Parto ──────────────────────────────────────────

    public function verEmbarazoParto(Request $request)
    {
        $id_inscripcion = $request->input('id_inscripcion');

        return $this->apiResponse($this->service->verEmbarazoParto($id_inscripcion));
    }

    public function añadirEmbarazoParto(HceEmbarazoPartoRequest $request)
    {
        return $this->apiResponse($this->service->añadirEmbarazoParto($request->validated()));
    }

    public function actualizarEmbarazoParto(HceEmbarazoPartoRequest $request)
    {
        $body = $request->validated();
        $id = $body['id'] ?? null;

        return $this->apiResponse($this->service->actualizarEmbarazoParto($id, $body));
    }

    public function eliminarEmbarazoParto(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarEmbarazoParto($id));
    }

    // ── Estructura Familiar ─────────────────────────────────────

    public function verEstructuraFamiliar(Request $request)
    {
        $id_inscripcion = $request->input('id_inscripcion');

        return $this->apiResponse($this->service->verEstructuraFamiliar($id_inscripcion));
    }

    public function añadirEstructuraFamiliar(HceEstructuraFamiliarRequest $request)
    {
        return $this->apiResponse($this->service->añadirEstructuraFamiliar($request->validated()));
    }

    public function actualizarEstructuraFamiliar(HceEstructuraFamiliarRequest $request)
    {
        $body = $request->validated();
        $id = $body['id'] ?? null;

        return $this->apiResponse($this->service->actualizarEstructuraFamiliar($id, $body));
    }

    public function eliminarEstructuraFamiliar(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarEstructuraFamiliar($id));
    }

    // ── Hermanos ────────────────────────────────────────────────

    public function verHermanos(Request $request)
    {
        $id_inscripcion = $request->input('id_inscripcion');

        return $this->apiResponse($this->service->verHermanos($id_inscripcion));
    }

    public function añadirHermano(HceHermanoRequest $request)
    {
        return $this->apiResponse($this->service->añadirHermano($request->validated()));
    }

    public function actualizarHermano(HceHermanoRequest $request)
    {
        $body = $request->validated();
        $id = $body['id'] ?? null;

        return $this->apiResponse($this->service->actualizarHermano($id, $body));
    }

    public function eliminarHermano(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarHermano($id));
    }

    // ── Historia Escolar ────────────────────────────────────────

    public function verHistoriaEscolar(Request $request)
    {
        $id_inscripcion = $request->input('id_inscripcion');

        return $this->apiResponse($this->service->verHistoriaEscolar($id_inscripcion));
    }

    public function añadirHistoriaEscolar(HceHistoriaEscolarRequest $request)
    {
        return $this->apiResponse($this->service->añadirHistoriaEscolar($request->validated()));
    }

    public function actualizarHistoriaEscolar(HceHistoriaEscolarRequest $request)
    {
        $body = $request->validated();
        $id = $body['id'] ?? null;

        return $this->apiResponse($this->service->actualizarHistoriaEscolar($id, $body));
    }

    public function eliminarHistoriaEscolar(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarHistoriaEscolar($id));
    }

    // ── Observaciones y Firmas ──────────────────────────────────

    public function verObservacionesFirmas(Request $request)
    {
        $id_inscripcion = $request->input('id_inscripcion');

        return $this->apiResponse($this->service->verObservacionesFirmas($id_inscripcion));
    }

    public function añadirObservacionesFirmas(HceObservacionesFirmasRequest $request)
    {
        return $this->apiResponse($this->service->añadirObservacionesFirmas($request->validated()));
    }

    public function actualizarObservacionesFirmas(HceObservacionesFirmasRequest $request)
    {
        $body = $request->validated();
        $id = $body['id'] ?? null;

        return $this->apiResponse($this->service->actualizarObservacionesFirmas($id, $body));
    }

    public function subirFirma(Request $request)
    {
        $id = $request->route('id');
        $campo = $request->route('campo');
        $file = $request->file('firma');

        return $this->apiResponse($this->service->subirFirma($id, $campo, $file));
    }

    public function eliminarObservacionesFirmas(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarObservacionesFirmas($id));
    }

    // ── Psicoafectiva ───────────────────────────────────────────

    public function verPsicoafectiva(Request $request)
    {
        $id_inscripcion = $request->input('id_inscripcion');

        return $this->apiResponse($this->service->verPsicoafectiva($id_inscripcion));
    }

    public function añadirPsicoafectiva(HcePsicoafectivaRequest $request)
    {
        return $this->apiResponse($this->service->añadirPsicoafectiva($request->validated()));
    }

    public function actualizarPsicoafectiva(HcePsicoafectivaRequest $request)
    {
        $body = $request->validated();
        $id = $body['id'] ?? null;

        return $this->apiResponse($this->service->actualizarPsicoafectiva($id, $body));
    }

    public function eliminarPsicoafectiva(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarPsicoafectiva($id));
    }

    // ── Remisión ────────────────────────────────────────────────

    public function verRemision(Request $request)
    {
        $id_inscripcion = $request->input('id_inscripcion');

        return $this->apiResponse($this->service->verRemision($id_inscripcion));
    }

    public function añadirRemision(HceRemisionRequest $request)
    {
        return $this->apiResponse($this->service->añadirRemision($request->validated()));
    }

    public function actualizarRemision(HceRemisionRequest $request)
    {
        $body = $request->validated();
        $id = $body['id'] ?? null;

        return $this->apiResponse($this->service->actualizarRemision($id, $body));
    }

    public function eliminarRemision(Request $request)
    {
        $id = $request->input('id');

        return $this->apiResponse($this->service->eliminarRemision($id));
    }
}
