<?php

namespace App\Services\Cursos;

use App\Models\Areas\Cursos;
use Throwable;

class CursosServices
{
    public function getCursos()
    {
        try {
            $cursos = Cursos::select(
                'id',
                'nombre',
                'curso_proximo',
                'activo'
            )->orderBy('id', 'asc')
                ->get();

            return $cursos;
        } catch (Throwable $err) {
            return [
                'error' => true,
                'message' => $err->getMessage(),
            ];
        }
    }
}
