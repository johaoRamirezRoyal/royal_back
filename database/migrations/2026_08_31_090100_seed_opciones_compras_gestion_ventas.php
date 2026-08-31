<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOMBRE_OPCION_GESTION = 'Compras — Gestión de compras';
    private const NOMBRE_OPCION_VENTAS = 'Compras — Ventas';
    private const ID_MODULO_PROCESO_COMPRA = 9;
    private const PERFIL_SUPER_ADMIN = 1;
    private const PERFIL_ADMIN = 2;

    /**
     * El id_perfil de Compras/Ventas lo asignó el autoincrement en la migración anterior
     * (seed_perfiles_compras_ventas) — se busca por nombre en vez de hardcodear el número,
     * igual que `down()` ya hace con `cron_opciones` por nombre en el resto del repo.
     */
    public function up(): void
    {
        $idPerfilCompras = DB::table('perfiles')->where('nombre', 'Compras')->value('id_perfil');
        $idPerfilVentas = DB::table('perfiles')->where('nombre', 'Ventas')->value('id_perfil');

        $idOpcionGestion = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE_OPCION_GESTION,
            'id_modulo' => self::ID_MODULO_PROCESO_COMPRA,
            'activo' => 1,
            'fechareg' => now(),
        ]);

        $idOpcionVentas = DB::table('cron_opciones')->insertGetId([
            'nombre' => self::NOMBRE_OPCION_VENTAS,
            'id_modulo' => self::ID_MODULO_PROCESO_COMPRA,
            'activo' => 1,
            'fechareg' => now(),
        ]);

        $permisos = [
            [$idOpcionGestion, self::PERFIL_SUPER_ADMIN],
            [$idOpcionGestion, self::PERFIL_ADMIN],
            [$idOpcionGestion, $idPerfilCompras],
            [$idOpcionVentas, self::PERFIL_SUPER_ADMIN],
            [$idOpcionVentas, self::PERFIL_ADMIN],
            [$idOpcionVentas, $idPerfilVentas],
        ];

        foreach ($permisos as [$idOpcion, $idPerfil]) {
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
        foreach ([self::NOMBRE_OPCION_GESTION, self::NOMBRE_OPCION_VENTAS] as $nombre) {
            $idOpcion = DB::table('cron_opciones')->where('nombre', $nombre)->value('id');

            if ($idOpcion) {
                DB::table('cron_permisos')->where('id_opcion', $idOpcion)->delete();
                DB::table('cron_opciones')->where('id', $idOpcion)->delete();
            }
        }
    }
};
