<?php

namespace App\Http\Controllers\Noticias;

use App\Http\Controllers\Controller;
use App\Services\FileStorageService;
use App\Services\Noticias\NoticiasService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NoticiasController extends Controller
{
    // cron_opciones id 107 ("Noticias", id_modulo=3 Gestión Humana) — ver migración
    // 2026_09_11_110000_seed_opcion_noticias.
    private const OPCION_NOTICIAS = 107;

    public function __construct(
        private NoticiasService $service,
        private UsuariosServices $usuariosService,
        private FileStorageService $fileStorage,
        private Request $request,
    ) {
    }

    /** Gestionar noticias (crear/editar/activar/desactivar el CRUD de contenido) sigue
     * restringido a RH/Admin — pero VERLAS (paraMostrar/verImagen) es para cualquier
     * usuario autenticado del sistema general, es lo que alimenta el contenedor de
     * noticias del Home. Por eso el chequeo ya no vive en el constructor (bloquearía
     * también a esas dos acciones) sino que cada acción de administración lo llama a
     * mano — mismo patrón que ColegioAdmisionController::ensureAdmin. */
    private function ensurePermisoGestion(): void
    {
        $perfil = $this->request->user()->perfil;

        if (!($this->usuariosService->tienePermiso(self::OPCION_NOTICIAS, $perfil)['permiso'] ?? false)) {
            abort($this->error('No tienes permiso para gestionar noticias', 403));
        }
    }

    /**
     * GET /api/noticias/para-mostrar — mensaje general activo + programadas activas de
     * los últimos 30 días para el nivel del usuario autenticado (o todas si es
     * "todos los niveles" = nivel 0). Sin gating de OPCION_NOTICIAS: cualquier usuario
     * del sistema general puede verlas, es contenido informativo, no administración.
     */
    public function paraMostrar(): JsonResponse
    {
        return $this->apiResponse(
            $this->service->obtenerParaMostrar($this->request->user()->id_nivel)
        );
    }

    public function obtenerGeneral(): JsonResponse
    {
        $this->ensurePermisoGestion();

        return $this->apiResponse($this->service->obtenerMensajeGeneral());
    }

    public function actualizarGeneral(Request $request): JsonResponse
    {
        $this->ensurePermisoGestion();

        $request->validate([
            'titulo' => 'nullable|string|max:200',
            'mensaje' => 'nullable|string',
            'imagen' => 'nullable|string|max:250',
            'activo' => 'required|boolean',
        ]);

        return $this->apiResponse(
            $this->service->actualizarMensajeGeneral($request->all(), $request->user()->id_user)
        );
    }

    /**
     * GET /api/noticias?per-page=&s=&nivel=&activo=&fecha_desde=&fecha_hasta=
     */
    public function listar(Request $request): JsonResponse
    {
        $this->ensurePermisoGestion();

        $filtros = [
            's' => $request->input('s') ? trim($request->input('s')) : null,
            'nivel' => $request->input('nivel'),
            'activo' => $request->input('activo'),
            'fecha_desde' => $request->input('fecha_desde'),
            'fecha_hasta' => $request->input('fecha_hasta'),
        ];

        return $this->paginatedResponse(
            $this->service->listarProgramados($filtros, (int) $request->input('per-page', 10))
        );
    }

    public function crear(Request $request): JsonResponse
    {
        $this->ensurePermisoGestion();

        $request->validate([
            'fecha' => 'required|date',
            'titulo' => 'required|string|max:200',
            'mensaje' => 'nullable|string',
            'imagen' => 'nullable|string|max:250',
            'url' => 'nullable|string|max:200',
            'nivel' => 'nullable|integer',
            'tipo' => 'nullable|in:normal,cumpleanos',
            'dias_visualizacion' => 'nullable|integer|min:1',
            'activo' => 'required|boolean',
        ]);

        return $this->apiResponse(
            $this->service->crearProgramado($request->all(), $request->user()->id_user)
        );
    }

    public function actualizar(Request $request): JsonResponse
    {
        $this->ensurePermisoGestion();

        $request->validate([
            'id' => 'required|integer',
            'fecha' => 'required|date',
            'titulo' => 'required|string|max:200',
            'mensaje' => 'nullable|string',
            'imagen' => 'nullable|string|max:250',
            'url' => 'nullable|string|max:200',
            'nivel' => 'nullable|integer',
            'tipo' => 'nullable|in:normal,cumpleanos',
            'dias_visualizacion' => 'nullable|integer|min:1',
            'activo' => 'required|boolean',
        ]);

        return $this->apiResponse(
            $this->service->actualizarProgramado((int) $request->input('id'), $request->all())
        );
    }

    public function cambiarEstado(Request $request): JsonResponse
    {
        $this->ensurePermisoGestion();

        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer',
            'estado' => 'required|integer|in:0,1',
        ]);

        return $this->apiResponse(
            $this->service->cambiarEstadoProgramados($request->input('ids'), (int) $request->input('estado'))
        );
    }

    /**
     * POST /api/noticias/imagen — sube la imagen y devuelve la ruta a guardar en
     * `imagen` (mismo flujo de dos pasos que BibliotecaController::subirImagenBiblioteca:
     * subir primero, luego enviar la ruta devuelta en el payload de crear/actualizar).
     */
    public function subirImagen(Request $request): JsonResponse
    {
        $this->ensurePermisoGestion();

        $request->validate([
            'imagen' => 'required|file|mimes:jpg,jpeg,png,webp,gif|max:2048',
        ]);

        $archivo = $this->fileStorage->uploadFile($request->file('imagen'), 'noticias');

        return $this->apiResponse([
            'error' => false,
            'message' => 'Imagen subida correctamente',
            'data' => $archivo,
        ]);
    }

    /**
     * GET /api/noticias/imagen/{ruta} — sirve la imagen desde el disco de uploads
     * ({ruta} = el valor completo guardado en `imagen`, ej. "noticias/uuid.jpg"; mismo
     * patrón que BibliotecaController::verImagenBiblioteca).
     */
    public function verImagen(string $ruta)
    {
        if (str_contains($ruta, '..')) {
            abort(404);
        }

        $disk = Storage::disk(config('filesystems.uploads_disk', 'public'));

        if (!$disk->exists($ruta)) {
            abort(404);
        }

        return $disk->response($ruta);
    }
}
