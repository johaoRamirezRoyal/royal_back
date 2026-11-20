<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un nivel de usuario (`nivel`: Preescolar, Primaria, Secundaria…) puede abarcar varios niveles
 * académicos (Preescolar → Prejardín y Educación preescolar). Reemplaza el puente 1:1
 * `nivel.id_nivel_academico` (que queda como dato heredado) y el caso fijo en código de
 * Secundaria → Básica secundaria (3). Ver docs/contexto/modulos/gestion-academica.md.
 *
 * Sin FK a `nivel`: en algunas BD es MyISAM.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nivel_nivel_academico', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->integer('id_nivel');
            $table->integer('id_nivel_academico')->index();
            $table->foreign('id_nivel_academico')->references('id')->on('nivel_academico')->cascadeOnDelete();
            $table->primary(['id_nivel', 'id_nivel_academico']);
        });

        // Lo que ya había: el puente 1:1 y Secundaria (4) también en Básica secundaria (3).
        DB::statement('insert into nivel_nivel_academico (id_nivel, id_nivel_academico)
            select id, id_nivel_academico from nivel where id_nivel_academico is not null');
        if (DB::table('nivel')->where('id', 4)->exists() && DB::table('nivel_academico')->where('id', 3)->exists()) {
            DB::table('nivel_nivel_academico')->insertOrIgnore(['id_nivel' => 4, 'id_nivel_academico' => 3]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nivel_nivel_academico');
    }
};
