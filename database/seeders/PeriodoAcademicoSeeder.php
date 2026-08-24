<?php

namespace Database\Seeders;

use App\Services\AnioEscolar\PeriodoAcademicoServices;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PeriodoAcademicoSeeder extends Seeder
{
    /**
     * Cuatro bimestres para el año escolar activo, vía el Service real (no inserts
     * directos) para respetar la resolución automática de id_anio_escolar y la validación
     * de rango de fechas del calendario configurado (ver AnioEscolarServices/
     * PeriodoAcademicoServices::agregarPeriodoAcademico). Si el año activo no está en
     * Calendario B (ago-jun) estas fechas fijas podrían no encajar — se omite con un aviso
     * en vez de fallar la migración/seed completa.
     */
    public function run(): void
    {
        $bimestres = [
            ['nombre' => 'Primer bimestre', 'fecha_inicio' => '2026-08-01', 'fecha_fin' => '2026-10-09'],
            ['nombre' => 'Segundo bimestre', 'fecha_inicio' => '2026-10-12', 'fecha_fin' => '2026-12-11'],
            ['nombre' => 'Tercer bimestre', 'fecha_inicio' => '2027-01-25', 'fecha_fin' => '2027-03-26'],
            ['nombre' => 'Cuarto bimestre', 'fecha_inicio' => '2027-03-29', 'fecha_fin' => '2027-06-11'],
        ];

        $service = app(PeriodoAcademicoServices::class);

        foreach ($bimestres as $data) {
            $yaExiste = DB::table('periodo_academico')->where('nombre', $data['nombre'])->exists();
            if ($yaExiste) {
                continue;
            }

            $resultado = $service->agregarPeriodoAcademico(['activo' => true, ...$data]);

            if ($resultado['error']) {
                $this->command?->warn("PeriodoAcademicoSeeder: no se pudo crear '{$data['nombre']}' — {$resultado['message']}");
            }
        }
    }
}
