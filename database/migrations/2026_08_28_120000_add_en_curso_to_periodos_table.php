<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `periodos.activo` no identifica de forma confiable "el periodo vigente ahora
     * mismo" — en la práctica hay varios años escolares y periodos marcados
     * `activo=1` a la vez (histórico sin depurar), y el año escolar "más reciente
     * activo" (AnioEscolarServices::obtenerUltimoAnioEscolar) puede no tener ningún
     * periodo propio, dejando sin resolver "¿qué periodo está en curso?" (ver
     * EvaluacionesServices::resolverPeriodoActivo). `en_curso` es explícito y no se
     * deriva de nada más: exactamente un periodo (o ninguno) debe tenerlo en 1 en un
     * momento dado — no hay CRUD para esta tabla todavía, se administra a mano.
     */
    public function up(): void
    {
        Schema::table('periodos', function (Blueprint $table) {
            $table->boolean('en_curso')->default(false)->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('periodos', function (Blueprint $table) {
            $table->dropColumn('en_curso');
        });
    }
};
