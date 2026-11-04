<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * curso.id_director: el docente director de grupo del curso (usuarios.id_user, perfil Docente o
 * Docente ICT). Opcional. Un docente dirige un solo curso: asignarlo a otro lo reasigna
 * (CursosServices::reasignarDirector). Sin FK:
 * `curso` es MyISAM en algunas BD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('curso', function (Blueprint $table) {
            $table->integer('id_director')->nullable()->after('curso_proximo')->index();
        });
    }

    public function down(): void
    {
        Schema::table('curso', function (Blueprint $table) {
            $table->dropIndex(['id_director']);
            $table->dropColumn('id_director');
        });
    }
};
