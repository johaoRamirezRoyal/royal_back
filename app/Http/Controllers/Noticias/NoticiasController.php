<?php

namespace App\Http\Controllers\Noticias;

use App\Http\Controllers\Controller;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\FileStorageService;
use App\Services\Noticias\NoticiasService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NoticiasController extends Controller
{
    // cron_opciones id 69 ("NEWS Royal", id_modulo=3 Gestión Humana) — la migración
    // 2026_09_11_110000_seed_opcion_noticias asumía que el `insertGetId` de esa fila
    // daría 107, pero en esta BD el id 107 real es "Año Escolar y Periodos" (mismo tipo
    // de drift ya documentado para Instituciones en AGENTS.md, "104 vs 106"): dos
    // features distintas quedaban gateadas por la misma opción por error. Confirmado
    // contra la tabla real 2026-09-16.
    private const OPCION_NOTICIAS = 69;

    // cron_opciones id 128 ("Noticias — Correos de distribución") — ver migración
    // 2026_09_16_150000_seed_opcion_correos_distribucion_noticias. Separada de
    // OPCION_NOTICIAS a propósito: quien administra el contenido de Noticias no
    // necesariamente debe poder cambiar a qué direcciones reales se manda el correo
    // masivo. Renumerada de 147 a 128 el 2026-09-16 al sincronizar cron_opciones con
    // producción (147 era un id inflado por duplicados locales, ver AGENTS.md).
    private const OPCION_CORREOS_DISTRIBUCION = 128;

    public function __construct(
        private NoticiasService $service,
        private UsuariosServices $usuariosService,
        private FileStorageService $fileStorage,
        private CloudinaryService $cloudinary,
        private Request $request,
    ) {
    }

    /** Gestionar noticias (crear/editar/activar/desactivar el CRUD de contenido) sigue
     * restringido a RH/Admin — pero VERLAS (paraMostrar/verImagen) es para cualquier
     * usuario autenticado del sistema general, es lo que alimenta el contenedor de
     * noticias del Home. Por eso el chequeo ya no vive en el constructor (bloquearía
     * también a esas dos acciones) sino que cada acción de administración lo llama a
     * mano — mismo patrón que ColegioAdmisionController::ensureAdmin. */
    // Super Admin (perfil 1) siempre pasa, tenga o no la opción otorgada en
    // `cron_permisos` — mismo criterio que el bypass `allowedRoles={[1]}` del lado
    // frontend (router/sidebar): un Super Admin no debe quedar afuera de un módulo por
    // un permiso mal configurado o revocado por error.
    private const SUPER_ADMIN_PERFIL = 1;

    private function ensurePermisoGestion(): void
    {
        $perfil = $this->request->user()->perfil;

        if ($perfil === self::SUPER_ADMIN_PERFIL) {
            return;
        }

        if (!($this->usuariosService->tienePermiso(self::OPCION_NOTICIAS, $perfil)['permiso'] ?? false)) {
            abort($this->error('No tienes permiso para gestionar noticias', 403));
        }
    }

    /**
     * Guard propio de "Correos de distribución" — separado de ensurePermisoGestion()
     * porque es una opción distinta (128), ver la constante arriba.
     */
    private function ensurePermisoCorreosDistribucion(): void
    {
        $perfil = $this->request->user()->perfil;

        if ($perfil === self::SUPER_ADMIN_PERFIL) {
            return;
        }

        if (!($this->usuariosService->tienePermiso(self::OPCION_CORREOS_DISTRIBUCION, $perfil)['permiso'] ?? false)) {
            abort($this->error('No tienes permiso para gestionar los correos de distribución', 403));
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
            // 0 = "Todos" (alias all@royalschool.edu.co, un solo correo real de
            // distribución) sigue siendo válido — lo que causó el incidente de rate-limit
            // (ver MailRateLimitException) era el envío individual a cada usuario, no la
            // audiencia amplia en sí. Debe elegirse explícitamente algo, eso sí.
            'nivel' => 'required|integer|min:0',
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

    public function correosDistribucion(): JsonResponse
    {
        $this->ensurePermisoCorreosDistribucion();

        return $this->apiResponse($this->service->listarCorreosDistribucion());
    }

    public function crearCorreoDistribucion(Request $request): JsonResponse
    {
        $this->ensurePermisoCorreosDistribucion();

        $request->validate([
            'grupo' => 'required|string',
            'nombre' => 'nullable|string|max:190',
            'correo' => 'required|email|max:190',
            'activo' => 'nullable|boolean',
        ]);

        return $this->apiResponse($this->service->crearCorreoDistribucion($request->all()));
    }

    public function actualizarCorreoDistribucion(Request $request, int $id): JsonResponse
    {
        $this->ensurePermisoCorreosDistribucion();

        $request->validate([
            'nombre' => 'nullable|string|max:190',
            'correo' => 'required|email|max:190',
            'activo' => 'nullable|boolean',
        ]);

        return $this->apiResponse($this->service->actualizarCorreoDistribucion($id, $request->all()));
    }

    public function eliminarCorreoDistribucion(int $id): JsonResponse
    {
        $this->ensurePermisoCorreosDistribucion();

        return $this->apiResponse($this->service->eliminarCorreoDistribucion($id));
    }

    public function asignarGrupoNivel(Request $request, int $idNivel): JsonResponse
    {
        $this->ensurePermisoCorreosDistribucion();

        $request->validate([
            'grupo' => 'nullable|string',
        ]);

        return $this->apiResponse($this->service->asignarGrupoDelNivel($idNivel, $request->input('grupo')));
    }

    public function listarRevistas(): JsonResponse
    {
        $this->ensurePermisoGestion();

        return $this->apiResponse($this->service->listarRevistas());
    }

    /**
     * POST /api/noticias/revistas (multipart: titulo + descripcion opcional + archivo PDF)
     * — sube y crea en un solo paso, a diferencia de las imágenes: el PDF no se
     * previsualiza antes de guardar.
     */
    public function crearRevista(Request $request): JsonResponse
    {
        $this->ensurePermisoGestion();

        $request->validate([
            'titulo' => 'required|string|max:200',
            'descripcion' => 'nullable|string|max:2000',
            // 10 MB — el máximo que acepta CloudinaryService::validateFile (y el plan
            // gratuito de Cloudinary para PDFs).
            'archivo' => 'required|file|mimes:pdf|max:10240',
        ]);

        // public_id único: si no, dos PDFs con el mismo nombre se pisan en Cloudinary.
        $subida = $this->cloudinary->uploadFile($request->file('archivo'), 'Noticias/Revistas', 'revista_' . Str::uuid());

        if ($subida['error']) {
            return $this->apiResponse($subida);
        }

        return $this->apiResponse(
            $this->service->crearRevista(
                trim($request->input('titulo')),
                $request->filled('descripcion') ? trim($request->input('descripcion')) : null,
                $subida['data']['url'],
                $subida['data']['public_id'],
                $request->user()->id_user,
            )
        );
    }

    /** PUT /api/noticias/revistas/{id} — solo título y descripción; el PDF no se reemplaza. */
    public function actualizarRevista(Request $request, int $id): JsonResponse
    {
        $this->ensurePermisoGestion();

        $request->validate([
            'titulo' => 'required|string|max:200',
            'descripcion' => 'nullable|string|max:2000',
        ]);

        return $this->apiResponse(
            $this->service->actualizarRevista(
                $id,
                trim($request->input('titulo')),
                $request->filled('descripcion') ? trim($request->input('descripcion')) : null,
            )
        );
    }

    public function cambiarEstadoRevista(Request $request, int $id): JsonResponse
    {
        $this->ensurePermisoGestion();

        $request->validate(['activo' => 'required|boolean']);

        return $this->apiResponse($this->service->cambiarEstadoRevista($id, $request->boolean('activo')));
    }

    public function eliminarRevista(int $id): JsonResponse
    {
        $this->ensurePermisoGestion();

        $resultado = $this->service->eliminarRevista($id);
        // Los PDF se suben como resource_type "image" (CloudinaryService::getResourceType),
        // no "raw" (el default de deleteFile) — ver InstitucionAdminController.
        if (!$resultado['error']) {
            $this->cloudinary->deleteFile($resultado['public_id'], 'image');
        }

        return $this->apiResponse($resultado);
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
