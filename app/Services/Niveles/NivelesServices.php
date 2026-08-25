<?php

namespace App\Services\Niveles;

use App\Models\Usuarios\Nivel;
use App\Models\Usuarios\NivelAcademico;

class NivelesServices
{
    /**
     * @param bool $soloAcademicos Si es true, solo trae niveles de `nivel` con
     *   id_nivel_academico asignado (Preescolar/Primaria/Bachillerato — clasificación
     *   general de usuario) — excluye las categorías no académicas (Administrativo/
     *   Acudiente/Operativo/Egresado, etc.). Nota: `nivel` no tiene fila para Secundaria
     *   (nunca la tuvo como categoría de usuario) — para elegir entre los 4 niveles
     *   académicos reales (ej. el nivel de un curso o esquema de horario) usar
     *   mostrarTodosNivelesAcademicos(), no este método.
     */
    public function mostrarTodosNiveles(bool $soloAcademicos = false){
        try{
            $niveles = Nivel::query()
                ->when($soloAcademicos, fn ($q) => $q->whereNotNull('id_nivel_academico'))
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
}