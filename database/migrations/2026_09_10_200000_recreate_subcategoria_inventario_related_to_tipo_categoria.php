<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recrea subcategoria_inventario (catálogo: 1=Sistemas, 2=Operativo, 3=Área
 * Común) — pero, a diferencia del primer intento
 * (2026_09_10_100000_create_subcategoria_inventario_table, revertido en
 * 2026_09_10_190000), esta vez SIN columna nueva en `categoria`: la relación
 * es una FK real sobre la columna que ya existe, `categoria.tipo_categoria`
 * → `subcategoria_inventario.id`. `tipo_categoria` sigue siendo la única
 * fuente de verdad; esta tabla solo le da nombre/catálogo a sus valores.
 *
 * `id` es `int` (no bigint unsigned) a propósito: tipo_categoria es
 * `int(11)` con signo (legacy) — un PK bigint unsigned rompe la FK por
 * incompatibilidad de tipos (mismo problema ya visto con bloques.id_nivel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcategoria_inventario', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true);
        });

        DB::table('subcategoria_inventario')->insert([
            ['id' => 1, 'nombre' => 'Sistemas', 'activo' => 1],
            ['id' => 2, 'nombre' => 'Operativo', 'activo' => 1],
            ['id' => 3, 'nombre' => 'Área Común', 'activo' => 1],
        ]);

        Schema::table('categoria', function (Blueprint $table) {
            $table->foreign('tipo_categoria')
                ->references('id')->on('subcategoria_inventario');
        });
    }

    public function down(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            $table->dropForeign(['tipo_categoria']);
        });

        Schema::dropIfExists('subcategoria_inventario');
    }
};
