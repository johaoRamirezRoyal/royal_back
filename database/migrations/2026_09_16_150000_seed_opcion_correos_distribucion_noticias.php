<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOMBRE_OPCION = 'Noticias — Correos de distribución';
    private const ID_MODULO_GESTION_HUMANA = 3;
    // Solo Super Admin de entrada — administra las direcciones reales de envío masivo
    // (ver MailRateLimitException/NoticiasService::GRUPOS_DISTRIBUCION). Un admin con
    // acceso a "NEWS Royal" (69) puede otorgar esta opción a más perfiles después desde
    // /permisos, sin volver a tocar código.
    private const PERFILES_CON_ACCESO = [1];

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
