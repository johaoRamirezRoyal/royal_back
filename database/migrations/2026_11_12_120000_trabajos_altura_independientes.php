<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trabajos en altura creados desde Gestión Humana sin reporte ni mantenimiento: `id_reporte`
 * pasa a opcional y el trabajo guarda sus propios datos del permiso (tipo, ubicación, elemento,
 * descripción). Altura aproximada, hora de inicio y fecha de finalización son opcionales para
 * cualquier trabajo y, si se llenan, salen impresos en el permiso. `bloques` es MyISAM: sin FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajos_altura', function (Blueprint $table) {
            $table->integer('id_reporte')->nullable()->change();
            $table->string('tipo_trabajo', 100)->nullable()->after('fecha_trabajo');
            $table->unsignedBigInteger('id_bloque')->nullable()->after('tipo_trabajo');
            $table->integer('id_area')->nullable()->after('id_bloque');
            $table->string('elemento', 190)->nullable()->after('id_area');
            $table->text('descripcion')->nullable()->after('elemento');
            $table->decimal('altura_aproximada', 5, 2)->nullable()->after('descripcion');
            $table->time('hora_inicio')->nullable()->after('altura_aproximada');
            $table->date('fecha_finalizacion')->nullable()->after('hora_inicio');

            $table->foreign('id_area')->references('id')->on('areas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trabajos_altura', function (Blueprint $table) {
            $table->dropForeign(['id_area']);
            $table->dropColumn(['tipo_trabajo', 'id_bloque', 'id_area', 'elemento', 'descripcion', 'altura_aproximada', 'hora_inicio', 'fecha_finalizacion']);
            $table->integer('id_reporte')->nullable(false)->change();
        });
    }
};
