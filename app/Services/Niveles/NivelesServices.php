<?php

namespace App\Services\Niveles;

use App\Models\Usuarios\Nivel;
use App\Models\Usuarios\NivelAcademico;

class NivelesServices
{
    /**
     * @param bool $soloAcademicos Si es true, solo trae niveles de `nivel` que abarcan algún
     *   nivel académico (nivel_nivel_academico: Preescolar/Primaria/Bachillerato — clasificación
     *   general de usuario) — excluye las categorías no académicas (Administrativo/
     *   Acudiente/Operativo/Egresado, etc.). Nota: `nivel` no tiene fila para Secundaria
     *   (nunca la tuvo como categoría de usuario) — para elegir entre los 4 niveles
     *   académicos reales (ej. el nivel de un curso o esquema de horario) usar
     *   mostrarTodosNivelesAcademicos(), no este método.
     */
    public function mostrarTodosNiveles(bool $soloAcademicos = false){
        try{
            $niveles = Nivel::query()
                ->when($soloAcademicos, fn ($q) => $q->whereIn('id', \Illuminate\Support\Facades\DB::table('nivel_nivel_academico')->select('id_nivel')))
                ->get();

            return [
                'error' => false,
                'data' => $niveles
            ];
        }catch(\Exception $e){
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /** Los 4 niveles académicos reales (Preescolar/Primaria/Secundaria/Media) — usar para poblar selectores de nivel de cursos/esquemas de horario, no mostrarTodosNiveles(). */
    public function mostrarTodosNivelesAcademicos(){
        try{
            return [
                'error' => false,
                'data' => NivelAcademico::all()
            ];
        }catch(\Exception $e){
            return [
                'error' => true,
                'message' => $e->getMessage(),
            ];
        }
    }

    /** Crea una fila de `nivel` (clasificación general de usuario). `activo` siempre true: se inactiva desde actualizarEstado(). */
    public function crear(array $datos): array
    {
        try {
            $nivel = Nivel::create([...$datos, 'activo' => true]);

            return ['error' => false, 'message' => "Nivel \"{$nivel->nombre}\" creado.", 'data' => $nivel];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function actualizar(int $id, array $datos): array
    {
        $nivel = Nivel::find($id);

        if (! $nivel) {
            return ['error' => true, 'message' => 'El nivel no existe.'];
        }

        $nivel->update($datos);

        return ['error' => false, 'message' => "Nivel \"{$nivel->nombre}\" actualizado.", 'data' => $nivel];
    }

    /**
     * Activa o inactiva niveles. Nunca se borran: `activo` solo oculta el nivel de los
     * selects de alta de usuario, no afecta a quienes ya lo tienen asignado.
     *
     * @param  array<int>  $ids
     */
    public function actualizarEstado(array $ids, bool $activo): array
    {
        Nivel::whereIn('id', $ids)->update(['activo' => $activo]);

        return [
            'error' => false,
            'message' => $activo ? 'Nivel(es) activado(s).' : 'Nivel(es) inactivado(s).',
        ];
    }
}