<?php

namespace App\Services\HistoriaClinica;

use App\Models\HistoriaClinica\HceCognitivoLenguaje;
use App\Models\HistoriaClinica\HceDesarrolloPsicomotor;
use App\Models\HistoriaClinica\HceEmbarazoParto;
use App\Models\HistoriaClinica\HceEstructuraFamiliar;
use App\Models\HistoriaClinica\HceHermano;
use App\Models\HistoriaClinica\HceHistoriaEscolar;
use App\Models\HistoriaClinica\HceObservacionesFirmas;
use App\Models\HistoriaClinica\HcePsicoafectiva;
use App\Models\HistoriaClinica\HceRemision;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\Service;
use Exception;
use Illuminate\Http\UploadedFile;

class HistoriaClinicaService extends Service
{
    public function __construct(
        private CloudinaryService $cloudinaryService
    ) {}

    // ── Cognitivo Lenguaje ──────────────────────────────────────

    public function verCognitivoLenguaje(int $id_inscripcion): array
    {
        try {
            $registro = HceCognitivoLenguaje::where('id_inscripcion', $id_inscripcion)->first();

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'No se encontró el registro de cognitivo lenguaje para esta inscripción.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Registro obtenido correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener el registro de cognitivo lenguaje.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el registro.',
                'data' => []
            ];
        }
    }

    public function añadirCognitivoLenguaje(array $data): array
    {
        try {
            $registro = HceCognitivoLenguaje::where('id_inscripcion', $data['id_inscripcion'])->first();

            if ($registro) {
                $registro->update($data);

                return [
                    'error' => false,
                    'message' => 'Registro de cognitivo lenguaje actualizado correctamente.',
                    'data' => $registro->fresh()->toArray()
                ];
            }

            $registro = HceCognitivoLenguaje::create($data);

            return [
                'error' => false,
                'message' => 'Registro de cognitivo lenguaje creado correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al guardar el registro de cognitivo lenguaje.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al guardar el registro.',
                'data' => []
            ];
        }
    }

    public function actualizarCognitivoLenguaje(int $id, array $data): array
    {
        try {
            $registro = HceCognitivoLenguaje::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de cognitivo lenguaje no encontrado.',
                    'data' => []
                ];
            }

            $registro->update($data);

            return [
                'error' => false,
                'message' => 'Registro de cognitivo lenguaje actualizado correctamente.',
                'data' => $registro->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el registro de cognitivo lenguaje.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el registro.',
                'data' => []
            ];
        }
    }

    public function eliminarCognitivoLenguaje(int $id): array
    {
        try {
            $registro = HceCognitivoLenguaje::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de cognitivo lenguaje no encontrado.',
                    'data' => []
                ];
            }

            $registro->delete();

            return [
                'error' => false,
                'message' => 'Registro de cognitivo lenguaje eliminado correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el registro de cognitivo lenguaje.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar el registro.',
                'data' => []
            ];
        }
    }

    // ── Desarrollo Psicomotor ───────────────────────────────────

    public function verDesarrolloPsicomotor(int $id_inscripcion): array
    {
        try {
            $registro = HceDesarrolloPsicomotor::where('id_inscripcion', $id_inscripcion)->first();

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'No se encontró el registro de desarrollo psicomotor para esta inscripción.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Registro obtenido correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener el registro de desarrollo psicomotor.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el registro.',
                'data' => []
            ];
        }
    }

    public function añadirDesarrolloPsicomotor(array $data): array
    {
        try {
            $registro = HceDesarrolloPsicomotor::where('id_inscripcion', $data['id_inscripcion'])->first();

            if ($registro) {
                $registro->update($data);

                return [
                    'error' => false,
                    'message' => 'Registro de desarrollo psicomotor actualizado correctamente.',
                    'data' => $registro->fresh()->toArray()
                ];
            }

            $registro = HceDesarrolloPsicomotor::create($data);

            return [
                'error' => false,
                'message' => 'Registro de desarrollo psicomotor creado correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al guardar el registro de desarrollo psicomotor.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al guardar el registro.',
                'data' => []
            ];
        }
    }

    public function actualizarDesarrolloPsicomotor(int $id, array $data): array
    {
        try {
            $registro = HceDesarrolloPsicomotor::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de desarrollo psicomotor no encontrado.',
                    'data' => []
                ];
            }

            $registro->update($data);

            return [
                'error' => false,
                'message' => 'Registro de desarrollo psicomotor actualizado correctamente.',
                'data' => $registro->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el registro de desarrollo psicomotor.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el registro.',
                'data' => []
            ];
        }
    }

    public function eliminarDesarrolloPsicomotor(int $id): array
    {
        try {
            $registro = HceDesarrolloPsicomotor::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de desarrollo psicomotor no encontrado.',
                    'data' => []
                ];
            }

            $registro->delete();

            return [
                'error' => false,
                'message' => 'Registro de desarrollo psicomotor eliminado correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el registro de desarrollo psicomotor.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar el registro.',
                'data' => []
            ];
        }
    }

    // ── Embarazo Parto ──────────────────────────────────────────

    public function verEmbarazoParto(int $id_inscripcion): array
    {
        try {
            $registro = HceEmbarazoParto::where('id_inscripcion', $id_inscripcion)->first();

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'No se encontró el registro de embarazo y parto para esta inscripción.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Registro obtenido correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener el registro de embarazo y parto.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el registro.',
                'data' => []
            ];
        }
    }

    public function añadirEmbarazoParto(array $data): array
    {
        try {
            $registro = HceEmbarazoParto::where('id_inscripcion', $data['id_inscripcion'])->first();

            if ($registro) {
                $registro->update($data);

                return [
                    'error' => false,
                    'message' => 'Registro de embarazo y parto actualizado correctamente.',
                    'data' => $registro->fresh()->toArray()
                ];
            }

            $registro = HceEmbarazoParto::create($data);

            return [
                'error' => false,
                'message' => 'Registro de embarazo y parto creado correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al guardar el registro de embarazo y parto.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al guardar el registro.',
                'data' => []
            ];
        }
    }

    public function actualizarEmbarazoParto(int $id, array $data): array
    {
        try {
            $registro = HceEmbarazoParto::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de embarazo y parto no encontrado.',
                    'data' => []
                ];
            }

            $registro->update($data);

            return [
                'error' => false,
                'message' => 'Registro de embarazo y parto actualizado correctamente.',
                'data' => $registro->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el registro de embarazo y parto.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el registro.',
                'data' => []
            ];
        }
    }

    public function eliminarEmbarazoParto(int $id): array
    {
        try {
            $registro = HceEmbarazoParto::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de embarazo y parto no encontrado.',
                    'data' => []
                ];
            }

            $registro->delete();

            return [
                'error' => false,
                'message' => 'Registro de embarazo y parto eliminado correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el registro de embarazo y parto.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar el registro.',
                'data' => []
            ];
        }
    }

    // ── Estructura Familiar ─────────────────────────────────────

    public function verEstructuraFamiliar(int $id_inscripcion): array
    {
        try {
            $registro = HceEstructuraFamiliar::where('id_inscripcion', $id_inscripcion)->first();

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'No se encontró el registro de estructura familiar para esta inscripción.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Registro obtenido correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener el registro de estructura familiar.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el registro.',
                'data' => []
            ];
        }
    }

    public function añadirEstructuraFamiliar(array $data): array
    {
        try {
            $registro = HceEstructuraFamiliar::where('id_inscripcion', $data['id_inscripcion'])->first();

            if ($registro) {
                $registro->update($data);

                return [
                    'error' => false,
                    'message' => 'Registro de estructura familiar actualizado correctamente.',
                    'data' => $registro->fresh()->toArray()
                ];
            }

            $registro = HceEstructuraFamiliar::create($data);

            return [
                'error' => false,
                'message' => 'Registro de estructura familiar creado correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al guardar el registro de estructura familiar.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al guardar el registro.',
                'data' => []
            ];
        }
    }

    public function actualizarEstructuraFamiliar(int $id, array $data): array
    {
        try {
            $registro = HceEstructuraFamiliar::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de estructura familiar no encontrado.',
                    'data' => []
                ];
            }

            $registro->update($data);

            return [
                'error' => false,
                'message' => 'Registro de estructura familiar actualizado correctamente.',
                'data' => $registro->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el registro de estructura familiar.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el registro.',
                'data' => []
            ];
        }
    }

    public function eliminarEstructuraFamiliar(int $id): array
    {
        try {
            $registro = HceEstructuraFamiliar::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de estructura familiar no encontrado.',
                    'data' => []
                ];
            }

            $registro->delete();

            return [
                'error' => false,
                'message' => 'Registro de estructura familiar eliminado correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el registro de estructura familiar.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar el registro.',
                'data' => []
            ];
        }
    }

    // ── Hermanos ────────────────────────────────────────────────

    public function verHermanos(int $id_inscripcion): array
    {
        try {
            $registros = HceHermano::where('id_inscripcion', $id_inscripcion)->get();

            if ($registros->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron hermanos para esta inscripción.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Hermanos obtenidos correctamente.',
                'data' => $registros->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener los hermanos.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener los hermanos.',
                'data' => []
            ];
        }
    }

    public function añadirHermano(array $data): array
    {
        try {
            $hermano = HceHermano::create($data);

            return [
                'error' => false,
                'message' => 'Hermano creado correctamente.',
                'data' => $hermano->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear el hermano.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear el hermano.',
                'data' => []
            ];
        }
    }

    public function actualizarHermano(int $id, array $data): array
    {
        try {
            $hermano = HceHermano::find($id);

            if (!$hermano) {
                return [
                    'error' => true,
                    'message' => 'Hermano no encontrado.',
                    'data' => []
                ];
            }

            $hermano->update($data);

            return [
                'error' => false,
                'message' => 'Hermano actualizado correctamente.',
                'data' => $hermano->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el hermano.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el hermano.',
                'data' => []
            ];
        }
    }

    public function eliminarHermano(int $id): array
    {
        try {
            $hermano = HceHermano::find($id);

            if (!$hermano) {
                return [
                    'error' => true,
                    'message' => 'Hermano no encontrado.',
                    'data' => []
                ];
            }

            $hermano->delete();

            return [
                'error' => false,
                'message' => 'Hermano eliminado correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el hermano.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar el hermano.',
                'data' => []
            ];
        }
    }

    // ── Historia Escolar ────────────────────────────────────────

    public function verHistoriaEscolar(int $id_inscripcion): array
    {
        try {
            $registro = HceHistoriaEscolar::where('id_inscripcion', $id_inscripcion)->first();

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'No se encontró el registro de historia escolar para esta inscripción.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Registro obtenido correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener el registro de historia escolar.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el registro.',
                'data' => []
            ];
        }
    }

    public function añadirHistoriaEscolar(array $data): array
    {
        try {
            $registro = HceHistoriaEscolar::where('id_inscripcion', $data['id_inscripcion'])->first();

            if ($registro) {
                $registro->update($data);

                return [
                    'error' => false,
                    'message' => 'Registro de historia escolar actualizado correctamente.',
                    'data' => $registro->fresh()->toArray()
                ];
            }

            $registro = HceHistoriaEscolar::create($data);

            return [
                'error' => false,
                'message' => 'Registro de historia escolar creado correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al guardar el registro de historia escolar.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al guardar el registro.',
                'data' => []
            ];
        }
    }

    public function actualizarHistoriaEscolar(int $id, array $data): array
    {
        try {
            $registro = HceHistoriaEscolar::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de historia escolar no encontrado.',
                    'data' => []
                ];
            }

            $registro->update($data);

            return [
                'error' => false,
                'message' => 'Registro de historia escolar actualizado correctamente.',
                'data' => $registro->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el registro de historia escolar.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el registro.',
                'data' => []
            ];
        }
    }

    public function eliminarHistoriaEscolar(int $id): array
    {
        try {
            $registro = HceHistoriaEscolar::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de historia escolar no encontrado.',
                    'data' => []
                ];
            }

            $registro->delete();

            return [
                'error' => false,
                'message' => 'Registro de historia escolar eliminado correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el registro de historia escolar.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar el registro.',
                'data' => []
            ];
        }
    }

    // ── Observaciones y Firmas ──────────────────────────────────

    public function verObservacionesFirmas(int $id_inscripcion): array
    {
        try {
            $registro = HceObservacionesFirmas::where('id_inscripcion', $id_inscripcion)->first();

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'No se encontró el registro de observaciones y firmas para esta inscripción.',
                    'data' => []
                ];
            }

            $data = $registro->toArray();

            // Generar URLs de las firmas si existen
            foreach (['firma_padre', 'firma_madre', 'firma_psicologa'] as $campo) {
                if (!empty($data[$campo])) {
                    $urlResult = $this->cloudinaryService->getFileUrl($data[$campo]);
                    $data[$campo . '_url'] = $urlResult['error'] ? null : $urlResult['data']['url'];
                }
            }

            return [
                'error' => false,
                'message' => 'Registro obtenido correctamente.',
                'data' => $data
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener el registro de observaciones y firmas.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el registro.',
                'data' => []
            ];
        }
    }

    public function añadirObservacionesFirmas(array $data): array
    {
        try {
            $registro = HceObservacionesFirmas::where('id_inscripcion', $data['id_inscripcion'])->first();

            if ($registro) {
                $registro->update($data);

                return [
                    'error' => false,
                    'message' => 'Registro de observaciones y firmas actualizado correctamente.',
                    'data' => $registro->fresh()->toArray()
                ];
            }

            $registro = HceObservacionesFirmas::create($data);

            return [
                'error' => false,
                'message' => 'Registro de observaciones y firmas creado correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al guardar el registro de observaciones y firmas.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al guardar el registro.',
                'data' => []
            ];
        }
    }

    public function actualizarObservacionesFirmas(int $id, array $data): array
    {
        try {
            $registro = HceObservacionesFirmas::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de observaciones y firmas no encontrado.',
                    'data' => []
                ];
            }

            $registro->update($data);

            return [
                'error' => false,
                'message' => 'Registro de observaciones y firmas actualizado correctamente.',
                'data' => $registro->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el registro de observaciones y firmas.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el registro.',
                'data' => []
            ];
        }
    }

    public function subirFirma(int $id, string $campo, UploadedFile $file): array
    {
        try {
            $camposPermitidos = ['firma_padre', 'firma_madre', 'firma_psicologa'];

            if (!in_array($campo, $camposPermitidos)) {
                return [
                    'error' => true,
                    'message' => 'Campo de firma no válido. Permitidos: ' . implode(', ', $camposPermitidos),
                    'data' => []
                ];
            }

            $registro = HceObservacionesFirmas::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de observaciones y firmas no encontrado.',
                    'data' => []
                ];
            }

            // Eliminar firma anterior si existe
            if ($registro->{$campo}) {
                $this->cloudinaryService->deleteFile($registro->{$campo});
            }

            $resultado = $this->cloudinaryService->uploadFile($file, 'historia-clinica/firmas');

            if ($resultado['error']) {
                return [
                    'error' => true,
                    'message' => $resultado['message'],
                    'data' => []
                ];
            }

            $registro->update([
                $campo => $resultado['data']['public_id']
            ]);

            return [
                'error' => false,
                'message' => 'Firma subida correctamente.',
                'data' => $registro->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al subir la firma.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al subir la firma.',
                'data' => []
            ];
        }
    }

    public function eliminarObservacionesFirmas(int $id): array
    {
        try {
            $registro = HceObservacionesFirmas::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de observaciones y firmas no encontrado.',
                    'data' => []
                ];
            }

            // Eliminar firmas de Cloudinary
            foreach (['firma_padre', 'firma_madre', 'firma_psicologa'] as $campo) {
                if ($registro->{$campo}) {
                    $this->cloudinaryService->deleteFile($registro->{$campo});
                }
            }

            $registro->delete();

            return [
                'error' => false,
                'message' => 'Registro de observaciones y firmas eliminado correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el registro de observaciones y firmas.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar el registro.',
                'data' => []
            ];
        }
    }

    // ── Psicoafectiva ───────────────────────────────────────────

    public function verPsicoafectiva(int $id_inscripcion): array
    {
        try {
            $registro = HcePsicoafectiva::where('id_inscripcion', $id_inscripcion)->first();

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'No se encontró el registro psicoafectivo para esta inscripción.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Registro obtenido correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener el registro psicoafectivo.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el registro.',
                'data' => []
            ];
        }
    }

    public function añadirPsicoafectiva(array $data): array
    {
        try {
            $registro = HcePsicoafectiva::where('id_inscripcion', $data['id_inscripcion'])->first();

            if ($registro) {
                $registro->update($data);

                return [
                    'error' => false,
                    'message' => 'Registro psicoafectivo actualizado correctamente.',
                    'data' => $registro->fresh()->toArray()
                ];
            }

            $registro = HcePsicoafectiva::create($data);

            return [
                'error' => false,
                'message' => 'Registro psicoafectivo creado correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al guardar el registro psicoafectivo.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al guardar el registro.',
                'data' => []
            ];
        }
    }

    public function actualizarPsicoafectiva(int $id, array $data): array
    {
        try {
            $registro = HcePsicoafectiva::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro psicoafectivo no encontrado.',
                    'data' => []
                ];
            }

            $registro->update($data);

            return [
                'error' => false,
                'message' => 'Registro psicoafectivo actualizado correctamente.',
                'data' => $registro->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el registro psicoafectivo.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el registro.',
                'data' => []
            ];
        }
    }

    public function eliminarPsicoafectiva(int $id): array
    {
        try {
            $registro = HcePsicoafectiva::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro psicoafectivo no encontrado.',
                    'data' => []
                ];
            }

            $registro->delete();

            return [
                'error' => false,
                'message' => 'Registro psicoafectivo eliminado correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el registro psicoafectivo.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar el registro.',
                'data' => []
            ];
        }
    }

    // ── Remisión ────────────────────────────────────────────────

    public function verRemision(int $id_inscripcion): array
    {
        try {
            $registro = HceRemision::where('id_inscripcion', $id_inscripcion)->first();

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'No se encontró el registro de remisión para esta inscripción.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Registro obtenido correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener el registro de remisión.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el registro.',
                'data' => []
            ];
        }
    }

    public function añadirRemision(array $data): array
    {
        try {
            $registro = HceRemision::where('id_inscripcion', $data['id_inscripcion'])->first();

            if ($registro) {
                $registro->update($data);

                return [
                    'error' => false,
                    'message' => 'Registro de remisión actualizado correctamente.',
                    'data' => $registro->fresh()->toArray()
                ];
            }

            $registro = HceRemision::create($data);

            return [
                'error' => false,
                'message' => 'Registro de remisión creado correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al guardar el registro de remisión.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al guardar el registro.',
                'data' => []
            ];
        }
    }

    public function actualizarRemision(int $id, array $data): array
    {
        try {
            $registro = HceRemision::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de remisión no encontrado.',
                    'data' => []
                ];
            }

            $registro->update($data);

            return [
                'error' => false,
                'message' => 'Registro de remisión actualizado correctamente.',
                'data' => $registro->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el registro de remisión.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el registro.',
                'data' => []
            ];
        }
    }

    public function eliminarRemision(int $id): array
    {
        try {
            $registro = HceRemision::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de remisión no encontrado.',
                    'data' => []
                ];
            }

            $registro->delete();

            return [
                'error' => false,
                'message' => 'Registro de remisión eliminado correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el registro de remisión.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar el registro.',
                'data' => []
            ];
        }
    }
}
