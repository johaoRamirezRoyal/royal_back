<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Quien con la opción 133/99 registra una observación elige a qué docente corresponde:
     * id_docente pasa a ser "el docente en cuestión" e id_usuario_registra guarda quién la
     * digitó (quien puede editarla/eliminarla). Las existentes las registró su propio docente.
     */
    public function up(): void
    {
        Schema::table('academico_observacion_estudiante', function (Blueprint $table) {
            $table->integer('id_usuario_registra')->nullable()->after('id_docente');
            $table->foreign('id_usuario_registra')->references('id_user')->on('usuarios');
        });

        DB::table('academico_observacion_estudiante')->update(['id_usuario_registra' => DB::raw('id_docente')]);
    }

    public function down(): void
    {
        Schema::table('academico_observacion_estudiante', function (Blueprint $table) {
            $table->dropForeign(['id_usuario_registra']);
            $table->dropColumn('id_usuario_registra');
        });
    }
};
