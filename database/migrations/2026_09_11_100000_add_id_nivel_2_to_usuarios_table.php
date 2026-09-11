<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Segundo nivel opcional para un usuario (ej. un docente que dicta tanto en
 * Primaria como en Secundaria) — independiente de `id_nivel`, sin reemplazarlo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->unsignedInteger('id_nivel_2')->nullable()->after('id_nivel');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('id_nivel_2');
        });
    }
};
