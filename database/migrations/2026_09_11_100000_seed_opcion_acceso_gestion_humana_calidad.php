<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El grupo de sidebar "Gestion humana y calidad" (asistencias de trabajadores +
 * listado de personal/hoja de vida + configuración de asistencia) quedaba visible para
 * CUALQUIER perfil porque su gate combinaba (OR) las opciones de sus subitems junto con
 * la opción legacy 23 "Gestion Humana" — esa 23 está otorgada históricamente a casi todos
 * los perfiles (incluido Docente), sin relación real con este módulo. Se agrega una
 * opción propia y dedicada para el módulo completo, otorgada solo a quienes ya tenían
 * acceso real a alguno de sus subitems (63 Asistencia, 87 Listado Personal HV, 100
 * Configuración de asistencia): Super Admin, Administrador, Recursos Humanos y
 * Coordinador — reemplaza la opción 23 en el gate del grupo (ver sideBar/index.layout.tsx).
 */
return new class extends Migration
{
    private const NOMBRE_OPCION = 'Gestión Humana y Calidad — Acceso al módulo';

    // Mismo módulo que 63/87/100 (Asistencia, Listado Personal HV, Configuración de asistencia).
    private const ID_MODULO_GESTION_HUMANA = 3;

    // Super Admin, Administrador, Recursos Humanos, Coordinador — ya tenían acceso real a
    // alguno de los subitems del grupo (63/87/100); Docente y el resto solo lo veían por
    // la opción 23, sin relación con este módulo.
    private const PERFILES_CON_ACCESO = [1, 2, 8, 26];

    public function up(): void
    {
        $idOpcion = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE_OPCION,
            'id_modulo' => self::ID_MODULO_GESTION_HUMANA,
            'activo' => 1,
            'fechareg' => now(),
        ]);

        foreach (self::PERFILES_CON_ACCESO as $idPerfil) {
            DB::table('cron_permisos')->insert([
                'id_opcion' => $idOpcion,
                'id_perfil' => $idPerfil,
                'activo' => 1,
                'fechareg' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $idOpcion = DB::table('cron_opciones')->where('nombre', self::NOMBRE_OPCION)->value('id');

        if ($idOpcion) {
            DB::table('cron_permisos')->where('id_opcion', $idOpcion)->delete();
            DB::table('cron_opciones')->where('id', $idOpcion)->delete();
        }
    }
};
