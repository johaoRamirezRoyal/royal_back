<?php

namespace App\Services\Niveles;

use App\Models\Usuarios\Nivel;

class NivelesServices 
{
    /**
     * @param bool $soloAcademicos Si es true, solo trae niveles con id_nivel_academico
     *   asignado (Preescolar/Primaria/Secundaria/Media) — excluye las categorías no
     *   académicas de usuario (Administrativo/Acudiente/Operativo/Egresado, etc.).
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
}