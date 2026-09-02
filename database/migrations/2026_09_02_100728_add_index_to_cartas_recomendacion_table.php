<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * listarMisCartas filtra por id_institucion y ordena por created_at desc; sin un índice
 * que cubra ambos, MySQL resuelve el ORDER BY con filesort, que sobre esta tabla (columna
 * `datos` JSON sin límite de tamaño) agota el sort buffer del servidor
 * ("SQLSTATE[HY001]: Out of sort memory"). Este índice compuesto deja que el ORDER BY se
 * resuelva directo desde el índice, sin filesort.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartas_recomendacion', function (Blueprint $table) {
            $table->index(['id_institucion', 'created_at'], 'cartas_recomendacion_institucion_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('cartas_recomendacion', function (Blueprint $table) {
            $table->dropIndex('cartas_recomendacion_institucion_created_idx');
        });
    }
};
