<?php

namespace App\Services\GestionAcademica;

use App\Models\GestionAcademica\CargaAcademica;
use App\Services\Service;
use Exception;

class CargaAcademicaService extends Service
{
    public function añadirCargaAcademicaDocente(int $id_curso, int $id_docente_asignatura)
    {
        try {

            $carga = CargaAcademica::firstOrCreate(
                [
                    'id_docente_asignatura' => $id_docente_asignatura,
                    'id_curso' => $id_curso,
                ]
            );

            if (!$carga->wasRecentlyCreated) {
                return [
                    'error' => true,
                    'message' => 'Ya existe una carga académica para este docente en ese curso.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se ha creado la carga académica correctamente.",
                'data' => []
            ];
        } catch (Exception $e) {

            $this->sendError($e, "Error en el servidor al tratar de añadir la carga académica");

            return [
                'error' => true,
                'message' => "Error en el servidor...",
                'data' => []
            ];
        }
    }

    public function listarCargaAcademicaDocente(int $id_docente, int $estado, ?int $id_curso = null, ?int $id_asignatura = null)
    {
        try {

            $cargaAcademica = CargaAcademica::query()
                ->with([
                    'curso:id,nombre,id_nivel',
                    'curso.nivel:id,nombre',
                    'docenteAsignatura:id,id_docente,id_asignatura,activo',
                    'docenteAsignatura.docente:id_user,nombre,apellido,correo,documento',
                    'docenteAsignatura.asignatura:id,nombre,codigo,abreviatura,color',
                ])->where('activo', $estado)
                ->whereHas('docenteAsignatura', function ($query) use ($id_docente, $id_asignatura) {
                    $query->where('id_docente', $id_docente)
                        ->when($id_asignatura, function ($query) use ($id_asignatura) {
                            $query->where('id_asignatura', $id_asignatura);
                        });
                })
                ->when($id_curso, function ($query) use ($id_curso) {
                    $query->where('id_curso', $id_curso);
                })
                ->get();

            return [
                'error' => false,
                'message' => 'Carga académica obtenida correctamente.',
                'data' => $cargaAcademica,
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al listar la carga académica.');

            return [
                'error' => true,
                'message' => 'Error en el servidor...',
                'data' => [],
            ];
        }
    }

    public function cambiarEstadoCargaAcademica(array $ids, int $estado)
    {
        try {
            $data = CargaAcademica::whereIn('id', $ids)->update(['activo' => $estado]);

            if ($data == 0) {
                return [
                    'error' => true,
                    'message' => 'No se pudo cambiar el estado de las cargas académicas',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => "Se ha actualizado $data cargas académicas",
                'data' => []
            ];
        } catch (Exception $e) {

            $this->sendError($e, "Error al cambiar el estado de las cargas académicas.");

            return [
                'error' => true,
                'message' => "Ha ocurrido un error en el servidor. ",
                'data' => []
            ];
        }
    }
}
