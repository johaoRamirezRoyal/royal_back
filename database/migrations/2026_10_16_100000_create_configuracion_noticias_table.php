<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Config singleton (id=1) del módulo de Noticias — mismo patrón que
 * configuracion_reservas. `enviar_correos_programadas`: interruptor de la pestaña
 * "Noticias programadas" para apagar el envío por correo de los cumpleaños sin
 * desactivar las noticias (siguen mostrándose en el Home). Ver
 * NoticiasService::enviarProgramadosDelDia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_noticias', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->boolean('enviar_correos_programadas')->default(true);
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });

        DB::table('configuracion_noticias')->insert(['id' => 1]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_noticias');
    }
};
