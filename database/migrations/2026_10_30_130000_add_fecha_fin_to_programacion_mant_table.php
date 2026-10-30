<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `programacion_mant.fecha_fin`: último día de la programación (fecha_registro es el primero),
 * para pintarla como una sola franja en el calendario. NULL = un solo día (todo lo del legacy).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode, 'NO_ZERO_DATE', ''))");

        if (! Schema::hasColumn('programacion_mant', 'fecha_fin')) {
            DB::statement('ALTER TABLE programacion_mant ADD fecha_fin DATE NULL AFTER fecha_registro');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE programacion_mant DROP COLUMN fecha_fin');
    }
};
