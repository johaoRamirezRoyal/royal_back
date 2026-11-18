<?php

namespace App\Services\branding;

use App\Models\Branding\Marca;
use App\Models\Branding\MarcaDominio;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\Service;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Marcas (`marcas`): la identidad de cada institución y sus dominios de correo. Los colegios
 * por slug eligen su marca en ColegioAdmisionService. Las imágenes van a Cloudinary.
 */
class MarcaService extends Service
{
    private const CARPETA = 'marcas';

    /** Mismo set que CloudinaryService::validateFile acepta para imágenes. */
    private const EXTENSIONES_PERMITIDAS = ['jpg', 'jpeg', 'png', 'webp'];

    private const TAMANO_MAXIMO = 5 * 1024 * 1024;

    /** Imagen del formulario => columnas en `marcas`. */
    private const IMAGENES = [
        'logo' => ['logo_path', 'logo_public_id', 'El logo'],
        'logo_secundario' => ['logo_secundario_path', 'logo_secundario_public_id', 'El logo secundario'],
        'imagen' => ['imagen_fondo_path', 'imagen_fondo_public_id', 'La imagen de fondo'],
    ];

    public function __construct(private CloudinaryService $cloudinaryService) {}

    public function listar(): array
    {
        try {
            return [
                'error' => false,
                'message' => 'Marcas obtenidas correctamente.',
                'data' => Marca::with('dominios:id,id_marca,dominio')->withCount('colegios')->orderBy('nombre')->get(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al listar las marcas');

            return ['error' => true, 'message' => 'Error en el servidor al listar las marcas.', 'data' => []];
        }
    }

    /** Dominios con el nombre de su marca, para quien solo necesita la lista (banners por colegio). */
    public function listarDominios(): array
    {
        try {
            $dominios = MarcaDominio::with('marca:id,nombre,activo')->orderBy('dominio')->get()
                ->map(fn (MarcaDominio $d) => [
                    'id' => $d->id,
                    'dominio' => $d->dominio,
                    'nombre' => $d->marca?->nombre,
                    'activo' => $d->activo && (bool) $d->marca?->activo,
                ]);

            return ['error' => false, 'message' => 'Dominios obtenidos correctamente.', 'data' => $dominios];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al listar los dominios de las marcas');

            return ['error' => true, 'message' => 'Error en el servidor al listar los dominios.', 'data' => []];
        }
    }

    /**
     * @param  array<string, UploadedFile|null>  $imagenes  logo, logo_secundario, imagen
     * @param  string[]  $quitar  imágenes a borrar (mismas claves)
     */
    public function guardar(?int $id, array $datos, array $dominios, array $imagenes, array $quitar = []): array
    {
        try {
            $marca = $id ? Marca::find($id) : new Marca;
            if (! $marca) {
                return ['error' => true, 'message' => 'La marca no existe.', 'data' => []];
            }

            foreach (self::IMAGENES as $clave => [, , $etiqueta]) {
                if (($imagenes[$clave] ?? null) && ($error = $this->validarImagen($imagenes[$clave], $etiqueta))) {
                    return ['error' => true, 'message' => $error, 'data' => []];
                }
            }

            $dominios = array_values(array_unique(array_filter(array_map([$this, 'normalizarDominio'], $dominios))));
            $ocupado = MarcaDominio::whereIn('dominio', $dominios)->when($id, fn ($q) => $q->where('id_marca', '!=', $id))->value('dominio');
            if ($ocupado) {
                return ['error' => true, 'message' => "El dominio {$ocupado} ya pertenece a otra marca.", 'data' => []];
            }

            $marca->nombre = $datos['nombre'];
            $marca->descripcion = ($datos['descripcion'] ?? null) ?: null;
            $marca->color = ($datos['color'] ?? null) ?: null;

            // Se sube todo antes de tocar la base; los archivos reemplazados se borran al final.
            $aBorrar = [];
            foreach (self::IMAGENES as $clave => [$columnaRuta, $columnaId, $etiqueta]) {
                $archivo = $imagenes[$clave] ?? null;
                if (! $archivo && ! in_array($clave, $quitar, true)) {
                    continue;
                }
                $subido = null;
                if ($archivo && ! ($subido = $this->subir($archivo))) {
                    return ['error' => true, 'message' => "Error al subir {$etiqueta} a Cloudinary.", 'data' => []];
                }
                if ($marca->{$columnaId}) {
                    $aBorrar[] = $marca->{$columnaId};
                }
                $marca->{$columnaRuta} = $subido['url'] ?? null;
                $marca->{$columnaId} = $subido['public_id'] ?? null;
            }

            DB::connection('admin_management')->transaction(function () use ($marca, $dominios) {
                $marca->save();
                $marca->dominios()->whereNotIn('dominio', $dominios)->delete();
                $existentes = $marca->dominios()->pluck('dominio')->all();
                foreach (array_diff($dominios, $existentes) as $dominio) {
                    $marca->dominios()->create(['dominio' => $dominio]);
                }
            });

            foreach ($aBorrar as $publicId) {
                $this->cloudinaryService->deleteFile($publicId, 'image');
            }

            return [
                'error' => false,
                'message' => $id ? 'Marca actualizada correctamente.' : 'Marca creada correctamente.',
                'data' => $marca->load('dominios:id,id_marca,dominio'),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al guardar la marca');

            return ['error' => true, 'message' => 'Error en el servidor al guardar la marca.', 'data' => []];
        }
    }

    public function cambiarEstado(array $ids, bool $estado): array
    {
        try {
            $actualizados = Marca::whereIn('id', $ids)->update(['activo' => $estado]);

            return [
                'error' => false,
                'message' => $estado ? 'Marca(s) habilitada(s) correctamente.' : 'Marca(s) deshabilitada(s) correctamente.',
                'data' => $actualizados,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al cambiar el estado de la marca');

            return ['error' => true, 'message' => 'Error en el servidor al cambiar el estado.', 'data' => []];
        }
    }

    /** Borra la marca, sus dominios (FK en cascada) y sus imágenes; los colegios quedan sin marca. */
    public function eliminar(array $ids): array
    {
        try {
            $marcas = Marca::whereIn('id', $ids)->get();

            if ($marcas->isEmpty()) {
                return ['error' => true, 'message' => 'No se encontraron marcas para eliminar.', 'data' => []];
            }

            Marca::whereIn('id', $ids)->delete();

            // Después de borrar las filas: si Cloudinary falla, solo queda el archivo suelto.
            foreach ($marcas as $marca) {
                foreach (self::IMAGENES as [, $columnaId]) {
                    if ($marca->{$columnaId}) {
                        $this->cloudinaryService->deleteFile($marca->{$columnaId}, 'image');
                    }
                }
            }

            return ['error' => false, 'message' => 'Marca(s) eliminada(s) correctamente.', 'data' => []];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar la marca');

            return ['error' => true, 'message' => 'Error en el servidor al eliminar la marca.', 'data' => []];
        }
    }

    /** Acepta un correo completo ("user@dominio.com") o un dominio suelto. */
    private function normalizarDominio(string $correoODominio): string
    {
        $partes = explode('@', trim($correoODominio));

        return mb_strtolower(trim(end($partes)));
    }

    private function validarImagen(UploadedFile $archivo, string $etiqueta): ?string
    {
        if ($archivo->getSize() > self::TAMANO_MAXIMO) {
            return "{$etiqueta} excede 5MB.";
        }

        if (! in_array(strtolower($archivo->getClientOriginalExtension()), self::EXTENSIONES_PERMITIDAS, true)) {
            return 'Formato de imagen no permitido (usa jpg, png o webp).';
        }

        return null;
    }

    /** @return ?array{url: string, public_id: string} */
    private function subir(UploadedFile $archivo): ?array
    {
        $resultado = $this->cloudinaryService->uploadFile($archivo, self::CARPETA);

        if ($resultado['error']) {
            return null;
        }

        return ['url' => $resultado['data']['url'], 'public_id' => $resultado['data']['public_id']];
    }
}
