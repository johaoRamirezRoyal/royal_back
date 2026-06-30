<?php

namespace App\Services\GestionAcademica;

use App\Models\GestionAcademica\DocenteAsignatura as DocenteAsignaturaModel;
use App\Models\Usuarios\Usuario;
use App\Services\Service;
use Exception;
use Illuminate\Support\Facades\DB;

class DocenteAsignatura extends Service
{
    public function listarDocentesAsignaturas(?int $usuario, ?int $asignatura, ?string $search, int $perpage = 10): array
    {
        try {
            $docentes = Usuario::query()
                ->where('perfil', 3)
                ->when($usuario, function ($query) use ($usuario) {
                    $query->where('id_user', $usuario);
                })
                ->when($asignatura, function ($query) use ($asignatura) {
                $query->whereHas('asignaturas', function ($q) use ($asignatura) {
                    $q->whereIn('id_asignatura', $asignatura);
                });
                })
                ->when($search, function ($query) use ($search){
                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'LIKE', "%{$search}%")
                        ->orWhere('apellido', 'LIKE', "%{$search}%")
                        ->orWhere('documento', 'LIKE', "%{$search}%")
                        ->orWhere('correo', 'LIKE', "%{$search}%");
                });
                })
                ->select(
                    'id_user',
                    'nombre',
                    'apellido',
                    'id_nivel'
                )
                ->with([
                    'nivelRelacion:id,nombre',
                    'docente_asignaturas:id,id_asignatura,id_docente',
                    'docente_asignaturas.asignatura:id,nombre,abreviatura,color'
                ])
                ->orderBy('apellido')
                ->orderBy('nombre')
                ->paginate($perpage);

            if ($docentes->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron docentes asignados.',
                    'data' => []
                ];
            }
            return [
                'error' => false,
                'message' => "Se han obtenido las asignaturas de los docentes.",
                'data' => $docentes
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al listar docentes asignados.');
            return [
                'error' => true,
                'message' => 'Error en el servidor.',
                'data' => []
            ];
        }
    }

    public function asignarAsignaturasDocente(int $id_user, array $asignaturas): array
    {
        try {

            if (empty($asignaturas)) {
                return [
                    'error' => true,
                    'message' => 'Debe enviar al menos una asignatura.',
                    'data' => []
                ];
            }

            $docente = Usuario::where('id_user', $id_user)
                ->where('perfil', 3)
                ->first();

            if (!$docente) {
                return [
                    'error' => true,
                    'message' => 'Docente no encontrado.',
                    'data' => []
                ];
            }

            $asignaturas = array_unique($asignaturas);

            DB::transaction(function () use ($id_user, $asignaturas) {

                $data = array_map(fn($id) => [
                    'id_docente' => $id_user,
                    'id_asignatura' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $asignaturas);

                DocenteAsignaturaModel::insertOrIgnore($data);
            });

            return [
                'error' => false,
                'message' => 'Asignaturas asignadas correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al asignar asignaturas.');

            return [
                'error' => true,
                'message' => 'Error en el servidor.',
                'data' => []
            ];
        }
    }

    public function eliminarAsignaturasDocente(array $ids_asignatura_docente): array {
        try {
            $asignaturas = DocenteAsignaturaModel::whereIn('id', $ids_asignatura_docente)->delete();

            if ($asignaturas == 0) {
              return [
                'error' => true,
                'message' => "No se pudieron eliminar las asignaturas al docente",
                'data' => []
              ];
            }

            return [
                'error' => false,
                'message' => 'Asignaturas eliminadas correctamente.',
                'data' => [$asignaturas],
            ];
        }catch(Exception $e){
            $this->sendError($e, 'Error al eliminar asignaturas del docente.');
            return [
                'error' => true,
                'message' => "Error en el servidor al eliminar las asignaturas del docente",
                'data' => []
            ];
        }
    }
}
