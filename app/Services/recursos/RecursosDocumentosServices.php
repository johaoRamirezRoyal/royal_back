<?php

namespace App\Services\recursos;

use App\Models\Recursos\ProcesoDocumento;
use App\Models\Recursos\RenovacionDocumento;
use App\Services\FileStorageService;
use Exception;
use Illuminate\Http\UploadedFile;

class RecursosDocumentosServices
{
    /** Catálogo fijo (ver legacy vistas/modulos/recursos/agregarRenovacion.php) — sin tabla propia. */
    public const CATEGORIAS = [
        1 => 'Virtual',
        2 => 'Físico',
        3 => 'Digital',
    ];

    public const PROXIMAS_FECHAS = [
        'mensual' => 'Mensual',
        'trimestral' => 'Trimestral',
        'semestral' => 'Semestral',
        'anual' => 'Anual',
        'bianual' => 'Cada 2 años',
        'quinquenal' => 'Cada 5 años',
    ];

    public const TIEMPOS_RETENCION = ['Hasta nueva retención', 'Tiempo Establecido'];

    public function __construct(private FileStorageService $fileStorage)
    {
    }

    public function procesos(): array
    {
        try {
            $procesos = ProcesoDocumento::where('activo', 1)->orderBy('nombre')->get();

            return ['error' => false, 'message' => 'Procesos obtenidos correctamente', 'data' => $procesos];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function procesosConCantidades(): array
    {
        try {
            $procesos = ProcesoDocumento::where('activo', 1)
                ->withCount(['documentos' => fn ($q) => $q->where('activo', 1)])
                ->orderBy('nombre')
                ->get();

            return ['error' => false, 'message' => 'Cantidades obtenidas correctamente', 'data' => $procesos];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function listar(array $filtros, int $perPage): array
    {
        try {
            $query = RenovacionDocumento::with('proceso:id,nombre')
                ->where('activo', 1)
                ->orderByDesc('id');

            if (!empty($filtros['tipo_proceso'])) {
                $query->where('tipo_proceso', (int) $filtros['tipo_proceso']);
            }

            if (!empty($filtros['s'])) {
                $query->where('nom_documento', 'like', "%{$filtros['s']}%");
            }

            $paginator = $query->paginate($perPage);
            $paginator->getCollection()->each(fn ($d) => $this->adjuntarUrl($d));

            return ['error' => false, 'message' => 'Documentos obtenidos correctamente', 'data' => $paginator];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crear(array $data, int $idUser, int $idLog, ?UploadedFile $archivo): array
    {
        try {
            $evidencia = null;

            if ($archivo) {
                $subido = $this->fileStorage->uploadFile($archivo, 'recursos/documentos');
                $evidencia = $subido['ruta'];
            }

            $documento = RenovacionDocumento::create([
                'tipo_proceso' => $data['tipo_proceso'],
                'nom_documento' => $data['nom_documento'],
                'version_doc' => $data['version_doc'],
                'fecha_vigencia' => $data['fecha_vigencia'],
                'fecha_revision' => $data['fecha_revision'] ?? null,
                'categ_doc' => $data['categ_doc'],
                'gestion_cambio' => $data['gestion_cambio'] ?? null,
                'url_archivo' => $data['url_archivo'] ?? null,
                'evidencia' => $evidencia,
                'id_user' => $idUser,
                'id_log' => $idLog,
                'proxima_fecha' => $data['proxima_fecha'] ?? null,
                'tiempo_retencion' => $data['tiempo_retencion'] ?? null,
                'activo' => 1,
            ]);

            $this->adjuntarUrl($documento);

            return ['error' => false, 'message' => 'Documento registrado correctamente', 'data' => $documento];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizar(int $id, array $data, int $idLog, ?UploadedFile $archivo): array
    {
        try {
            $documento = RenovacionDocumento::find($id);

            if (!$documento) {
                return ['error' => true, 'message' => 'Documento no encontrado', 'status' => 404];
            }

            $evidencia = $documento->evidencia;

            if ($archivo) {
                $subido = $evidencia
                    ? $this->fileStorage->reemplazar($archivo, $evidencia, 'recursos/documentos')
                    : $this->fileStorage->uploadFile($archivo, 'recursos/documentos');
                $evidencia = $subido['ruta'];
            }

            $documento->update([
                'tipo_proceso' => $data['tipo_proceso'],
                'nom_documento' => $data['nom_documento'],
                'version_doc' => $data['version_doc'],
                'fecha_vigencia' => $data['fecha_vigencia'],
                'fecha_revision' => $data['fecha_revision'] ?? null,
                'categ_doc' => $data['categ_doc'],
                'gestion_cambio' => $data['gestion_cambio'] ?? null,
                'url_archivo' => $data['url_archivo'] ?? null,
                'evidencia' => $evidencia,
                'id_log' => $idLog,
                'proxima_fecha' => $data['proxima_fecha'] ?? null,
                'tiempo_retencion' => $data['tiempo_retencion'] ?? null,
                'fecha_edit' => now(),
            ]);

            $documento = $documento->fresh('proceso');
            $this->adjuntarUrl($documento);

            return ['error' => false, 'message' => 'Documento actualizado correctamente', 'data' => $documento];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function eliminar(int $id, int $idLog): array
    {
        try {
            $documento = RenovacionDocumento::find($id);

            if (!$documento) {
                return ['error' => true, 'message' => 'Documento no encontrado', 'status' => 404];
            }

            $documento->update([
                'activo' => 0,
                'fecha_inactivo' => now(),
                'id_inactiva' => $idLog,
            ]);

            return ['error' => false, 'message' => 'Documento eliminado correctamente', 'data' => []];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Virtual (1): el archivo vive en `url_archivo` (link externo). Físico/Digital: `evidencia` es un archivo subido a este backend. */
    private function adjuntarUrl(RenovacionDocumento $documento): void
    {
        $documento->url_documento = $documento->categ_doc === 1
            ? $documento->url_archivo
            : $this->fileStorage->url($documento->evidencia);
    }
}
