<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('periodo_academico', function (Blueprint $table) {
            // nullable: los periodos ya existentes no tienen nombre asignado.
            $table->string('nombre', 100)->nullable()->after('id_anio_escolar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('periodo_academico', function (Blueprint $table) {
            $table->dropColumn('nombre');
        });
    }
};
