<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academico_horario_clase', function (Blueprint $table) {
            $table->string('descripcion', 255)->nullable()->after('tipo');
        });
    }

    public function down(): void
    {
        Schema::table('academico_horario_clase', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
