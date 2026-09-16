<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Antes, NoticiasService::resolverDestinatariosDistribucion adivinaba el grupo de
     * `correos_institucionales` a partir del `nombre` de la fila `nivel` (match exacto
     * en código, ver commit anterior) — frágil ante un rename y no editable sin tocar
     * código. Esta columna hace explícita esa asociación y la deja administrable desde
     * la pantalla "Correos de distribución" (Noticias). Backfill: mismo mapeo por nombre
     * que ya estaba validado en producción, para no perder el comportamiento actual.
     */
    public function up(): void
    {
        Schema::table('nivel', function (Blueprint $table) {
            $table->string('grupo_correo_distribucion', 60)->nullable()->after('id_nivel_academico');
        });

        DB::table('nivel')->where('nombre', 'Preescolar')->update(['grupo_correo_distribucion' => 'NOTICIAS_PREESCOLAR']);
        DB::table('nivel')->where('nombre', 'Primaria')->update(['grupo_correo_distribucion' => 'NOTICIAS_PRIMARIA']);
        DB::table('nivel')->where('nombre', 'Secundaria')->update(['grupo_correo_distribucion' => 'NOTICIAS_SECUNDARIA']);
        DB::table('nivel')->where('nombre', 'Administrativo')->update(['grupo_correo_distribucion' => 'NOTICIAS_ADMINISTRATIVO']);
    }

    public function down(): void
    {
        Schema::table('nivel', function (Blueprint $table) {
            $table->dropColumn('grupo_correo_distribucion');
        });
    }
};
