<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `POST /solicitudes` (crear una solicitud de compra) no tenía gate — cualquier empleado
 * autenticado podía generar una. Se agrega un permiso dedicado para restringir quién puede
 * iniciar solicitudes de compra, otorgado por defecto solo a perfiles administrativos/de
 * gestión — el resto de perfiles puede recibirlo luego desde /permisos si hace falta.
 * "Mis solicitudes" (ver/cancelar las propias) NO se gatea con esta opción, sigue abierto.
 */
return new class extends Migration
{
    private const NOMBRE_OPCION = 'Compras — Generar solicitudes';

    private const ID_MODULO_PROCESO_COMPRA = 9;

    // Super Admin, Administrador, Directora administrativa, Recursos Humanos, Tesorera,
    // Administrativo, Coordinador, Contador General, Contador Play and Learn, Asistente
    // Contable — perfiles administrativos/de gestión ya existentes en el sistema.
    private const PERFILES_CON_ACCESO = [1, 2, 7, 8, 18, 22, 26, 29, 30, 31];

    public function up(): void
    {
        $idOpcion = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE_OPCION,
            'id_modulo' => self::ID_MODULO_PROCESO_COMPRA,
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
