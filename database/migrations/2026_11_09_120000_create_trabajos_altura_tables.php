<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trabajos en altura: un reporte o mantenimiento de Áreas Comunes en altura puede tener
 * varios operativos con curso vigente (antes, uno solo en `reportes.id_operativo`). La
 * fecha del trabajo es propia (editable en Gestión Humana > Trabajos en altura) y no mueve
 * el reporte. Copia los `reportes.id_operativo` existentes; la columna queda sin uso hasta
 * borrarla en otra migración (BD compartida: producción aún la escribe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trabajos_altura', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_reporte')->unique();
            $table->date('fecha_trabajo');
            $table->integer('user_log')->nullable();
            $table->timestamp('fechareg')->useCurrent();

            $table->foreign('id_reporte')->references('id')->on('reportes')->cascadeOnDelete();
        });

        Schema::create('trabajos_altura_operativos', function (Blueprint $table) {
            $table->integer('id_trabajo');
            $table->integer('id_operativo');

            $table->primary(['id_trabajo', 'id_operativo']);
            $table->foreign('id_trabajo')->references('id')->on('trabajos_altura')->cascadeOnDelete();
            $table->foreign('id_operativo')->references('id_user')->on('usuarios');
        });

        DB::statement('INSERT INTO trabajos_altura (id_reporte, fecha_trabajo)
            SELECT id, DATE(CASE WHEN tipo_reporte = 2 THEN COALESCE(fecha_respuesta, fechareg) ELSE fechareg END)
            FROM reportes WHERE id_operativo IS NOT NULL AND id_reporte IS NULL');

        DB::statement('INSERT INTO trabajos_altura_operativos (id_trabajo, id_operativo)
            SELECT ta.id, rp.id_operativo FROM trabajos_altura ta JOIN reportes rp ON rp.id = ta.id_reporte');
    }

    public function down(): void
    {
        Schema::dropIfExists('trabajos_altura_operativos');
        Schema::dropIfExists('trabajos_altura');
    }
};
