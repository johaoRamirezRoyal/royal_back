<?php

namespace App\Services\DocumentosVarios;

use App\Models\DocumentosVarios\DocumentoVario;
use App\Services\FileStorageService;
use App\Services\Service;
use Exception;
use Illuminate\Http\UploadedFile;

class DocumentosVariosService extends Service
{
    public function __construct(
        private FileStorageService $fileStorageService
    ) {}

    public function obtenerDocumentosPorUsuario(int $id_usuario): array
    {
        try {
            $documentos = DocumentoVario::where('id_user', $id_usuario)
                ->orderByDesc('fechareg')
                ->get();

            if ($documentos->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron documentos para este usuario.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Documentos obtenidos correctamente.',
                'data' => $documentos->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener los documentos del usuario.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener los documentos.',
                'data' => []
            ];
        }
    }

    public function obtenerDocumentosPorTipo(string $tipo_doc, ?int $id_usuario = null): array
    {
        try {
            $query = DocumentoVario::where('tipo_doc', $tipo_doc);

            if ($id_usuario) {
                $query->where('id_user', $id_usuario);
            }

            $documentos = $query->orderByDesc('fechareg')->get();

            return [
                'error' => false,
                'message' => 'Documentos obtenidos correctamente.',
                'data' => $documentos->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener los documentos por tipo.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener los documentos.',
                'data' => []
            ];
        }
    }

    public function crearDocumento(array $data, UploadedFile $archivo): array
    {
        try {
            $resultado = $this->fileStorageService->uploadFile($archivo, 'documentos-varios');
            $data['nombre_doc'] = $resultado['ruta'];
            $documento = DocumentoVario::create($data);

            return [
                'error' => false,
                'message' => 'Documento creado correctamente.',
                'data' => $documento->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear el documento.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear el documento.',
                'data' => []
            ];
        }
    }

    public function actualizarDocumento(int $id, array $data, ?UploadedFile $archivo = null): array
    {
        try {
            $documento = DocumentoVario::find($id);

            if (!$documento) {
                return [
                    'error' => true,
                    'message' => 'Documento no encontrado.',
                    'data' => []
                ];
            }

            if ($archivo) {
                $this->fileStorageService->eliminar($documento->nombre_doc);
                $data['nombre_doc'] = $this->fileStorageService->uploadFile($archivo, 'documentos-varios')['ruta'];
            }

            $documento->update($data);

            return [
                'error' => false,
                'message' => 'Documento actualizado correctamente.',
                'data' => $documento->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el documento.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el documento.',
                'data' => []
            ];
        }
    }

    public function eliminarDocumento(int $id): array
    {
        try {
            $documento = DocumentoVario::find($id);

            if (!$documento) {
                return [
                    'error' => true,
                    'message' => 'Documento no encontrado.',
                    'data' => []
                ];
            }

            if ($documento->nombre_doc) {
                $this->fileStorageService->eliminar($documento->nombre_doc);
            }

            $documento->delete();

            return [
                'error' => false,
                'message' => 'Documento eliminado correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el documento.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar el documento.',
                'data' => []
            ];
        }
    }

    public function eliminarDocumentosPorUsuario(int $id_usuario): array
    {
        try {
            $documentos = DocumentoVario::where('id_user', $id_usuario)->get();

            foreach ($documentos as $doc) {
                if ($doc->nombre_doc) {
                    $this->fileStorageService->eliminar($doc->nombre_doc);
                }
            }

            $eliminados = $documentos->count();
            DocumentoVario::where('id_user', $id_usuario)->delete();

            return [
                'error' => false,
                'message' => "$eliminados documento(s) eliminado(s) correctamente.",
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar los documentos del usuario.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar los documentos.',
                'data' => []
            ];
        }
    }

    public function eliminarDocumentosPorTipo(string $tipo_doc, ?int $id_usuario = null): array
    {
        try {
            $query = DocumentoVario::where('tipo_doc', $tipo_doc);

            if ($id_usuario) {
                $query->where('id_user', $id_usuario);
            }

            $documentos = $query->get();

            foreach ($documentos as $doc) {
                if ($doc->nombre_doc) {
                    $this->fileStorageService->eliminar($doc->nombre_doc);
                }
            }

            $eliminados = $documentos->count();
            $query->delete();

            return [
                'error' => false,
                'message' => "$eliminados documento(s) eliminado(s) correctamente.",
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar los documentos por tipo.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar los documentos.',
                'data' => []
            ];
        }
    }

    public function contarDocumentosPorTipo(int $id_usuario): array
    {
        try {
            $conteo = DocumentoVario::where('id_user', $id_usuario)
                ->selectRaw('tipo_doc, count(*) as total')
                ->groupBy('tipo_doc')
                ->get();

            return [
                'error' => false,
                'message' => 'Conteo de documentos obtenido correctamente.',
                'data' => $conteo->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al contar los documentos por tipo.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al contar los documentos.',
                'data' => []
            ];
        }
    }
}
