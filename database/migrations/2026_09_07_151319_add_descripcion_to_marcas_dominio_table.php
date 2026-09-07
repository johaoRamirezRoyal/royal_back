<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Vive en `admin_management` (ver 2026_08_26_100000_create_marcas_dominio_table). */
    protected $connection = 'admin_management';

    /** Lema/tagline propio de la institución (ej. "Intelligence = Problem Solving") —
     * se muestra en el panel decorativo del Login cuando hay marca resuelta para el
     * dominio; sin ella, el Login cae al lema genérico de OMNIA como plataforma. */
    public function up(): void
    {
        Schema::table('marcas_dominio', function (Blueprint $table) {
            $table->string('descripcion', 190)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('marcas_dominio', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
