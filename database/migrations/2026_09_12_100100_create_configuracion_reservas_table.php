<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Config singleton (id=1) para el módulo de Reservas — mismo patrón que
 * configuracion_instituciones. Ver migración hermana
 * add_dias_anticipacion_to_configuracion_reservas_table para las columnas de la ventana
 * de anticipación (única razón de ser de esta tabla).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_reservas', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });

        DB::table('configuracion_reservas')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_reservas');
    }
};
