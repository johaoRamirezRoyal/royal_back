<?php

namespace App\Services\GestionAcademica;

use App\Services\Service;

class GestionAcademicaService extends Service
{
    public function __construct(
        private Asignatura $asignatura,
        private AreaAcademicaService $areaAcademica,
        private DocenteAsignatura $docenteAsignatura,
        private CargaAcademicaService $cargaAcademica,
        private FranjaHorariaService $franjaHoraria,
        private HorarioClaseService $horarioClase,
        private AsistenciaClaseService $asistenciaClase,
        private AsistenciaEstudianteService $asistenciaEstudiante,
        private EsquemaHorarioService $esquemaHorario,
        private DocenteHorarioService $docenteHorario,
    ) {}

    public function asignatura(): Asignatura
    {
        return $this->asignatura;
    }

    public function areaAcademica(): AreaAcademicaService
    {
        return $this->areaAcademica;
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

    public function horarioClase(): HorarioClaseService
    {
        return $this->horarioClase;
    }

    public function asistenciaClase(): AsistenciaClaseService
    {
        return $this->asistenciaClase;
    }

    public function asistenciaEstudiante(): AsistenciaEstudianteService
    {
        return $this->asistenciaEstudiante;
    }

    public function esquemaHorario(): EsquemaHorarioService
    {
        return $this->esquemaHorario;
    }

    public function docenteHorario(): DocenteHorarioService
    {
        return $this->docenteHorario;
    }
}