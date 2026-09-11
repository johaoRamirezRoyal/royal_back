<?php

namespace Tests\Unit\GestionAcademica;

use App\Models\Usuarios\Nivel;
use App\Services\GestionAcademica\CargaAcademicaService;
use App\Services\GestionAcademica\DocenteHorarioService;
use App\Services\GestionAcademica\HorarioClaseService;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Cubre idsNivelAcademicoParaNivel() en solitario (sin BD): la lógica de fusión de
 * id_nivel + id_nivel_2 de un docente ya se ejerce end-to-end vía verMenu(), pero eso
 * requiere fixtures de curso/asignatura/año escolar completos. Este test es la única
 * red de seguridad barata para la rama "Bachillerato agrega Secundaria" y para
 * "sin id_nivel_academico no cuenta".
 */
class DocenteHorarioServiceTest extends TestCase
{
    private function invoke(?Nivel $nivel): array
    {
        $service = new DocenteHorarioService(
            $this->createMock(CargaAcademicaService::class),
            $this->createMock(HorarioClaseService::class),
        );
        $method = new ReflectionMethod(DocenteHorarioService::class, 'idsNivelAcademicoParaNivel');
        $method->setAccessible(true);

        return $method->invoke($service, $nivel);
    }

    public function test_nivel_nulo_no_aporta_ids(): void
    {
        $this->assertSame([], $this->invoke(null));
    }

    public function test_nivel_sin_nivel_academico_no_aporta_ids(): void
    {
        $nivel = new Nivel(['nombre' => 'Egresado', 'id_nivel_academico' => null]);

        $this->assertSame([], $this->invoke($nivel));
    }

    public function test_nivel_normal_aporta_su_propio_id(): void
    {
        $nivel = new Nivel(['nombre' => 'Primaria', 'id_nivel_academico' => 2]);

        $this->assertSame([2], $this->invoke($nivel));
    }

    public function test_bachillerato_aporta_media_y_secundaria(): void
    {
        $nivel = new Nivel(['nombre' => 'Bachillerato', 'id_nivel_academico' => 4]);

        $this->assertSame([4, 3], $this->invoke($nivel));
    }
}
