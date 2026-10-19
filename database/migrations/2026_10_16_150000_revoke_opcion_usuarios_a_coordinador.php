<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ID_OPCION_USUARIOS = 2;
    private const ID_PERFIL_COORDINADOR = 26;

    /**
     * En producción el Coordinador tiene otorgada la opción 2 ("Usuarios"), que es la que
     * protege /users en el frontend y UsuariosController::sinAcceso() en el backend. El
     * Coordinador no debe gestionar usuarios: se le revoca.
     */
    public function up(): void
    {
        DB::table('cron_permisos')
            ->where('id_opcion', self::ID_OPCION_USUARIOS)
            ->where('id_perfil', self::ID_PERFIL_COORDINADOR)
            ->delete();
    }

    public function down(): void
    {
        DB::table('cron_permisos')->insert([
            'id_opcion' => self::ID_OPCION_USUARIOS,
            'id_perfil' => self::ID_PERFIL_COORDINADOR,
            'activo' => 1,
            'fechareg' => now(),
        ]);
    }
};
