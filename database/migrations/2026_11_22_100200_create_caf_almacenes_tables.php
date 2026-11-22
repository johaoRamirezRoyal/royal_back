<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cafetería, etapa 1: almacenes y de cuáles se abastece cada punto de venta.
 * - `id_cafeteria` NULL = almacén central de la institución (puede abastecer a cualquier punto).
 * - Un solo almacén predeterminado por punto: `predeterminado_clave` vale el id del punto solo
 *   en la fila predeterminada (NULL en las demás) y es UNIQUE. Un UNIQUE sobre
 *   (id_punto_venta, es_predeterminado) también impediría tener dos filas no predeterminadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caf_almacenes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('id_cafeteria')->nullable()->constrained('caf_cafeterias')->restrictOnDelete();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 120);
            $table->string('tipo', 20);
            $table->string('descripcion', 255)->nullable();
            $table->string('estado', 20)->default('ACTIVO');
            $table->integer('creado_por')->index();
            $table->timestamps();
        });

        Schema::create('caf_puntos_venta_almacenes', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->foreignId('id_punto_venta')->constrained('caf_puntos_venta')->cascadeOnDelete();
            $table->foreignId('id_almacen')->constrained('caf_almacenes')->restrictOnDelete();
            $table->boolean('es_predeterminado')->default(false);
            $table->unsignedBigInteger('predeterminado_clave')->nullable()
                ->storedAs('IF(es_predeterminado, id_punto_venta, NULL)');

            $table->primary(['id_punto_venta', 'id_almacen']);
            $table->unique('predeterminado_clave', 'uq_caf_pva_predeterminado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caf_puntos_venta_almacenes');
        Schema::dropIfExists('caf_almacenes');
    }
};
