<?php

namespace App\Services\GestionAcademica;

use App\Services\Service;

class GestionAcademicaService extends Service
{
    public function __construct(
        private Asignatura $asignatura,
        private DocenteAsignatura $docenteAsignatura,
        private CargaAcademicaService $cargaAcademica,
        private FranjaHorariaService $franjaHoraria
    ) {}

    public function asignatura(): Asignatura
    {
        return $this->asignatura;
    }

    public function docenteAsignatura(): DocenteAsignatura
    {
        return $this->docenteAsignatura;
    }

    public function cargaAcademica(): CargaAcademicaService
    {
        return $this->cargaAcademica;
    }

    public function franjaHoraria(): FranjaHorariaService
    {
        return $this->franjaHoraria;
    }
}