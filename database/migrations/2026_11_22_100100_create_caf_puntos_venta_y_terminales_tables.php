<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cafetería, etapa 1: puntos de venta (lugar físico de atención) de cada cafetería y las
 * terminales (caja lógica o dispositivo) de cada punto. Las sesiones de caja y las ventas
 * llegan en una etapa posterior. Estados: App\Enums\Cafeteria\EstadoPuntoVenta (los dos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caf_puntos_venta', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('id_cafeteria')->constrained('caf_cafeterias')->restrictOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre', 120);
            $table->string('tipo', 20);
            $table->string('ubicacion', 200)->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();

            $table->unique(['id_cafeteria', 'codigo']);
        });

        Schema::create('caf_terminales_venta', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('id_punto_venta')->constrained('caf_puntos_venta')->restrictOnDelete();
            $table->string('codigo', 30);
            $table->string('nombre', 120);
            $table->string('identificador_dispositivo', 120)->nullable()->unique();
            $table->string('estado', 20)->default('ACTIVO');
            $table->timestamps();

            $table->unique(['id_punto_venta', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caf_terminales_venta');
        Schema::dropIfExists('caf_puntos_venta');
    }
};
