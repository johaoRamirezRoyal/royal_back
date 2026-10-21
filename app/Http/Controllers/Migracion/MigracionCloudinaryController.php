<?php

namespace App\Http\Controllers\Migracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Migracion\SubirArchivosCloudinaryRequest;
use App\Services\Cloudinary\CloudinaryService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Migración de los archivos del disco de uploads a Cloudinary (lo consume el script
 * externo script_liberacion). Sin JWT: se protege con el header X-Migracion-Token contra
 * MIGRACION_API_TOKEN; con la variable vacía el endpoint no existe (404).
 */
class MigracionCloudinaryController extends Controller implements HasMiddleware
{
    /** Carpeta raíz en Cloudinary; debajo va el directorio original del archivo. */
    private const CARPETA_RAIZ = 'royal';

    /** Todas las extensiones que hay hoy en las tablas con archivos. */
    private const EXTENSIONES_MIGRACION = [
        'jpg', 'jpeg', 'png', 'webp', 'gif', 'jfif', 'avif', 'pdf', 'docx', 'doc', 'xlsx', 'xls', 'zip', 'html',
    ];

    public function __construct(private CloudinaryService $cloudinary) {}

    /** El token se valida antes que el FormRequest: sin token no se llega a validar nada. */
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $request, Closure $next) {
                $token = (string) config('cloudinary.migracion_token');

                if ($token === '') {
                    abort(404);
                }

                if (! hash_equals($token, (string) $request->header('X-Migracion-Token'))) {
                    return response()->json(['error' => true, 'message' => 'Token de migración inválido.'], 401);
                }

                return $next($request);
            }),
        ];
    }

    /**
     * POST /api/migracion/cloudinary (multipart: directorio opcional + archivos[]).
     * El public_id es el nombre sin extensión (overwrite), así repetir la migración
     * sobrescribe en vez de duplicar. Un archivo que falla no corta el lote.
     */
    public function subir(SubirArchivosCloudinaryRequest $request): JsonResponse
    {
        $directorio = trim((string) $request->input('directorio', ''), '/');
        $carpeta = $directorio === '' ? self::CARPETA_RAIZ : self::CARPETA_RAIZ.'/'.$directorio;

        $resultados = [];

        foreach ($request->file('archivos') as $archivo) {
            $nombre = $archivo->getClientOriginalName();
            $subida = $this->cloudinary->uploadFile(
                $archivo,
                $carpeta,
                pathinfo($nombre, PATHINFO_FILENAME),
                self::EXTENSIONES_MIGRACION
            );

            $resultados[] = [
                'nombre' => $nombre,
                'ok' => ! $subida['error'],
                'public_id' => $subida['data']['public_id'] ?? null,
                'url' => $subida['data']['url'] ?? null,
                'mensaje' => $subida['message'],
            ];
        }

        $fallidos = count(array_filter($resultados, fn (array $r) => ! $r['ok']));

        return response()->json([
            'error' => false,
            'message' => $fallidos === 0
                ? 'Archivos subidos correctamente.'
                : "{$fallidos} de ".count($resultados).' archivos no se pudieron subir.',
            'data' => $resultados,
        ]);
    }
}
