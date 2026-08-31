<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fila única (id=1) que reemplaza los valores de config/instituciones.php
 * ('dias_plazo_bloqueo_correo', 'correo_notificacion', antes leídos de .env) — ahora
 * editables desde el módulo admin de Instituciones en vez de requerir tocar el .env y
 * reiniciar el servidor. Se siembra con los mismos valores por defecto para no cambiar
 * el comportamiento actual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracion_instituciones', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->unsignedInteger('dias_plazo_bloqueo_correo');
            $table->string('correo_notificacion')->nullable();
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });

        DB::table('configuracion_instituciones')->insert([
            'id' => 1,
            'dias_plazo_bloqueo_correo' => 7,
            'correo_notificacion' => null,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracion_instituciones');
    }
};
