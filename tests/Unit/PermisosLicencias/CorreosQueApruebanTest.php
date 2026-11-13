<?php

namespace Tests\Unit\PermisosLicencias;

use App\Services\permisosLicencias\PermisosLicenciasServices;
use App\Support\Permiso;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Solo quien puede aprobar/rechazar (opción 83, o Super Admin) recibe el botón a la página
 * de detalle en el correo de solicitud de permiso (PermisosLicenciasServices::correosQueAprueban).
 */
class CorreosQueApruebanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('usuarios', function (Blueprint $t) {
            $t->increments('id_user');
            $t->string('correo')->nullable();
            $t->integer('perfil')->nullable();
        });
        Schema::create('cron_modulos', fn (Blueprint $t) => $t->increments('id'));
        Schema::create('cron_opciones', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('id_modulo');
        });
        Schema::create('cron_permisos', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('id_perfil');
            $t->integer('id_opcion');
            $t->integer('activo')->default(1);
        });

        DB::table('cron_modulos')->insert(['id' => 1]);
        DB::table('cron_opciones')->insert([
            ['id' => Permiso::PERMISOS_LICENCIAS_APROBAR, 'id_modulo' => 1],
            ['id' => Permiso::PERMISOS_LICENCIAS_LISTADO, 'id_modulo' => 1],
        ]);
        DB::table('cron_permisos')->insert([
            ['id_perfil' => 26, 'id_opcion' => Permiso::PERMISOS_LICENCIAS_APROBAR, 'activo' => 1],
            ['id_perfil' => 11, 'id_opcion' => Permiso::PERMISOS_LICENCIAS_LISTADO, 'activo' => 1],
            ['id_perfil' => 7, 'id_opcion' => Permiso::PERMISOS_LICENCIAS_APROBAR, 'activo' => 0],
        ]);
        DB::table('usuarios')->insert([
            ['correo' => 'coordinador@colegio.test', 'perfil' => 26],
            ['correo' => 'asistente@colegio.test', 'perfil' => 11],
            ['correo' => 'directivo@colegio.test', 'perfil' => 7],
            ['correo' => 'admin@colegio.test', 'perfil' => Permiso::PERFIL_SUPER_ADMIN],
        ]);
    }

    public function test_solo_devuelve_correos_con_opcion_aprobar_o_super_admin(): void
    {
        $m = new ReflectionMethod(PermisosLicenciasServices::class, 'correosQueAprueban');

        $correos = $m->invoke(app(PermisosLicenciasServices::class), [
            'coordinador@colegio.test',
            'asistente@colegio.test',
            'directivo@colegio.test', // opción 83 inactiva
            'admin@colegio.test',
            'gestion.humana@colegio.test', // no es usuario
        ]);

        sort($correos);
        $this->assertSame(['admin@colegio.test', 'coordinador@colegio.test'], $correos);
    }
}
