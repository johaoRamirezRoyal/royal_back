<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tamaño de texto del banner informativo, elegible por el Super Admin junto con el
 * mensaje/variante — default 'sm' para no cambiar el tamaño del banner ya configurado
 * (mismo valor que el `text-theme-xs` fijo que traía antes de esta columna).
 */
return new class extends Migration
{
    /** Tabla transversal en `admin_management` — ver create_banner_informativo_table. */
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::table('banner_informativo', function (Blueprint $table) {
            $table->string('tamano', 10)->default('sm')->after('variante');
        });
    }

    public function down(): void
    {
        Schema::table('banner_informativo', function (Blueprint $table) {
            $table->dropColumn('tamano');
        });
    }
};
