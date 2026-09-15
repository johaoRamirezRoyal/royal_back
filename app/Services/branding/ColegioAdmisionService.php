<?php

namespace App\Services\branding;

use App\Models\Branding\ColegioAdmision;
use App\Services\AdminManagement\BasesDatosService;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\Service;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Colegio identificado por el `slug` en la URL pública de admisiones
 * (`/{slug}/admissions`, ver ResolveColegioAdmision) — resuelve tanto a qué connection de
 * base de datos debe apuntar la petición como a la marca (nombre/color/logo) a mostrar en
 * el frontend. Mismo patrón que MarcaDominioService (dominio de correo -> marca), pero la
 * clave es un slug de URL, no un dominio, y además guarda la connection destino.
 */
class ColegioAdmisionService extends Service
{
    private const CARPETA = 'colegios-admision';

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
                'message' => 'Colegios obtenidos correctamente.',
                'data' => ColegioAdmision::orderBy('nombre')->get(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al listar los colegios de admisión');

            return ['error' => true, 'message' => 'Error en el servidor al listar los colegios.', 'data' => []];
        }
    }

    public function crear(array $datos, ?UploadedFile $logo = null, ?UploadedFile $logoSecundario = null): array
    {
        try {
            $errorConnection = $this->validarConnection($datos['connection']);
            if ($errorConnection) {
                return ['error' => true, 'message' => $errorConnection, 'data' => []];
            }

            if ($logo) {
                $errorValidacion = $this->validarLogo($logo);
                if ($errorValidacion) {
                    return ['error' => true, 'message' => $errorValidacion, 'data' => []];
                }
            }

            if ($logoSecundario) {
                $errorValidacion = $this->validarLogo($logoSecundario);
                if ($errorValidacion) {
                    return ['error' => true, 'message' => $errorValidacion, 'data' => []];
                }
            }

            $slug = $this->normalizarSlug($datos['slug']);

            if (ColegioAdmision::where('slug', $slug)->exists()) {
                return ['error' => true, 'message' => 'Ya existe un colegio configurado con ese slug.', 'data' => []];
            }

            $subido = $logo ? $this->subirLogo($logo) : null;
            if ($logo && !$subido) {
                return ['error' => true, 'message' => 'Error al subir el logo a Cloudinary.', 'data' => []];
            }

            $subidoSecundario = $logoSecundario ? $this->subirLogo($logoSecundario) : null;
            if ($logoSecundario && !$subidoSecundario) {
                return ['error' => true, 'message' => 'Error al subir el logotipo secundario a Cloudinary.', 'data' => []];
            }

            $colegio = ColegioAdmision::create([
                'slug' => $slug,
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?? null,
                'color' => empty($datos['color']) ? null : $datos['color'],
                'logo_path' => $subido['url'] ?? null,
                'logo_public_id' => $subido['public_id'] ?? null,
                'logo_secundario_path' => $subidoSecundario['url'] ?? null,
                'logo_secundario_public_id' => $subidoSecundario['public_id'] ?? null,
                'connection' => $datos['connection'],
                'tipo_calendario' => $datos['tipo_calendario'] ?? 'B',
                'activo' => true,
            ]);

            return ['error' => false, 'message' => 'Colegio creado correctamente.', 'data' => $colegio];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear el colegio de admisión');

            return ['error' => true, 'message' => 'Error en el servidor al crear el colegio.', 'data' => []];
        }
    }

    public function actualizar(int $id, array $datos, ?UploadedFile $logo = null, ?UploadedFile $logoSecundario = null): array
    {
        try {
            $colegio = ColegioAdmision::find($id);

            if (!$colegio) {
                return ['error' => true, 'message' => 'El colegio no existe.', 'data' => []];
            }

            if (isset($datos['connection'])) {
                $errorConnection = $this->validarConnection($datos['connection']);
                if ($errorConnection) {
                    return ['error' => true, 'message' => $errorConnection, 'data' => []];
                }
            }

            if ($logo) {
                $errorValidacion = $this->validarLogo($logo);
                if ($errorValidacion) {
                    return ['error' => true, 'message' => $errorValidacion, 'data' => []];
                }
            }

            if ($logoSecundario) {
                $errorValidacion = $this->validarLogo($logoSecundario);
                if ($errorValidacion) {
                    return ['error' => true, 'message' => $errorValidacion, 'data' => []];
                }
            }

            $slug = isset($datos['slug']) ? $this->normalizarSlug($datos['slug']) : $colegio->slug;

            if ($slug !== $colegio->slug && ColegioAdmision::where('slug', $slug)->exists()) {
                return ['error' => true, 'message' => 'Ya existe un colegio configurado con ese slug.', 'data' => []];
            }

            $publicIdAnterior = $colegio->logo_public_id;
            $publicIdSecundarioAnterior = $colegio->logo_secundario_public_id;

            $colegio->slug = $slug;
            $colegio->nombre = $datos['nombre'] ?? $colegio->nombre;
            $colegio->descripcion = array_key_exists('descripcion', $datos) ? ($datos['descripcion'] ?: null) : $colegio->descripcion;
            $colegio->color = array_key_exists('color', $datos) ? (empty($datos['color']) ? null : $datos['color']) : $colegio->color;
            $colegio->connection = $datos['connection'] ?? $colegio->connection;
            $colegio->tipo_calendario = $datos['tipo_calendario'] ?? $colegio->tipo_calendario;

            if ($logo) {
                $subido = $this->subirLogo($logo);
                if (!$subido) {
                    return ['error' => true, 'message' => 'Error al subir el logo a Cloudinary.', 'data' => []];
                }
                $colegio->logo_path = $subido['url'];
                $colegio->logo_public_id = $subido['public_id'];
            }

            if ($logoSecundario) {
                $subidoSecundario = $this->subirLogo($logoSecundario);
                if (!$subidoSecundario) {
                    return ['error' => true, 'message' => 'Error al subir el logotipo secundario a Cloudinary.', 'data' => []];
                }
                $colegio->logo_secundario_path = $subidoSecundario['url'];
                $colegio->logo_secundario_public_id = $subidoSecundario['public_id'];
            }

            $colegio->save();

            if ($logo && $publicIdAnterior && $publicIdAnterior !== $colegio->logo_public_id) {
                $this->cloudinaryService->deleteFile($publicIdAnterior, 'image');
            }

            if ($logoSecundario && $publicIdSecundarioAnterior && $publicIdSecundarioAnterior !== $colegio->logo_secundario_public_id) {
                $this->cloudinaryService->deleteFile($publicIdSecundarioAnterior, 'image');
            }

            return ['error' => false, 'message' => 'Colegio actualizado correctamente.', 'data' => $colegio];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el colegio de admisión');

            return ['error' => true, 'message' => 'Error en el servidor al actualizar el colegio.', 'data' => []];
        }
    }

    public function cambiarEstado(array $ids, bool $estado): array
    {
        try {
            $actualizados = ColegioAdmision::whereIn('id', $ids)->update(['activo' => $estado]);

            return [
                'error' => false,
                'message' => $estado ? 'Colegio(s) habilitado(s) correctamente.' : 'Colegio(s) deshabilitado(s) correctamente.',
                'data' => $actualizados,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al cambiar el estado del colegio de admisión');

            return ['error' => true, 'message' => 'Error en el servidor al cambiar el estado.', 'data' => []];
        }
    }

    public function eliminar(array $ids): array
    {
        try {
            $colegios = ColegioAdmision::whereIn('id', $ids)->get();

            if ($colegios->isEmpty()) {
                return ['error' => true, 'message' => 'No se encontraron colegios para eliminar.', 'data' => []];
            }

            foreach ($colegios as $colegio) {
                if ($colegio->logo_public_id) {
                    $this->cloudinaryService->deleteFile($colegio->logo_public_id, 'image');
                }
                if ($colegio->logo_secundario_public_id) {
                    $this->cloudinaryService->deleteFile($colegio->logo_secundario_public_id, 'image');
                }
            }

            ColegioAdmision::whereIn('id', $ids)->delete();

            return ['error' => false, 'message' => 'Colegio(s) eliminado(s) correctamente.', 'data' => []];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el colegio de admisión');

            return ['error' => true, 'message' => 'Error en el servidor al eliminar el colegio.', 'data' => []];
        }
    }

    /** Activo únicamente — un slug inactivo se trata igual que uno inexistente en todos
     * los consumidores (ResolveColegioAdmision, branding público). */
    public function resolverPorSlug(?string $slug): ?ColegioAdmision
    {
        if (!$slug) {
            return null;
        }

        return ColegioAdmision::where('slug', $this->normalizarSlug($slug))->where('activo', true)->first();
    }

    /** @return array{nombre: ?string, descripcion: ?string, color: ?string, logo: ?string, logo_secundario: ?string, tipo_calendario: string} */
    public function resolverBrandingPorSlug(?string $slug): ?array
    {
        $colegio = $this->resolverPorSlug($slug);

        if (!$colegio) {
            return null;
        }

        return [
            'nombre' => $colegio->nombre,
            'descripcion' => $colegio->descripcion,
            'color' => $colegio->color,
            'logo' => $colegio->logo_path,
            // Opcional, sin genérico de respaldo (a diferencia de `logo`) — se muestra en
            // la esquina superior derecha de la página pública de admisiones, ver
            // Inscripciones.tsx. null si el colegio no subió uno.
            'logo_secundario' => $colegio->logo_secundario_path,
            // 'A' (1 feb-30 nov, mismo año) | 'B' (1 ago-30 jun del año siguiente) — mismos
            // códigos que ConfiguracionAcademica::tipo_calendario en la base operativa de
            // cada colegio (ver AnioEscolarServices), usado acá solo para que el frontend
            // calcule el año escolar a mostrar en las pantallas públicas de admisiones
            // (ver AdmissionsTenantProvider), no para abrir/cerrar el año escolar real.
            'tipo_calendario' => $colegio->tipo_calendario,
        ];
    }

    private function normalizarSlug(string $slug): string
    {
        return Str::slug(trim($slug));
    }

    private function validarConnection(string $connection): ?string
    {
        return BasesDatosService::esConnectionValida($connection) ? null : 'Esa base de datos no existe.';
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
