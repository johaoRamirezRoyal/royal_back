<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `reportes.evidencia` (longblob legacy, vacía en todas las filas y sin uso en el código)
 * pasa a ser `id_operativo`: el operativo con curso de alturas vigente asignado a un
 * mantenimiento o reporte de daño de Áreas Comunes en altura. Raw ALTER porque no está
 * doctrine/dbal.
 */
return new class extends Migration
{
    public function up(): void
    {
        // El ALTER y la FK reconstruyen/revalidan la tabla contra el sql_mode actual: filas
        // legacy con fechareg='0000-00-00 00:00:00' rompen eso bajo NO_ZERO_DATE. Se relaja
        // solo para esta sesión (igual que add_id_bloque_to_inventario_table).
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode, 'NO_ZERO_DATE', ''))");

        DB::statement('ALTER TABLE reportes CHANGE evidencia id_operativo INT NULL');

        Schema::table('reportes', function (Blueprint $table) {
            $table->foreign('id_operativo')->references('id_user')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode, 'NO_ZERO_DATE', ''))");

        Schema::table('reportes', function (Blueprint $table) {
            $table->dropForeign(['id_operativo']);
        });

        DB::statement('ALTER TABLE reportes CHANGE id_operativo evidencia LONGBLOB NULL');
    }
};
