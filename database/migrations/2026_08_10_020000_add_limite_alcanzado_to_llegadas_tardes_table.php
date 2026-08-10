<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * true cuando, al crear esta fila, el total de llegadas tarde del alumno en el período
     * académico llegó a un múltiplo de 5 (dispara el correo a Vicerrectoría — ver
     * LlegadasTarde::agregarLlegadaTarde). Queda fijo en la fila en el momento del
     * registro, no se recalcula después.
     */
    public function up(): void
    {
        Schema::table('llegadas_tardes', function (Blueprint $table) {
            $table->boolean('limite_alcanzado')->default(false)->after('hora');
        });
    }

    public function down(): void
    {
        Schema::table('llegadas_tardes', function (Blueprint $table) {
            $table->dropColumn('limite_alcanzado');
        });
    }
};
