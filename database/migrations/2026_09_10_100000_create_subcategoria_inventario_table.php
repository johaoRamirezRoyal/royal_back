<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de subcategorías de inventario (Sistemas/Operativo/Área Común), para
 * reemplazar de a poco el `categoria.tipo_categoria` legacy (int fijo 1|2, sin
 * catálogo propio). Se agrega `categoria.id_subcategoria` en paralelo y se
 * mantiene sincronizado con `tipo_categoria` — ver AGENTS.md "Áreas Comunes".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcategoria_inventario', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true);
        });

        DB::table('subcategoria_inventario')->insert([
            ['id' => 1, 'nombre' => 'Sistemas', 'activo' => 1],
            ['id' => 2, 'nombre' => 'Operativo', 'activo' => 1],
            ['id' => 3, 'nombre' => 'Área Común', 'activo' => 1],
        ]);

        Schema::table('categoria', function (Blueprint $table) {
            $table->unsignedBigInteger('id_subcategoria')->nullable()->after('tipo_categoria');
            $table->foreign('id_subcategoria')->references('id')->on('subcategoria_inventario');
        });

        // Backfill: hoy tipo_categoria solo tiene los valores 1 (Sistemas) y 2
        // (Operativos) — coinciden 1:1 con los ids sembrados arriba.
        DB::table('categoria')->where('tipo_categoria', 1)->update(['id_subcategoria' => 1]);
        DB::table('categoria')->where('tipo_categoria', 2)->update(['id_subcategoria' => 2]);
    }

    public function down(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            $table->dropForeign(['id_subcategoria']);
            $table->dropColumn('id_subcategoria');
        });

        Schema::dropIfExists('subcategoria_inventario');
    }
};
