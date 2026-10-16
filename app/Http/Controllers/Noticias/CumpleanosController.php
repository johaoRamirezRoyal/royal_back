<?php

namespace App\Http\Controllers\Noticias;

use App\Http\Controllers\Controller;
use App\Services\FileStorageService;
use App\Services\Noticias\CumpleanosImagen;
use App\Services\Noticias\CumpleanosService;
use App\Services\PerfilUsuario\PerfilUsuarioService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Configuración de cumpleaños (`/api/noticias/cumpleanos/*`). Mismo permiso que la gestión
 * de Noticias (opción 69, Super Admin siempre pasa) — ver NoticiasController.
 */
class CumpleanosController extends Controller
{
    private const OPCION_NOTICIAS = 69;
    private const SUPER_ADMIN_PERFIL = 1;

    public function __construct(
        private CumpleanosService $service,
        private UsuariosServices $usuariosService,
        private Request $request,
    ) {
        $perfil = $this->request->user()?->perfil;
        if ($perfil !== self::SUPER_ADMIN_PERFIL && !($this->usuariosService->tienePermiso(self::OPCION_NOTICIAS, $perfil)['permiso'] ?? false)) {
            abort($this->error('No tienes permiso para gestionar noticias', 403));
        }
    }

    private function reglasPlantilla(): array
    {
        return [
            'nombre' => 'required|string|max:120',
            'descripcion' => 'nullable|string|max:1000',
            'genero' => ['required', Rule::in(CumpleanosService::GENEROS)],
            'activo' => 'required|boolean',
            'imagen' => 'nullable|string|max:250|not_regex:/\.\./',
            'elementos' => 'nullable|array',
        ];
    }

    public function listarPlantillas(): JsonResponse
    {
        return $this->apiResponse($this->service->listarPlantillas());
    }

    public function obtenerPlantilla(int $id): JsonResponse
    {
        return $this->apiResponse($this->service->obtenerPlantilla($id));
    }

    public function crearPlantilla(Request $request): JsonResponse
    {
        $request->validate($this->reglasPlantilla());

        return $this->apiResponse($this->service->guardarPlantilla(null, $request->all(), $request->user()->id_user));
    }

    public function actualizarPlantilla(Request $request, int $id): JsonResponse
    {
        $request->validate($this->reglasPlantilla());

        return $this->apiResponse($this->service->guardarPlantilla($id, $request->all(), $request->user()->id_user));
    }

    public function cambiarEstadoPlantilla(Request $request, int $id): JsonResponse
    {
        $request->validate(['activo' => 'required|boolean']);

        return $this->apiResponse($this->service->cambiarEstadoPlantilla($id, $request->boolean('activo')));
    }

    public function duplicarPlantilla(Request $request, int $id): JsonResponse
    {
        return $this->apiResponse($this->service->duplicarPlantilla($id, $request->user()->id_user));
    }

    public function eliminarPlantilla(int $id): JsonResponse
    {
        return $this->apiResponse($this->service->eliminarPlantilla($id));
    }

    /** POST /cumpleanos/imagen — sube la imagen base y devuelve ruta + medidas + transparencia. */
    public function subirImagen(Request $request, FileStorageService $archivos): JsonResponse
    {
        $request->validate([
            // Hasta 3000 px por lado: GD compone en memoria (~36 MB a 3000×3000).
            'imagen' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120|dimensions:min_width=500,min_height=500,max_width=3000,max_height=3000',
        ], [
            'imagen.dimensions' => 'La imagen debe medir entre 500 y 3000 píxeles por lado.',
            'imagen.max' => 'La imagen no puede pesar más de 5 MB.',
            'imagen.mimes' => 'Formato no permitido: usa JPG, PNG o WebP.',
        ]);

        $archivo = $archivos->uploadFile($request->file('imagen'), 'noticias/cumpleanos/plantillas');

        return $this->apiResponse([
            'error' => false,
            'message' => 'Imagen subida',
            'data' => ['ruta' => $archivo['ruta']] + $this->service->inspeccionarImagen($archivo['ruta']),
        ]);
    }

    /** POST /cumpleanos/vista-previa — JPEG exacto de la felicitación (mismo código que la generación). */
    public function vistaPrevia(Request $request)
    {
        $request->validate([
            'imagen' => 'required|string|max:250',
            'elementos' => 'nullable|array',
            'id_user' => 'nullable|integer',
        ]);

        $resultado = $this->service->vistaPrevia($request->input('imagen'), $request->input('elementos', []), $request->input('id_user'));
        if ($resultado['error']) {
            return $this->apiResponse($resultado);
        }

        return response($resultado['data'], 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-store']);
    }

    /** GET /cumpleanos/fuentes — catálogo; el editor carga cada archivo con /fuentes/{archivo}. */
    public function fuentes(): JsonResponse
    {
        return $this->apiResponse(['error' => false, 'data' => CumpleanosImagen::FUENTES]);
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

    public function personal(): JsonResponse
    {
        return $this->apiResponse($this->service->listarPersonal());
    }

    public function actualizarPersona(Request $request, int $idUser): JsonResponse
    {
        $request->validate([
            'fecha_nacimiento' => 'nullable|date|before:today|after:1900-01-01',
            'genero' => ['nullable', Rule::in(CumpleanosService::GENEROS)],
        ], [
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
        ]);

        return $this->apiResponse($this->service->actualizarDatosPersona($idUser, $request->input('fecha_nacimiento'), $request->input('genero')));
    }

    /** POST /cumpleanos/personal/{idUser}/foto — nueva foto de perfil (misma tabla que "Mi perfil"). */
    public function subirFotoPersona(Request $request, int $idUser, PerfilUsuarioService $perfiles): JsonResponse
    {
        $request->validate(['foto' => 'required|file|mimes:jpg,jpeg,png,webp|max:4096']);

        if (!$this->service->personal()->contains('id_user', $idUser)) {
            return $this->apiResponse(['error' => true, 'message' => 'La persona no está en el personal activo']);
        }

        $resultado = $perfiles->agregarFotoPerfil($idUser, $request->file('foto'));
        if ($resultado['error']) {
            return $this->apiResponse($resultado);
        }

        return $this->apiResponse([
            'error' => false,
            'message' => 'Foto guardada',
            'data' => $this->service->personal()->firstWhere('id_user', $idUser),
        ]);
    }

    public function cambiarParticipacion(Request $request): JsonResponse
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'participa' => 'required|boolean',
        ]);

        return $this->apiResponse($this->service->cambiarParticipacion($request->input('ids'), $request->boolean('participa')));
    }

    public function configuracion(): JsonResponse
    {
        return $this->apiResponse($this->service->obtenerConfiguracion());
    }

    public function actualizarConfiguracion(Request $request): JsonResponse
    {
        $request->validate([
            'automatico' => 'required|boolean',
            'plantilla_masculino' => 'nullable|integer',
            'plantilla_femenino' => 'nullable|integer',
            'dias_anticipacion' => 'required|integer|min:0|max:30',
            'titulo' => 'required|string|max:200',
        ]);

        return $this->apiResponse($this->service->actualizarConfiguracion($request->all()));
    }

    public function proximos(Request $request): JsonResponse
    {
        return $this->apiResponse($this->service->listarProximos(min(120, max(7, (int) $request->input('dias', 45)))));
    }

    public function generar(): JsonResponse
    {
        return $this->apiResponse($this->service->generarPendientes());
    }

    public function regenerar(int $idUser): JsonResponse
    {
        return $this->apiResponse($this->service->regenerar($idUser));
    }
}
