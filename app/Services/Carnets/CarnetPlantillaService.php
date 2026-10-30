<?php

namespace App\Services\Carnets;

use App\Models\Carnets\CarnetPlantilla;
use App\Services\FileStorageService;
use App\Services\Noticias\CumpleanosImagen;
use App\Services\Service;
use Illuminate\Support\Facades\DB;

/**
 * Plantillas de carnet, con el mismo modelo que las de cumpleaños (CumpleanosService): imagen
 * de fondo + `elementos` en fracciones de la imagen. A diferencia de cumpleaños, el servidor no
 * compone nada: el frontend dibuja el carnet (SVG) con estas medidas, en el editor, al imprimir
 * y en el carnet digital. La plantilla activa de cada tipo es la que se usa; una por tipo.
 */
class CarnetPlantillaService extends Service
{
    public const TIPOS = ['estudiante', 'trabajador'];

    /** Textos del carnet, en el orden en que se dibujan. */
    public const TEXTOS = ['nombre', 'detalle', 'documento', 'vigencia'];

    /** Proporción del carnet vertical CR80 (54 × 85,6 mm); la imagen puede desviarse un 3 %. */
    public const PROPORCION = 54 / 85.6;

    private const TEXTO_BASE = [
        'x' => 0.5, 'tamano' => 0.05, 'fuente' => 'poppins', 'peso' => 400, 'estilo' => 'normal',
        'color' => '#0f172a', 'alineacion' => 'center', 'ancho_max' => 0.9, 'mayusculas' => false,
        'formato' => 'completo', 'visible' => true, 'prefijo' => '', 'ajustar' => false,
    ];

    public const ELEMENTOS_DEFECTO = [
        'foto' => [
            'x' => 0.5, 'y' => 0.38, 'ancho' => 0.46, 'alto' => 0.58, 'forma' => 'rectangulo', 'radio' => 0.06,
            'borde' => 0.01, 'color_borde' => '#0f172a', 'ajuste' => 'cover', 'capa' => 'encima',
        ],
        'nombre' => ['y' => 0.68, 'tamano' => 0.07, 'peso' => 700],
        'detalle' => ['y' => 0.745, 'color' => '#55627a'],
        'documento' => ['y' => 0.915, 'tamano' => 0.042],
        'vigencia' => ['y' => 0.965, 'tamano' => 0.04, 'prefijo' => 'Vigencia '],
        'barras' => ['x' => 0.5, 'y' => 0.84, 'ancho' => 0.8, 'alto' => 0.13, 'color' => '#000000', 'fondo' => true, 'visible' => true],
    ];

    public function __construct(
        private FileStorageService $archivos,
        private CumpleanosImagen $imagen,
    ) {
    }

