<?php

namespace App\Services\Estudiantes;

use App\Enums\TipoPerfil;
use App\Models\Usuarios\Perfil;
use App\Models\Estudiantes\EstudiantesPadre;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\QueryException;

/**
 * Vínculos acudiente (perfil 6) ↔ estudiante (perfil 16) en `estudiantes_padres`. La carga
 * masiva desde Excel está en AcudientesService::importarExcel (crea, actualiza y vincula).
 * Desvincular es soft (activo=0) — el resto del sistema (llegadas tarde, enfermería,
 * /usuarios/paginados) ya filtra por activo=1, y volver a vincular el mismo par reactiva
 * la fila existente en vez de duplicarla (la tabla no tiene índice único).
 */
class VinculacionAcudienteService
{
    public function estudiantesVinculados(int $idAcudiente): array
    {
        try {
            $vinculos = EstudiantesPadre::where('id_acudiente', $idAcudiente)
                ->where('activo', 1)
                ->with([
                    'estudiante:id_user,documento,nombre,apellido,id_curso,estado',
                    'estudiante.cursoRelacion:id,nombre',
                ])
                ->get()
                ->filter(fn ($vinculo) => $vinculo->estudiante);

            $idsEstudiantes = $vinculos->pluck('id_estudiante')->unique()->values();

            $otrosPorEstudiante = EstudiantesPadre::whereIn('id_estudiante', $idsEstudiantes)
                ->where('id_acudiente', '!=', $idAcudiente)
                ->where('activo', 1)
                ->with('acudiente:id_user,nombre,apellido')
                ->get()
                ->filter(fn ($vinculo) => $vinculo->acudiente)
                ->groupBy('id_estudiante');

            $estudiantes = $vinculos->unique('id_estudiante')->map(function ($vinculo) use ($otrosPorEstudiante) {
                $estudiante = $vinculo->estudiante;

                return [
                    'id_user' => $estudiante->id_user,
                    'documento' => $estudiante->documento,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'estado' => $estudiante->estado,
                    'curso' => $estudiante->cursoRelacion?->nombre,
                    'otros_acudientes' => $otrosPorEstudiante->get($estudiante->id_user, collect())
                        ->unique('id_acudiente')
                        ->map(fn ($otro) => [
                            'id_user' => $otro->acudiente->id_user,
                            'nombre' => $otro->acudiente->nombre,
                            'apellido' => $otro->acudiente->apellido,
                        ])->values()->all(),
                ];
            })->sortBy('nombre')->values()->all();

            return [
                'error' => false,
                'message' => 'Datos obtenidos satisfactoriamente',
                'data' => $estudiantes,
            ];
        } catch (QueryException $e) {
            return [
                'error' => true,
                'message' => 'Ha ocurrido un error inesperado',
                'data' => $e->getMessage(),
            ];
        }
    }

    public function acudientesVinculados(int $idEstudiante): array
    {
        return EstudiantesPadre::where('id_estudiante', $idEstudiante)
            ->where('activo', 1)
            ->with('acudiente:id_user,documento,nombre,apellido,correo,telefono')
            ->get()
            ->pluck('acudiente')
            ->filter()
            ->unique('id_user')
            ->sortBy('nombre')
            ->values()
            ->all();
    }

    public function vincular(int $idAcudiente, int $idEstudiante): array
    {
        if (!Usuario::where('id_user', $idAcudiente)->whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::ACUDIENTE))->exists()) {
            return ['error' => true, 'message' => 'El usuario indicado no es un acudiente.', 'data' => []];
        }

        if (!Usuario::where('id_user', $idEstudiante)->whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::ESTUDIANTE))->exists()) {
            return ['error' => true, 'message' => 'El usuario indicado no es un estudiante.', 'data' => []];
        }

        try {
            $existente = EstudiantesPadre::where('id_acudiente', $idAcudiente)
                ->where('id_estudiante', $idEstudiante)
                ->orderByDesc('activo')
                ->first();

            if ($existente?->activo === 1) {
                return ['error' => true, 'message' => 'El estudiante ya está vinculado a este acudiente.', 'data' => []];
            }

            if ($existente) {
                $existente->update(['activo' => 1]);
            } else {
                $existente = EstudiantesPadre::create([
                    'id_acudiente' => $idAcudiente,
                    'id_estudiante' => $idEstudiante,
                    'activo' => 1,
                    'fechareg' => now(),
                ]);
            }

            return ['error' => false, 'message' => 'Estudiante vinculado correctamente.', 'data' => $existente];
        } catch (QueryException $e) {
            return ['error' => true, 'message' => 'Ha ocurrido un error inesperado', 'data' => $e->getMessage()];
        }
    }

    public function desvincular(int $idAcudiente, int $idEstudiante): array
    {
        try {
            $afectadas = EstudiantesPadre::where('id_acudiente', $idAcudiente)
                ->where('id_estudiante', $idEstudiante)
                ->where('activo', 1)
                ->update(['activo' => 0]);

            if ($afectadas === 0) {
                return ['error' => true, 'message' => 'El estudiante no está vinculado a este acudiente.', 'data' => []];
            }

            return ['error' => false, 'message' => 'Estudiante desvinculado correctamente.', 'data' => []];
        } catch (QueryException $e) {
            return ['error' => true, 'message' => 'Ha ocurrido un error inesperado', 'data' => $e->getMessage()];
        }
    }
}
