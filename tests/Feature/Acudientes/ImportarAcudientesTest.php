<?php

namespace Tests\Feature\Acudientes;

use App\Enums\TipoPerfil;
use App\Models\Estudiantes\EstudiantesPadre;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\Usuario;
use App\Services\Acudientes\AcudientesService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Carga masiva de acudientes (AcudientesService::importarExcel): crea, actualiza solo lo que
 * viene lleno, vincula hijos y reporta las filas malas sin detener las demás.
 * Contra la base real, en una transacción (ver SesionesActivasTest).
 */
class ImportarAcudientesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $env = file_get_contents(base_path('.env'));
        preg_match('/^DB_DATABASE=(.*)$/m', $env, $match);
        Config::set('database.default', 'mysql');
        Config::set('database.connections.mysql.database', trim($match[1] ?? 'laravel', " \t\n\r\0\x0B\"'"));
        DB::purge('mysql');
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_crea_actualiza_vincula_y_reporta_errores(): void
    {
        $hijos = Usuario::whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::ESTUDIANTE))
            ->where('documento', 'regexp', '^[0-9]{6,}$')
            ->get()->groupBy('documento')->filter(fn ($g) => $g->count() === 1)->take(2)->flatten();
        if ($hijos->count() < 2) {
            $this->markTestSkipped('Faltan dos estudiantes con documento único.');
        }

        $existente = Usuario::create([
            'documento' => '9999000201', 'nombre' => 'Viejo', 'apellido' => 'Nombre', 'correo' => 'existente.carga@prueba.test',
            'perfil' => Perfil::idParaCrear(TipoPerfil::ACUDIENTE), 'estado' => 'activo', 'pass' => 'x',
        ]);

        $resumen = $this->importar([
            ['Documento', 'Nombres', 'Apellidos', 'Correo', 'Teléfono', 'Documento del hijo'],
            [9999000202, 'Nueva', 'Acudiente', 'Nueva.Carga@Prueba.test', '3001112233', $hijos[0]->documento], // crea y vincula
            ['9.999.000.202', '', '', '', '', $hijos[1]->documento],                                           // mismo acudiente, segundo hijo
            ['9999000201', '', '', '', '3009998877', ''],                                                       // actualiza solo el teléfono
            ['9999000203', 'Sin', 'Correo', '', '', ''],                                                        // error: falta correo
            ['9999000205', 'Sin Apellido', '', 'sin.apellido@prueba.test', '', ''],                             // crea: el apellido es opcional
            ['9999000204', 'Hijo', 'Inexistente', 'otro.carga@prueba.test', '', '1'],                           // crea, pero el hijo no existe
        ]);

        $this->assertSame([3, 1, 2], [$resumen['creados'], $resumen['actualizados'], $resumen['vinculados']]);
        $this->assertSame([5, 7], array_column($resumen['errores'], 'fila'));
        // Cada vínculo nuevo sale con nombres, para revisar que el documento del hijo era el correcto.
        $this->assertSame([2, 3], array_column($resumen['vinculos'], 'fila'));
        $this->assertSame('Nueva Acudiente', $resumen['vinculos'][0]['acudiente']);
        $this->assertNotSame('', $resumen['vinculos'][0]['estudiante']);
        $this->assertNull(Usuario::where('documento', '9999000205')->value('apellido'));

        $nueva = Usuario::where('documento', '9999000202')->first();
        $this->assertSame('nueva.carga@prueba.test', $nueva->correo);
        $this->assertTrue($nueva->esTipo(TipoPerfil::ACUDIENTE));
        // Contraseña inicial = documento sin puntos (en el Excel venía como número).
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('9999000202', $nueva->getRawOriginal('pass')));
        $this->assertSame(2, EstudiantesPadre::where('id_acudiente', $nueva->id_user)->where('activo', 1)->count());

        $existente->refresh();
        $this->assertSame(['Viejo', '3009998877'], [$existente->nombre, $existente->telefono]);

        // Volver a subir los mismos datos (otra fila del mismo acudiente) no duplica nada ni cuenta
        // como actualizado, aunque el correo venga con otras mayúsculas.
        $otraVez = $this->importar([
            ['Documento', 'Nombres', 'Apellidos', 'Correo', 'Teléfono', 'Documento del hijo'],
            ['9999000202', 'Nueva', 'Acudiente', 'NUEVA.carga@prueba.test', '3001112233', $hijos[0]->documento],
            ['9999000202', '', '', '', '', $hijos[1]->documento],
        ]);
        $this->assertSame([0, 0, 0, []], [$otraVez['creados'], $otraVez['actualizados'], $otraVez['vinculados'], $otraVez['errores']]);
    }

    private function importar(array $filas): array
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->fromArray($filas);
        $ruta = tempnam(sys_get_temp_dir(), 'acu') . '.xlsx';
        (new Xlsx($libro))->save($ruta);

        $respuesta = app(AcudientesService::class)->importarExcel($ruta, 1);
        @unlink($ruta);

        $this->assertFalse($respuesta['error'], $respuesta['message']);

        return $respuesta['data'];
    }
}
