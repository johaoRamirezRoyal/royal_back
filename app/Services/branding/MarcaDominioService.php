<?php

namespace App\Services\branding;

use App\Models\Branding\MarcaDominio;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\Service;
use Exception;
use Illuminate\Http\UploadedFile;

/**
 * Logo de la app según el dominio de correo del usuario (multi-tenant) — el archivo se
 * sube a Cloudinary (mismo storage que ya usan firmas/documentos de admisiones, ver
 * CloudinaryService), no al disco local: son pocos logos institucionales, pero se
 * mantiene consistencia con el resto de uploads de imágenes del backend.
 */
class MarcaDominioService extends Service
{
    private const CARPETA = 'marcas';

    /** Mismo set que CloudinaryService::validateFile acepta para imágenes — subir un svg
     * pasaría esta validación pero luego fallaría dentro de uploadFile() con un mensaje
     * menos claro, así que se corta acá. */
    private const EXTENSIONES_PERMITIDAS = ['jpg', 'jpeg', 'png', 'webp'];

    private const TAMANO_MAXIMO = 5 * 1024 * 1024;

    public function __construct(private CloudinaryService $cloudinaryService)
    {
    }

    public function listar(): array
    {
        try {
            return [
                'error' => false,
                'message' => 'Marcas obtenidas correctamente.',
                'data' => MarcaDominio::orderBy('dominio')->get(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al listar las marcas por dominio');

            return ['error' => true, 'message' => 'Error en el servidor al listar las marcas.', 'data' => []];
        }
    }

    public function crear(array $datos, UploadedFile $logo): array
    {
        try {
            $errorValidacion = $this->validarLogo($logo);
            if ($errorValidacion) {
                return ['error' => true, 'message' => $errorValidacion, 'data' => []];
            }

            $dominio = $this->normalizarDominio($datos['dominio']);

            if (MarcaDominio::where('dominio', $dominio)->exists()) {
                return ['error' => true, 'message' => 'Ya existe una marca configurada para ese dominio.', 'data' => []];
            }

            $subido = $this->subirLogo($logo);
            if (!$subido) {
                return ['error' => true, 'message' => 'Error al subir el logo a Cloudinary.', 'data' => []];
            }

            $marca = MarcaDominio::create([
                'dominio' => $dominio,
                'nombre' => $datos['nombre'] ?? null,
                'color' => empty($datos['color']) ? null : $datos['color'],
                'logo_path' => $subido['url'],
                'logo_public_id' => $subido['public_id'],
                'activo' => true,
            ]);

            return ['error' => false, 'message' => 'Marca creada correctamente.', 'data' => $marca];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear la marca por dominio');

            return ['error' => true, 'message' => 'Error en el servidor al crear la marca.', 'data' => []];
        }
    }

    public function actualizar(int $id, array $datos, ?UploadedFile $logo = null): array
    {
        try {
            $marca = MarcaDominio::find($id);

            if (!$marca) {
                return ['error' => true, 'message' => 'La marca no existe.', 'data' => []];
            }

            if ($logo) {
                $errorValidacion = $this->validarLogo($logo);
                if ($errorValidacion) {
                    return ['error' => true, 'message' => $errorValidacion, 'data' => []];
                }
            }

            $dominio = isset($datos['dominio']) ? $this->normalizarDominio($datos['dominio']) : $marca->dominio;

            if ($dominio !== $marca->dominio && MarcaDominio::where('dominio', $dominio)->exists()) {
                return ['error' => true, 'message' => 'Ya existe una marca configurada para ese dominio.', 'data' => []];
            }

            $publicIdAnterior = $marca->logo_public_id;

            $marca->dominio = $dominio;
            $marca->nombre = $datos['nombre'] ?? $marca->nombre;
            $marca->color = array_key_exists('color', $datos) ? (empty($datos['color']) ? null : $datos['color']) : $marca->color;

            if ($logo) {
                $subido = $this->subirLogo($logo);
                if (!$subido) {
                    return ['error' => true, 'message' => 'Error al subir el logo a Cloudinary.', 'data' => []];
                }
                $marca->logo_path = $subido['url'];
                $marca->logo_public_id = $subido['public_id'];
            }

            $marca->save();

            if ($logo && $publicIdAnterior && $publicIdAnterior !== $marca->logo_public_id) {
                $this->cloudinaryService->deleteFile($publicIdAnterior, 'image');
            }

            return ['error' => false, 'message' => 'Marca actualizada correctamente.', 'data' => $marca];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar la marca por dominio');

            return ['error' => true, 'message' => 'Error en el servidor al actualizar la marca.', 'data' => []];
        }
    }

    public function cambiarEstado(array $ids, bool $estado): array
    {
        try {
            $actualizados = MarcaDominio::whereIn('id', $ids)->update(['activo' => $estado]);

            return [
                'error' => false,
                'message' => $estado ? 'Marca(s) habilitada(s) correctamente.' : 'Marca(s) deshabilitada(s) correctamente.',
                'data' => $actualizados,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al cambiar el estado de la marca por dominio');

            return ['error' => true, 'message' => 'Error en el servidor al cambiar el estado.', 'data' => []];
        }
    }

    public function eliminar(array $ids): array
    {
        try {
            $marcas = MarcaDominio::whereIn('id', $ids)->get();

            if ($marcas->isEmpty()) {
                return ['error' => true, 'message' => 'No se encontraron marcas para eliminar.', 'data' => []];
            }

            foreach ($marcas as $marca) {
                $this->cloudinaryService->deleteFile($marca->logo_public_id, 'image');
            }

            MarcaDominio::whereIn('id', $ids)->delete();

            return ['error' => false, 'message' => 'Marca(s) eliminada(s) correctamente.', 'data' => []];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar la marca por dominio');

            return ['error' => true, 'message' => 'Error en el servidor al eliminar la marca.', 'data' => []];
        }
    }

    /**
     * Resuelve el logo a mostrar para un correo dado — null si no hay correo, no matchea
     * ningún dominio, o la marca está desactivada (el caller cae a su logo por defecto
     * actual en cualquiera de esos casos). `url` es la URL de Cloudinary ya lista para
     * usar tal cual (frontend, o como valor de `logo_path` en UsuarioResource).
     *
     * Para incrustar el logo en un documento generado server-side (PDF/Excel) usar
     * {@see resolverRutaLocalPorCorreo} en su lugar — TCPDF/PhpSpreadsheet necesitan una
     * ruta de archivo local, no una URL remota.
     *
     * @return array{url: ?string, nombre: ?string, color: ?string}
     */
    public function resolverPorCorreo(?string $correo): array
    {
        $sinMatch = ['url' => null, 'nombre' => null, 'color' => null];

        $dominio = $this->dominioDeCorreo($correo);
        if (!$dominio) {
            return $sinMatch;
        }

        $marca = MarcaDominio::where('dominio', $dominio)->where('activo', true)->first();

        if (!$marca) {
            return $sinMatch;
        }

        return ['url' => $marca->logo_path, 'nombre' => $marca->nombre, 'color' => $marca->color];
    }

    /**
     * Descarga a un archivo temporal local el logo resuelto para este correo — is_file()
     * (PazYSalvoPdfService::resolveImage, HorarioExcelService::agregarHoja) no verifica
     * rutas http(s) de forma confiable, así que los consumidores que arman documentos
     * necesitan un path real en disco, no la URL de Cloudinary. Null si no hay marca
     * configurada para el dominio o si la descarga falla (el caller cae a su logo por
     * defecto en ambos casos, igual que con `resolverPorCorreo`).
     */
    public function resolverRutaLocalPorCorreo(?string $correo): ?string
    {
        $url = $this->resolverPorCorreo($correo)['url'];
        if (!$url) {
            return null;
        }

        $contenido = @file_get_contents($url);
        if ($contenido === false) {
            return null;
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'png';
        $temporal = tempnam(sys_get_temp_dir(), 'marca').'.'.$extension;
        file_put_contents($temporal, $contenido);

        return $temporal;
    }

    /**
     * Dominio de correo normalizado (minúsculas), o null si no hay correo o no trae "@" —
     * mismo criterio que resolverPorCorreo, expuesto para consumidores que solo necesitan
     * el dominio en sí, no la marca asociada (ver LogDominioMiddleware).
     */
    public function dominioDeCorreo(?string $correo): ?string
    {
        if (!$correo || !str_contains($correo, '@')) {
            return null;
        }

        return $this->normalizarDominio($correo);
    }

    /** Acepta tanto un correo completo ("user@dominio.com") como un dominio suelto. */
    private function normalizarDominio(string $correoODominio): string
    {
        $partes = explode('@', trim($correoODominio));

        return mb_strtolower(end($partes));
    }

    private function validarLogo(UploadedFile $logo): ?string
    {
        if ($logo->getSize() > self::TAMANO_MAXIMO) {
            return 'El logo excede 5MB.';
        }

        $extension = strtolower($logo->getClientOriginalExtension());

        if (!in_array($extension, self::EXTENSIONES_PERMITIDAS, true)) {
            return 'Formato de imagen no permitido (usa jpg, png o webp).';
        }

        return null;
    }

    /** @return ?array{url: string, public_id: string} */
    private function subirLogo(UploadedFile $logo): ?array
    {
        $resultado = $this->cloudinaryService->uploadFile($logo, self::CARPETA);

        if ($resultado['error']) {
            return null;
        }

        return ['url' => $resultado['data']['url'], 'public_id' => $resultado['data']['public_id']];
    }
}
