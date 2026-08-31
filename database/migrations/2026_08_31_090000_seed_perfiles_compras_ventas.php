<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const PERFIL_COMPRAS = 'Compras';
    private const PERFIL_VENTAS = 'Ventas';

    /**
     * `perfiles` no se seedea vía Eloquent en este repo (tabla legacy manejada directo
     * por SQL, sin migración propia) — se inserta igual que cualquier otra fila
     * existente, vía DB::table(). El id_perfil resultante lo asigna el autoincrement;
     * se lee por nombre en la migración de permisos siguiente en vez de hardcodearlo.
     */
    public function up(): void
    {
        foreach ([self::PERFIL_COMPRAS, self::PERFIL_VENTAS] as $nombre) {
            DB::table('perfiles')->insert([
                'nombre' => $nombre,
                // Mismos valores que el resto de filas existentes en `perfiles` (estado
                // es varchar 'activo'/'inactivo', no un int; user_log/id_super_empresa
                // apuntan al Super Admin, igual que los perfiles sembrados originalmente).
                'user_log' => 1,
                'id_super_empresa' => 1,
                'estado' => 'activo',
                'fechareg' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('perfiles')->whereIn('nombre', [self::PERFIL_COMPRAS, self::PERFIL_VENTAS])->delete();
    }
};
