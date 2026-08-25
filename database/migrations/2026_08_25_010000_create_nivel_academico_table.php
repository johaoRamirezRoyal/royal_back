<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de los 4 niveles académicos (Preescolar/Primaria/Secundaria/Media), separado
     * de la tabla `nivel` (que mezcla niveles académicos con categorías no académicas de
     * usuario como Administrativo/Acudiente/Operativo/Egresado). `nivel` gana una FK
     * nullable a esta tabla en la siguiente migración — así "es un nivel académico" pasa a
     * ser un join real en vez del filtro por nombre en string que usaba el frontend
     * (NIVELES_ACADEMICOS.includes(n.label), frágil ante un rename de nivel.nombre).
     */
    public function up(): void
    {
        Schema::create('nivel_academico', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();
            $table->string('nombre', 50);
        });

        DB::table('nivel_academico')->insert([
            ['id' => 1, 'nombre' => 'Preescolar'],
            ['id' => 2, 'nombre' => 'Primaria'],
            ['id' => 3, 'nombre' => 'Secundaria'],
            ['id' => 4, 'nombre' => 'Media'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('nivel_academico');
    }
};
