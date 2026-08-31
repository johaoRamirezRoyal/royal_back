<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const NOMBRE_OPCION_GESTION = 'Compras — Gestión de compras';

    /**
     * El perfil "Compras" resultó innecesario: Ventas asume también la gestión de
     * seguimiento (confirmar solicitud/asignar proveedor, disponible en stock, anular),
     * además de lo que ya tenía (cambiar estado tras la entrega). Se elimina el perfil y
     * se traslada su permiso sobre la opción de gestión a Ventas.
     */
    public function up(): void
    {
        $idPerfilCompras = DB::table('perfiles')->where('nombre', 'Compras')->value('id_perfil');
        $idPerfilVentas = DB::table('perfiles')->where('nombre', 'Ventas')->value('id_perfil');
        $idOpcionGestion = DB::table('cron_opciones')->where('nombre', self::NOMBRE_OPCION_GESTION)->value('id');

        if ($idOpcionGestion && $idPerfilVentas) {
            DB::table('cron_permisos')->updateOrInsert(
                ['id_opcion' => $idOpcionGestion, 'id_perfil' => $idPerfilVentas],
                ['activo' => 1, 'fechareg' => now()],
            );
        }

        if ($idPerfilCompras) {
            DB::table('cron_permisos')->where('id_perfil', $idPerfilCompras)->delete();
            DB::table('perfiles')->where('id_perfil', $idPerfilCompras)->delete();
        }
    }

    public function down(): void
    {
        $idPerfilVentas = DB::table('perfiles')->where('nombre', 'Ventas')->value('id_perfil');
        $idOpcionGestion = DB::table('cron_opciones')->where('nombre', self::NOMBRE_OPCION_GESTION)->value('id');

        $idPerfilCompras = DB::table('perfiles')->insertGetId([
            'nombre' => 'Compras',
            'user_log' => 1,
            'id_super_empresa' => 1,
            'estado' => 'activo',
            'fechareg' => now(),
        ]);

        if ($idOpcionGestion) {
            DB::table('cron_permisos')->insert([
                'id_opcion' => $idOpcionGestion,
                'id_perfil' => $idPerfilCompras,
                'activo' => 1,
                'fechareg' => now(),
            ]);

            if ($idPerfilVentas) {
                DB::table('cron_permisos')
                    ->where('id_opcion', $idOpcionGestion)
                    ->where('id_perfil', $idPerfilVentas)
                    ->delete();
            }
        }
    }
};
