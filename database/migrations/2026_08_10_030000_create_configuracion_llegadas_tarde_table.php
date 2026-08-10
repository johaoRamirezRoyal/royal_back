<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Fila única (id=1) que reemplaza las constantes hardcodeadas
     * HikvisionController::HORA_LIMITE_LLEGADA ('07:15:00') y
     * LlegadasTarde::LIMITE_LLEGADAS_TARDE (5). Se sembra con esos mismos valores para no
     * cambiar el comportamiento actual hasta que alguien lo edite desde la configuración.
     */
    public function up(): void
    {
        Schema::create('configuracion_llegadas_tarde', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->time('hora_limite');
            $table->unsignedInteger('cantidad_limite');
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });

        DB::table('configuracion_llegadas_tarde')->insert([
            'id' => 1,
            'hora_limite' => '07:15:00',
            'cantidad_limite' => 5,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_llegadas_tarde');
    }
};
