<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'admin_management';

    /**
     * Antes de esta feature, `/admissions` era siempre Royal School (base `mysql`) sin
     * ninguna resolución de por medio — se sigue así por defecto seedeando el slug
     * "royalschool" apuntando a `mysql`, para que el redirect legacy `/admissions` ->
     * `/royalschool/admissions` (ver router del frontend) siga funcionando sin
     * intervención manual. Sin logo/color propios todavía (nullable, ver migración de la
     * tabla) — cae al genérico de OMNIA hasta que un Super Admin suba el logo real desde
     * el nuevo panel de "Colegios de admisión".
     */
    public function up(): void
    {
        DB::connection('admin_management')->table('colegios_admision')->insertOrIgnore([
            'slug' => 'royalschool',
            'nombre' => 'Royal School',
            'connection' => 'mysql',
            'activo' => true,
            'fechareg' => now(),
        ]);
    }

    public function down(): void
    {
        DB::connection('admin_management')->table('colegios_admision')->where('slug', 'royalschool')->delete();
    }
};
