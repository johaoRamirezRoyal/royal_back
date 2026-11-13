<?php

namespace App\Services\branding;

use App\Models\Branding\ColegioAdmision;
use App\Services\AdminManagement\BasesDatosService;
use App\Services\Service;
use Exception;
use Illuminate\Support\Str;

/**
 * Colegio identificado por el `slug` de sus URLs públicas (`/{slug}/admissions`,
 * `/{slug}/padres`, ver ResolveColegioAdmision): a qué connection apunta la petición y qué
 * marca (`marcas`: nombre, lema, color, logos, imagen de fondo) muestra el frontend. La
 * identidad se administra en MarcaService; aquí solo se elige la marca.
 */
class ColegioAdmisionService extends Service
{
    public function listar(): array
    {
        try {
            return [
                'error' => false,
                'message' => 'Colegios obtenidos correctamente.',
                'data' => ColegioAdmision::with('marca:id,nombre,logo_path,activo')->orderBy('slug')->get(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al listar los colegios');

            return ['error' => true, 'message' => 'Error en el servidor al listar los colegios.', 'data' => []];
        }
    }

    /** @param array{slug: string, connection: string, id_marca: int|string, tipo_calendario?: ?string} $datos */
    public function guardar(?int $id, array $datos): array
    {
        try {
            $colegio = $id ? ColegioAdmision::find($id) : new ColegioAdmision;
            if (! $colegio) {
                return ['error' => true, 'message' => 'El colegio no existe.', 'data' => []];
            }

            if (! BasesDatosService::esConnectionValida($datos['connection'])) {
                return ['error' => true, 'message' => 'Esa base de datos no existe.', 'data' => []];
            }

            $slug = $this->normalizarSlug($datos['slug']);
            if (ColegioAdmision::where('slug', $slug)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists()) {
                return ['error' => true, 'message' => 'Ya existe un colegio configurado con ese slug.', 'data' => []];
            }

            $colegio->fill([
                'slug' => $slug,
                'connection' => $datos['connection'],
                'id_marca' => (int) $datos['id_marca'],
                'tipo_calendario' => ($datos['tipo_calendario'] ?? null) ?: ($colegio->tipo_calendario ?? 'B'),
            ])->save();

            return [
                'error' => false,
                'message' => $id ? 'Colegio actualizado correctamente.' : 'Colegio creado correctamente.',
                'data' => $colegio->load('marca:id,nombre,logo_path,activo'),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al guardar el colegio');

            return ['error' => true, 'message' => 'Error en el servidor al guardar el colegio.', 'data' => []];
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
            $this->sendError($e, 'Error al cambiar el estado del colegio');

            return ['error' => true, 'message' => 'Error en el servidor al cambiar el estado.', 'data' => []];
        }
    }

    /** Solo borra el colegio: sus imágenes son de la marca, que puede seguir en uso. */
    public function eliminar(array $ids): array
    {
        try {
            $eliminados = ColegioAdmision::whereIn('id', $ids)->delete();

            if (! $eliminados) {
                return ['error' => true, 'message' => 'No se encontraron colegios para eliminar.', 'data' => []];
            }

            return ['error' => false, 'message' => 'Colegio(s) eliminado(s) correctamente.', 'data' => []];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el colegio');

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

    /** Como resolverPorSlug, pero solo si su base tiene usuarios (login y recuperar contraseña
     * por colegio del portal para padres): null si el slug no sirve para iniciar sesión. */
    public function colegioConUsuarios(?string $slug): ?ColegioAdmision
    {
        $colegio = $this->resolverPorSlug($slug);

        return $colegio && in_array($colegio->connection, BasesDatosService::connectionsConUsuarios(), true) ? $colegio : null;
    }

    /** @return array{nombre: ?string, descripcion: ?string, color: ?string, logo: ?string, imagen: ?string, logo_secundario: ?string, tipo_calendario: string} */
    public function resolverBrandingPorSlug(?string $slug): ?array
    {
        $colegio = $this->resolverPorSlug($slug);

        if (!$colegio) {
            return null;
        }

        // Marca inactiva o sin marca: el colegio sigue resolviendo, con la marca genérica de OMNIA.
        $marca = $colegio->marca?->activo ? $colegio->marca : null;

        return [
            'nombre' => $marca?->nombre,
            'descripcion' => $marca?->descripcion,
            'color' => $marca?->color,
            'logo' => $marca?->logo_path,
            // Imagen de fondo (friso del login del portal para padres).
            'imagen' => $marca?->imagen_fondo_path,
            // Opcional, sin genérico de respaldo (a diferencia de `logo`) — se muestra en
            // la esquina superior derecha de la página pública de admisiones, ver
            // Inscripciones.tsx.
            'logo_secundario' => $marca?->logo_secundario_path,
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
}
