<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Config singleton (id=1) para el módulo de Reservas — mismo patrón que
 * configuracion_instituciones: `correo_notificacion` son los correos que reciben SIEMPRE
 * una notificación al reservar cualquier salón, además de los correos propios de cada
 * salón (`salones.correo_notificacion`, ver migración hermana).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_reservas', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->string('correo_notificacion', 500)->nullable();
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });

        DB::table('configuracion_reservas')->insert(['id' => 1, 'correo_notificacion' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_reservas');
    }
};
