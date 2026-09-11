<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permite un ítem de inventario asignado directo a un bloque, sin pasar por un
 * área (ej. equipos de una zona común que no tiene salón asociado).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Agregar una FK obliga a MySQL a revalidar la tabla completa contra el
        // sql_mode actual — filas legacy con fecha_compra='0000-00-00' rompen eso
        // bajo NO_ZERO_DATE. Se relaja solo para esta sesión/migración.
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode, 'NO_ZERO_DATE', ''))");

        Schema::table('inventario', function (Blueprint $table) {
            $table->unsignedBigInteger('id_bloque')->nullable()->after('id_area');
            $table->foreign('id_bloque')->references('id')->on('bloques');
        });
    }

    public function down(): void
    {
        Schema::table('inventario', function (Blueprint $table) {
            $table->dropForeign(['id_bloque']);
            $table->dropColumn('id_bloque');
        });
    }
};
