<?php

namespace App\Http\Controllers\Carnets;

use App\Http\Controllers\Controller;
use App\Services\Carnets\CarnetPlantillaService;
use App\Services\FileStorageService;
use App\Services\Noticias\CumpleanosImagen;
use App\Support\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

/**
 * Plantillas de carnet (opción 144). Las tipografías (`fuentes`) son las de cumpleaños y no
 * piden la opción: el carnet digital de cualquier usuario las necesita para dibujarse.
 */
class CarnetPlantillaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(Permiso::ruta([Permiso::CARNETS]), except: ['fuentes', 'archivoFuente'])];
    }

    public function __construct(private CarnetPlantillaService $service)
    {
    }

    private function responder(array $r): JsonResponse
    {
        return $r['error']
            ? $this->error($r['message'], $r['status'] ?? 500)
            : $this->success($r['message'], $r['data'] ?? null);
    }

    private function validarPlantilla(Request $request): array
    {
        return $request->validate([
            'nombre' => 'required|string|max:120',
            'descripcion' => 'nullable|string|max:1000',
            'tipo' => ['required', Rule::in(CarnetPlantillaService::TIPOS)],
            'activo' => 'required|boolean',
            'imagen' => 'nullable|string|max:250|not_regex:/\.\./',
            'elementos' => 'nullable|array',
        ]);
    }

    public function index(): JsonResponse
    {
        return $this->responder($this->service->listar());
    }

    public function show(int $id): JsonResponse
    {
        return $this->responder($this->service->obtener($id));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->responder($this->service->guardar(null, $this->validarPlantilla($request), $request->user()->id_user));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return $this->responder($this->service->guardar($id, $this->validarPlantilla($request), $request->user()->id_user));
    }

    public function cambiarEstado(Request $request, int $id): JsonResponse
    {
        $request->validate(['activo' => 'required|boolean']);

        return $this->responder($this->service->cambiarEstado($id, $request->boolean('activo')));
    }

    public function duplicar(Request $request, int $id): JsonResponse
    {
        return $this->responder($this->service->duplicar($id, $request->user()->id_user));
    }

    public function destroy(int $id): JsonResponse
    {
        return $this->responder($this->service->eliminar($id));
    }

    /** Imagen de fondo: vertical, con la proporción del carnet (54 × 85,6 mm). */
    public function subirImagen(Request $request, FileStorageService $archivos): JsonResponse
    {
        $request->validate([
            'imagen' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120|dimensions:min_width=400,max_width=3000,max_height=3000',
        ], [
            'imagen.dimensions' => 'La imagen debe medir entre 400 y 3000 píxeles de ancho (y hasta 3000 de alto).',
            'imagen.max' => 'La imagen no puede pesar más de 5 MB.',
            'imagen.mimes' => 'Formato no permitido: usa JPG, PNG o WebP.',
        ]);

        [$ancho, $alto] = getimagesize($request->file('imagen')->getRealPath()) ?: [0, 0];
        if (!CarnetPlantillaService::proporcionValida($ancho, $alto)) {
            return $this->error('La imagen debe tener la proporción del carnet vertical (54 × 85,6 mm), por ejemplo 638 × 1011 px.', 422);
        }

        $archivo = $archivos->uploadFile($request->file('imagen'), 'carnets/plantillas');

        return $this->success('Imagen subida', ['ruta' => $archivo['ruta']] + $this->service->inspeccionarImagen($archivo['ruta']));
    }

    public function fuentes(): JsonResponse
    {
        return $this->success('Tipografías', CumpleanosImagen::FUENTES);
    }

    public function archivoFuente(string $archivo)
    {
        $validos = collect(CumpleanosImagen::FUENTES)->flatMap(fn ($f) => array_values($f['archivos']));
        abort_unless($validos->contains($archivo), 404);

        return response()->file(CumpleanosImagen::rutaFuente($archivo), [
            'Content-Type' => 'font/ttf',
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }
}
