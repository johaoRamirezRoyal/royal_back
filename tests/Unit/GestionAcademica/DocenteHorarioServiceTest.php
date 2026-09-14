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
        $nivel = new Nivel(['id' => 8, 'nombre' => 'Egresado', 'id_nivel_academico' => null]);

        $this->assertSame([], $this->invoke($nivel));
    }

    public function test_nivel_normal_aporta_su_propio_id(): void
    {
        $nivel = new Nivel(['id' => 3, 'nombre' => 'Primaria', 'id_nivel_academico' => 2]);

        $this->assertSame([2], $this->invoke($nivel));
    }

    /**
     * El bucket de 6°-11° se identifica por id (4), no por nombre — ver
     * NIVEL_ID_BACHILLERATO_SECUNDARIA. El nombre de esa fila varía según la BD
     * (algunas quedaron como "Bachillerato", otras como "Secundaria", ver
     * 2026_09_12_120000_backfill_id_nivel_academico_for_secundaria); el comportamiento
     * debe ser el mismo sin importar cuál tenga.
     */
    public function test_id_4_aporta_media_y_secundaria_sin_importar_el_nombre(): void
    {
        $bachillerato = new Nivel(['id' => 4, 'nombre' => 'Bachillerato', 'id_nivel_academico' => 4]);
        $secundaria = new Nivel(['id' => 4, 'nombre' => 'Secundaria', 'id_nivel_academico' => 4]);

        $this->assertSame([4, 3], $this->invoke($bachillerato));
        $this->assertSame([4, 3], $this->invoke($secundaria));
    }

    /**
     * Regresión del bug real: antes de este fix la rama se activaba comparando
     * `$nivel->nombre === 'Bachillerato'`, así que una fila con ESE nombre pero un id
     * distinto de 4 (coincidencia de nombre en otra fila, o el id real difiere en otro
     * entorno) hubiera agregado Secundaria igual, incorrectamente. Ahora no debe pasar:
     * solo cuenta el id.
     */
    public function test_nombre_bachillerato_con_otro_id_no_aporta_secundaria(): void
    {
        $nivel = new Nivel(['id' => 99, 'nombre' => 'Bachillerato', 'id_nivel_academico' => 4]);

        $this->assertSame([4], $this->invoke($nivel));
    }
}
