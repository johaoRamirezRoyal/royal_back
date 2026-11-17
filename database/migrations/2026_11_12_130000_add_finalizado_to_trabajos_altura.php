<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de los trabajos en altura creados en Gestión Humana: nacen pendientes y se marcan
 * finalizados (cuándo y quién) desde Trabajos en altura; se pueden reabrir. Los de un reporte
 * no lo usan: su estado sale del reporte (solucionado o no).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajos_altura', function (Blueprint $table) {
            $table->dateTime('finalizado_en')->nullable()->after('fecha_finalizacion');
            $table->integer('finalizado_por')->nullable()->after('finalizado_en');
        });
    }

    public function down(): void
    {
        Schema::table('trabajos_altura', function (Blueprint $table) {
            $table->dropColumn(['finalizado_en', 'finalizado_por']);
        });
    }
};
