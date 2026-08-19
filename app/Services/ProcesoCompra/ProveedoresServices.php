<?php

namespace App\Services\ProcesoCompra;

use App\Models\ProcesoCompra\Proveedores\ProveedorDetalle;
use App\Models\ProcesoCompra\Proveedores\ProveedorDocumento;
use App\Models\ProcesoCompra\Proveedores\TipoDocumentoProveedor;
use App\Models\Usuarios\Usuario;
use App\Services\FileStorageService;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProveedoresServices
{
    private const PERFIL_PROVEEDOR = 17;

    public function __construct(
        private FileStorageService $fileStorage,
    ) {}

    public function listar(): array
    {
        try {
            $proveedores = ProveedorDetalle::with('usuario:id_user,nombre,correo,user,estado')
                ->orderByDesc('id')
                ->get();

            return ['error' => false, 'message' => 'Proveedores obtenidos correctamente', 'data' => $proveedores];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function ver(int $id): array
    {
        try {
            $proveedor = ProveedorDetalle::with(['usuario', 'documentos.tipoDocumento', 'contactos', 'bancos'])->find($id);

            if (!$proveedor) {
                return ['error' => true, 'message' => 'Proveedor no encontrado', 'status' => 404];
            }

            $proveedor->documentos->each(fn ($doc) => $doc->url_documento = $this->urlDocumento($doc->nombre));

            return ['error' => false, 'message' => 'Proveedor encontrado', 'data' => $proveedor];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crear(array $usuarioData, array $proveedorData, int $idLog): array
    {
        try {
            $usuario = Usuario::create([
                ...$usuarioData,
                'perfil' => self::PERFIL_PROVEEDOR,
                'estado' => 'activo',
                'fechareg' => now(),
            ]);

            // La columna tiene default '0000-00-00' que MySQL strict rechaza si se omite.
            $proveedorData['fecha_ingreso'] = $proveedorData['fecha_ingreso'] ?? null;

            $proveedor = ProveedorDetalle::create([
                ...$proveedorData,
                'id_proveedor' => $usuario->id_user,
                'id_log' => $idLog,
            ]);

            return [
                'error' => false,
                'message' => 'Proveedor creado correctamente',
                'data' => $proveedor->fresh()->load(['usuario', 'documentos', 'contactos', 'bancos']),
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizar(int $id, array $usuarioData, array $proveedorData, int $idLog): array
    {
        try {
            $proveedor = ProveedorDetalle::find($id);

            if (!$proveedor) {
                return ['error' => true, 'message' => 'Proveedor no encontrado', 'status' => 404];
            }

            $proveedor->update([...$proveedorData, 'id_log' => $idLog]);

            if ($usuarioData && $proveedor->id_proveedor) {
                $usuario = Usuario::find($proveedor->id_proveedor);
                $usuario?->update($usuarioData);
            }

            return [
                'error' => false,
                'message' => 'Proveedor actualizado correctamente',
                'data' => $proveedor->fresh()->load(['usuario', 'documentos', 'contactos', 'bancos']),
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function cambiarEstado(int $id, string $estado, int $idLog): array
    {
        try {
            $proveedor = ProveedorDetalle::find($id);

            if (!$proveedor) {
                return ['error' => true, 'message' => 'Proveedor no encontrado', 'status' => 404];
            }

            $usuario = Usuario::find($proveedor->id_proveedor);

            if (!$usuario) {
                return ['error' => true, 'message' => 'Usuario proveedor no encontrado', 'status' => 404];
            }

            $usuario->update([
                'estado' => $estado,
                'fecha_activo' => $estado === 'activo' ? now() : $usuario->fecha_activo,
                'fecha_inactivo' => $estado === 'inactivo' ? now() : null,
            ]);

            return [
                'error' => false,
                'message' => 'Estado del proveedor actualizado correctamente',
                'data' => ['id' => $id, 'estado' => $estado],
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function listarTiposDocumento(): array
    {
        try {
            $tipos = TipoDocumentoProveedor::where('activo', 1)->orderBy('nombre')->get(['id', 'nombre']);

            return ['error' => false, 'message' => 'Tipos de documento obtenidos correctamente', 'data' => $tipos];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function listarDocumentos(int $idProveedor): array
    {
        try {
            $proveedor = ProveedorDetalle::find($idProveedor);

            if (!$proveedor) {
                return ['error' => true, 'message' => 'Proveedor no encontrado', 'status' => 404];
            }

            $documentos = ProveedorDocumento::with('tipoDocumento')
                ->where('id_proveedor', $proveedor->id_proveedor)
                ->orderByDesc('id')
                ->get();

            $documentos->each(fn ($doc) => $doc->url_documento = $this->urlDocumento($doc->nombre));

            return ['error' => false, 'message' => 'Documentos obtenidos correctamente', 'data' => $documentos];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function subirDocumento(int $idProveedor, int $tipoDocumento, int $activo, UploadedFile $archivo, int $idLog): array
    {
        try {
            $proveedor = ProveedorDetalle::find($idProveedor);

            if (!$proveedor) {
                return ['error' => true, 'message' => 'Proveedor no encontrado', 'status' => 404];
            }

            if (!TipoDocumentoProveedor::where('id', $tipoDocumento)->exists()) {
                return ['error' => true, 'message' => 'El tipo de documento no existe', 'status' => 422];
            }

            $archivoGuardado = $this->fileStorage->uploadFile($archivo, 'proveedores/documentos');

            if (empty($archivoGuardado['ruta'])) {
                return ['error' => true, 'message' => 'No se pudo guardar el archivo'];
            }

            ProveedorDocumento::create([
                'id_proveedor' => $proveedor->id_proveedor,
                'nombre' => $archivoGuardado['ruta'],
                'tipo_documento' => $tipoDocumento,
                'activo' => $activo,
                'id_log' => $idLog,
            ]);

            return ['error' => false, 'message' => 'Documento subido correctamente', 'data' => $archivoGuardado];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizarDocumento(int $idDocumento, int $tipoDocumento, int $activo, ?UploadedFile $archivo, int $idLog): array
    {
        try {
            $documento = ProveedorDocumento::find($idDocumento);

            if (!$documento) {
                return ['error' => true, 'message' => 'Documento no encontrado', 'status' => 404];
            }

            if (!TipoDocumentoProveedor::where('id', $tipoDocumento)->exists()) {
                return ['error' => true, 'message' => 'El tipo de documento no existe', 'status' => 422];
            }

            $data = ['tipo_documento' => $tipoDocumento, 'activo' => $activo, 'id_log' => $idLog];
            $archivoGuardado = null;

            if ($archivo) {
                $archivoGuardado = $this->fileStorage->reemplazar($archivo, $documento->nombre, 'proveedores/documentos');

                if (empty($archivoGuardado['ruta'])) {
                    return ['error' => true, 'message' => 'No se pudo reemplazar el archivo'];
                }

                $data['nombre'] = $archivoGuardado['ruta'];
            }

            $documento->update($data);

            return [
                'error' => false,
                'message' => 'Documento actualizado correctamente',
                'data' => $archivoGuardado ?? ['id' => $idDocumento, 'tipo_documento' => $tipoDocumento, 'activo' => $activo],
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function cambiarEstadoDocumento(int $idDocumento, int $activo, int $idLog): array
    {
        try {
            $documento = ProveedorDocumento::find($idDocumento);

            if (!$documento) {
                return ['error' => true, 'message' => 'Documento no encontrado', 'status' => 404];
            }

            $documento->update(['activo' => $activo, 'id_log' => $idLog]);

            return [
                'error' => false,
                'message' => 'Estado del documento actualizado correctamente',
                'data' => ['id' => $idDocumento, 'activo' => $activo],
            ];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function eliminarDocumento(int $idDocumento): array
    {
        try {
            $documento = ProveedorDocumento::find($idDocumento);

            if (!$documento) {
                return ['error' => true, 'message' => 'Documento no encontrado', 'status' => 404];
            }

            if ($documento->nombre) {
                $this->fileStorage->eliminar($documento->nombre);
            }

            $documento->delete();

            return ['error' => false, 'message' => 'Documento eliminado correctamente', 'data' => ['id' => $idDocumento]];
        } catch (Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    private function urlDocumento(?string $nombre): ?string
    {
        if (!$nombre) {
            return null;
        }

        return Storage::disk(config('filesystems.uploads_disk', 'public'))->url($nombre);
    }
}