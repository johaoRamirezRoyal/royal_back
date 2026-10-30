<?php

namespace App\Http\Controllers\Carnets;

use App\Http\Controllers\Controller;
use App\Services\Carnets\CarnetService;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/** Carnets institucionales (opción 144). `mio` es el carnet digital: autoservicio, sin opción. */
class CarnetController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([Permiso::CARNETS]), except: ['mio'])];
    }

    public function __construct(private CarnetService $service)
    {
    }

    /** ?tipo=estudiantes|trabajadores&cursos[]=&estado_carnet=SIN_CARNET|IMPRESO|...&sin_foto=1&s=&page=&per-page=&sort=&dir= */
    public function index(Request $request): JsonResponse
    {
        $filtros = $request->validate([
            'tipo' => 'nullable|in:estudiantes,trabajadores',
            'cursos' => 'nullable|array',
            'cursos.*' => 'integer',
            'estado_carnet' => 'nullable|in:SIN_CARNET,IMPRESO,ENTREGADO,PERDIDO,ANULADO',
            'sin_foto' => 'nullable|boolean',
            's' => 'nullable|string|max:100',
            'sort' => 'nullable|string',
            'dir' => 'nullable|string',
        ]);
        $filtros['per_page'] = min((int) $request->input('per-page', 10), 100);

        return $this->paginatedResponse($this->service->listar($filtros));
    }

    /** { "ids": [1, 2] } → los datos para imprimir cada carnet. */
    public function emitir(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
        ]);

        $response = $this->service->emitir($data['ids'], $request->user()->id_user);

        return $response['error']
            ? $this->error($response['message'], 422)
            : $this->success($response['message'], $response['data']);
    }

    /** { "ids": [1, 2] } → lo que saldría impreso, sin emitir (no cambia nada). */
    public function vistaPrevia(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
        ]);

        $response = $this->service->vistaPrevia($data['ids']);

        return $response['error']
            ? $this->error($response['message'], 422)
            : $this->success($response['message'], $response['data']);
    }

    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'estado' => 'required|in:ENTREGADO,PERDIDO,ANULADO',
            'observacion' => 'nullable|string|max:255',
        ]);

        $response = $this->service->cambiarEstado($id, $data['estado'], $data['observacion'] ?? null, $request->user()->id_user);

        return $response['error']
            ? $this->error($response['message'], $response['status'])
            : $this->success($response['message'], $response['data']);
    }

    /** { "ids": [id_user, ...] } → entrega el último carnet impreso de cada uno. */
    public function entregar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
        ]);

        $response = $this->service->entregar($data['ids'], $request->user()->id_user);

        return $this->success($response['message'], $response['data']);
    }

    public function historial(int $idUser): JsonResponse
    {
        $response = $this->service->historial($idUser);

        return $this->success($response['message'], $response['data']);
    }

    public function subirFoto(Request $request, int $idUser): JsonResponse
    {
        $request->validate([
            'foto' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ], ['foto.max' => 'La foto no puede pesar más de 4 MB.']);

        $response = $this->service->subirFoto($idUser, $request->file('foto'));

        return $response['error']
            ? $this->error($response['message'], $response['status'] ?? 500)
            : $this->success($response['message'], $response['data']);
    }

    /** multipart `fotos[]` (máx. 20 por petición: el `max_file_uploads` por defecto de PHP). */
    public function subirFotosLote(Request $request): JsonResponse
    {
        $request->validate([
            'fotos' => 'required|array|min:1|max:20',
            'fotos.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096',
        ], ['fotos.*.max' => 'Cada foto puede pesar hasta 4 MB.']);

        $response = $this->service->subirFotosLote($request->file('fotos'));

        return $this->success($response['message'], $response['data']);
    }

    public function mio(Request $request): JsonResponse
    {
        $response = $this->service->miCarnet($request->user()->id_user);

        return $this->success($response['message'], $response['data']);
    }
}
