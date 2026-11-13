<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índice para los filtros del listado paginado de /indicadores-gestion (categoría, año,
 * periodo) y el chequeo de duplicado de IndicadoresGestionServices::crear().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasIndex('indicadores_gestion', 'indicadores_gestion_categoria_anio_periodo_index')) {
            return;
        }

        Schema::table('indicadores_gestion', function (Blueprint $table) {
            $table->index(['categoria', 'anio', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::table('indicadores_gestion', function (Blueprint $table) {
            $table->dropIndex(['categoria', 'anio', 'periodo']);
        });
    }
};
