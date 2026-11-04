<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * cursos_promociones: cada promoción de estudiantes al curso próximo (CursosServices::promover),
 * con el año escolar que cerró. La página de inicio la usa para avisar, al terminar el año, que
 * todavía no se ha promovido. Sin FK a anio_escolar: su motor no es igual en todas las BD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos_promociones', function (Blueprint $table) {
            $table->id();
            $table->integer('id_anio_escolar')->index();
            $table->unsignedInteger('total');
            $table->integer('id_log')->nullable();
            $table->dateTime('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos_promociones');
    }
};
