<?php

namespace App\Services\AdminManagement;

use App\Enums\Mails;
use App\Models\CorreoInstitucional;

/**
 * CRUD de `correos_institucionales` para TODOS los grupos de App\Enums\Mails (pantalla
 * "Correos institucionales" de Administración del sistema). Mismo CRUD que
 * NoticiasService::*CorreoDistribucion, pero sin acotar a NOTICIAS_* — los grupos siguen
 * siendo solo los del enum (un grupo inventado no lo leería ningún envío).
 */
class CorreosInstitucionalesService
{
    public function listar(): array
    {
        try {
            return [
                'error' => false,
                'message' => 'Correos institucionales obtenidos correctamente',
                'data' => [
                    'grupos' => array_map(fn (Mails $m) => ['grupo' => $m->name, 'label' => $m->label()], Mails::cases()),
                    'correos' => CorreoInstitucional::orderBy('grupo')->orderBy('id')->get(),
                ],
            ];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crear(array $datos): array
    {
        try {
            $correo = CorreoInstitucional::create([
                'grupo' => $datos['grupo'],
                'nombre' => $datos['nombre'] ?? null,
                'correo' => $datos['correo'],
                'activo' => $datos['activo'] ?? true,
            ]);

            return ['error' => false, 'message' => 'Correo agregado', 'data' => $correo];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizar(int $id, array $datos): array
    {
        try {
            $correo = CorreoInstitucional::find($id);

            if (!$correo) {
                return ['error' => true, 'message' => 'Correo no encontrado', 'status' => 404];
            }

            $correo->update([
                'nombre' => $datos['nombre'] ?? null,
                'correo' => $datos['correo'],
                'activo' => $datos['activo'] ?? true,
            ]);

            return ['error' => false, 'message' => 'Correo actualizado', 'data' => $correo];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function eliminar(int $id): array
    {
        try {
            if (!CorreoInstitucional::where('id', $id)->delete()) {
                return ['error' => true, 'message' => 'Correo no encontrado', 'status' => 404];
            }

            return ['error' => false, 'message' => 'Correo eliminado', 'data' => []];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