    /** Completa con los valores por defecto y acota cada valor (`elementos` llega del cliente). */
    public static function normalizar(?array $elementos): array
    {
        $acotar = fn ($v, float $min, float $max) => max($min, min($max, (float) $v));
        $resultado = [];

        foreach (self::TEXTOS as $clave) {
            $t = array_merge(self::TEXTO_BASE, self::ELEMENTOS_DEFECTO[$clave], (array) ($elementos[$clave] ?? []));
            // Mismas reglas (fuente, color, alineación, tamaños) que el nombre de cumpleaños.
            $resultado[$clave] = CumpleanosImagen::normalizar(['nombre' => $t])['nombre'] + [
                'visible' => (bool) $t['visible'],
                'prefijo' => mb_substr(is_string($t['prefijo']) ? $t['prefijo'] : '', 0, 30),
                'ajustar' => (bool) $t['ajustar'],
            ];
            $resultado[$clave]['tamano'] = $acotar($t['tamano'], 0.01, 0.3);
        }

        $foto = array_merge(self::ELEMENTOS_DEFECTO['foto'], (array) ($elementos['foto'] ?? []));
        $resultado['foto'] = CumpleanosImagen::normalizar(['foto' => $foto])['foto'];

        $b = array_merge(self::ELEMENTOS_DEFECTO['barras'], (array) ($elementos['barras'] ?? []));
        $resultado['barras'] = [
            'x' => $acotar($b['x'], 0, 1),
            'y' => $acotar($b['y'], 0, 1),
            'ancho' => $acotar($b['ancho'], 0.1, 1),
            'alto' => $acotar($b['alto'], 0.02, 0.6),
            'color' => is_string($b['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $b['color']) ? strtolower($b['color']) : '#000000',
            'fondo' => (bool) $b['fondo'],
            'visible' => (bool) $b['visible'],
        ];

        return $resultado;
    }

    public static function proporcionValida(int $ancho, int $alto): bool
    {
        return $alto > 0 && abs($ancho / $alto - self::PROPORCION) / self::PROPORCION <= 0.03;
    }

    public function serializar(CarnetPlantilla $p): array
    {
        return [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'descripcion' => $p->descripcion,
            'tipo' => $p->tipo,
            'activo' => $p->activo,
            'imagen' => $p->imagen,
            'ancho' => $p->ancho,
            'alto' => $p->alto,
            'transparente' => $p->transparente,
            'elementos' => self::normalizar($p->elementos),
            'updated_at' => $p->updated_at?->format('Y-m-d H:i:s'),
        ];
    }

    public function listar(): array
    {
        $plantillas = CarnetPlantilla::orderBy('tipo')->orderByDesc('activo')->orderBy('nombre')->get();

        return ['error' => false, 'message' => 'Plantillas obtenidas', 'data' => $plantillas->map(fn ($p) => $this->serializar($p))];
    }

    public function obtener(int $id): array
    {
        $p = CarnetPlantilla::find($id);

        return $p
            ? ['error' => false, 'message' => 'Plantilla obtenida', 'data' => $this->serializar($p)]
            : ['error' => true, 'message' => 'La plantilla no existe', 'status' => 404];
    }

    /** Crea o actualiza. Activarla desactiva las demás de su tipo (una en uso por tipo). */
    public function guardar(?int $id, array $datos, int $idLog): array
    {
        $p = $id ? CarnetPlantilla::find($id) : new CarnetPlantilla();
        if (!$p) {
            return ['error' => true, 'message' => 'La plantilla no existe', 'status' => 404];
        }

        $imagen = $datos['imagen'] ?? null;
        $medidas = null;
        if ($imagen && $imagen !== $p->imagen) {
            if (!$this->archivos->rutaLocal($imagen)) {
                return ['error' => true, 'message' => 'La imagen de la plantilla no existe', 'status' => 422];
            }
            $medidas = $this->inspeccionarImagen($imagen);
        }

        $activo = (bool) ($datos['activo'] ?? false) && $imagen;

        DB::transaction(function () use ($p, $datos, $imagen, $medidas, $activo, $idLog) {
            if ($activo) {
                CarnetPlantilla::where('tipo', $datos['tipo'])->where('id', '!=', $p->id ?? 0)->update(['activo' => false]);
            }

            $p->fill([
                'nombre' => $datos['nombre'],
                'descripcion' => $datos['descripcion'] ?? null,
                'tipo' => $datos['tipo'],
                'activo' => $activo,
                'imagen' => $imagen,
                'elementos' => self::normalizar($datos['elementos'] ?? null),
                'id_log' => $idLog,
            ] + ($imagen ? ($medidas ?? []) : ['ancho' => null, 'alto' => null, 'transparente' => false]));
            $p->save();
        });

        return ['error' => false, 'message' => 'Plantilla guardada', 'data' => $this->serializar($p->fresh())];
    }

    public function cambiarEstado(int $id, bool $activo): array
    {
        $p = CarnetPlantilla::find($id);
        if (!$p) {
            return ['error' => true, 'message' => 'La plantilla no existe', 'status' => 404];
        }
        if ($activo && !$p->imagen) {
            return ['error' => true, 'message' => 'Sube la imagen de la plantilla antes de activarla', 'status' => 422];
        }

        DB::transaction(function () use ($p, $activo) {
            if ($activo) {
                CarnetPlantilla::where('tipo', $p->tipo)->where('id', '!=', $p->id)->update(['activo' => false]);
            }
            $p->update(['activo' => $activo]);
        });

        return ['error' => false, 'message' => $activo ? 'Plantilla en uso' : 'Plantilla desactivada'];
    }

    public function duplicar(int $id, int $idLog): array
    {
        $p = CarnetPlantilla::find($id);
        if (!$p) {
            return ['error' => true, 'message' => 'La plantilla no existe', 'status' => 404];
        }

        $copia = $p->replicate();
        $copia->fill(['nombre' => mb_substr("Copia de {$p->nombre}", 0, 120), 'activo' => false, 'id_log' => $idLog])->save();

        return ['error' => false, 'message' => 'Plantilla duplicada', 'data' => ['id' => $copia->id]];
    }

    // ponytail: la imagen queda en el disco (puede compartirla una copia); limpiar huérfanas si se acumulan.
    public function eliminar(int $id): array
    {
        $borradas = CarnetPlantilla::where('id', $id)->delete();

        return $borradas
            ? ['error' => false, 'message' => 'Plantilla eliminada']
            : ['error' => true, 'message' => 'La plantilla no existe', 'status' => 404];
    }

    public function inspeccionarImagen(string $ruta): array
    {
        return $this->imagen->inspeccionar($this->archivos->rutaLocal($ruta) ?? '');
    }

    /** Plantillas en uso por tipo (`['estudiante' => [...]|null, 'trabajador' => ...]`), para dibujar los carnets. */
    public function enUso(): array
    {
        $activas = CarnetPlantilla::where('activo', true)->whereNotNull('imagen')->get()->keyBy('tipo');

        return collect(self::TIPOS)->mapWithKeys(fn ($tipo) => [
            $tipo => ($p = $activas->get($tipo)) ? [
                'id' => $p->id,
                'imagen' => $p->imagen,
                'ancho' => $p->ancho,
                'alto' => $p->alto,
                'elementos' => self::normalizar($p->elementos),
            ] : null,
        ])->all();
    }
}
