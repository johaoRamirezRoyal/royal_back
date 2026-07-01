<?php

namespace App\Http\Controllers\Cursos;

use App\Http\Controllers\Controller;
use App\Services\Cursos\CursosServices;

class CursosController extends Controller
{
    public function findAll(CursosServices $cursos)
    {
        $responseData = $cursos->getCursos();

        return $this->success('Se han encontrado cursos', $responseData);
    }
}
