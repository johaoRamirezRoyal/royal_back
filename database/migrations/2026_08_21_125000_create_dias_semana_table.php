<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de días de la semana usado por academico_franja_horaria. Tabla base del
     * módulo de Gestión Académica que, como el resto de tablas academico_*, no tenía
     * migración propia en este repo (creada directamente en la BD compartida) — esta la
     * agrega para que un entorno local nuevo también pueda levantar el módulo completo.
     * Orden 1=Lunes...7=Domingo, misma convención que usa el frontend
     * (src/pages/academic/AcademicConfig/hook/useFranjasHorariasTab.hook.ts).
     */
    public function up(): void
    {
        Schema::create('dias_semana', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary()->index();
            $table->string('nombre', 20);
            $table->string('abreviatura', 10);
            $table->integer('orden');
        });

        DB::table('dias_semana')->insert([
            ['id' => 1, 'nombre' => 'Lunes', 'abreviatura' => 'LUN', 'orden' => 1],
            ['id' => 2, 'nombre' => 'Martes', 'abreviatura' => 'MAR', 'orden' => 2],
            ['id' => 3, 'nombre' => 'Miércoles', 'abreviatura' => 'MIE', 'orden' => 3],
            ['id' => 4, 'nombre' => 'Jueves', 'abreviatura' => 'JUE', 'orden' => 4],
            ['id' => 5, 'nombre' => 'Viernes', 'abreviatura' => 'VIE', 'orden' => 5],
            ['id' => 6, 'nombre' => 'Sábado', 'abreviatura' => 'SAB', 'orden' => 6],
            ['id' => 7, 'nombre' => 'Domingo', 'abreviatura' => 'DOM', 'orden' => 7],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('dias_semana');
    }
};
