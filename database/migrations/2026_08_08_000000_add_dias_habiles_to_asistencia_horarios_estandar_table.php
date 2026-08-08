<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Días de la semana (1=Lunes...7=Domingo, misma convención que id_dia_semana del
     * módulo académico) en los que aplica un horario estándar. Los horarios existentes
     * se backfillean con todos los días para no cambiarles el comportamiento hasta que
     * alguien los edite explícitamente.
     */
    public function up(): void
    {
        Schema::table('asistencia_horarios_estandar', function (Blueprint $table) {
            $table->json('dias_habiles')->nullable()->after('hora_salida_esperada');
        });

        DB::table('asistencia_horarios_estandar')->update([
            'dias_habiles' => json_encode([1, 2, 3, 4, 5, 6, 7]),
        ]);

        // NOT NULL vía SQL crudo: el ->change() de Blueprint requiere doctrine/dbal,
        // que este proyecto no tiene instalado.
        DB::statement('ALTER TABLE asistencia_horarios_estandar MODIFY dias_habiles JSON NOT NULL');
    }

    public function down(): void
    {
        Schema::table('asistencia_horarios_estandar', function (Blueprint $table) {
            $table->dropColumn('dias_habiles');
        });
    }
};
