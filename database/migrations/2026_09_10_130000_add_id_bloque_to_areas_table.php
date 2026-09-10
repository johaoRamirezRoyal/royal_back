<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Relación área → bloque (un área pertenece a lo sumo a un bloque). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_bloque')->nullable()->after('nombre');
            $table->foreign('id_bloque')->references('id')->on('bloques');
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropForeign(['id_bloque']);
            $table->dropColumn('id_bloque');
        });
    }
};
