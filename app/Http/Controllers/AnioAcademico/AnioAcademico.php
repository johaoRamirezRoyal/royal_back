<?php

namespace App\Http\Controllers\AnioAcademico;

use App\Http\Controllers\Controller;
use App\Services\AnioEscolar\AnioEscolarServices;

class AnioAcademico extends Controller
{
    private function __construct(protected AnioEscolarServices $anioEscolarServices)
    {}

    public function obtenerAniosAcademicos()
    {
        $data = $this->anioEscolarServices->obtenerAniosEscolares();

        if ($data['error']) {
            return $this->error($data['message']);
        };
        
        return $this->success($data['message'], $data['data']);
    }

    public function obtenerUltimoAnioAcademico()
    {
        $data = $this->anioEscolarServices->obtenerUltimoAnioEscolar();

        if ($data['error']) {
            return $this->error($data['message']);
        };

        return $this->success($data['message'], $data['data']);
    }
}
