<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de los 4 niveles académicos, separado de la tabla `nivel` (que mezcla
     * niveles académicos con categorías no académicas de usuario como Administrativo/
     * Acudiente/Operativo/Egresado). `nivel` gana una FK nullable a esta tabla en la
     * siguiente migración — así "es un nivel académico" pasa a ser un join real en vez del
     * filtro por nombre en string que usaba el frontend (NIVELES_ACADEMICOS.includes(n.label),
     * frágil ante un rename de nivel.nombre).
     *
     * El nombre completo/descriptivo vive acá (nivel_academico.nombre); `nivel.nombre`
     * mantiene su forma corta original (Preescolar/Primaria/Secundaria/Bachillerato) — ver
     * 2026_08_25_020000_add_id_nivel_academico_to_nivel_table, que además revierte
     * nivel.nombre a esa forma corta si un rename manual anterior ya lo había cambiado.
     */
    public function up(): void
    {
        Schema::create('nivel_academico', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();
            $table->string('nombre', 50);
        });

        DB::table('nivel_academico')->insert([
            ['id' => 1, 'nombre' => 'Educación preescolar'],
            ['id' => 2, 'nombre' => 'Educación básica primaria'],
            ['id' => 3, 'nombre' => 'Educación básica secundaria'],
            ['id' => 4, 'nombre' => 'Educación media'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('nivel_academico');
    }
};
