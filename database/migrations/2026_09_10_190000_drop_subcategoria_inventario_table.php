<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Revierte 2026_09_10_100000_create_subcategoria_inventario_table — decisión de
 * producto: no hace falta una tabla/columna aparte para la subcategoría de
 * inventario, `categoria.tipo_categoria` ya sirve (1=Sistemas, 2=Operativo,
 * 3=Área Común, ver "Áreas Comunes" en AGENTS.md). id_subcategoria nunca llegó
 * a ser la fuente de verdad en el código (se mantenía solo en espejo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categoria', function (Blueprint $table) {
            $table->dropForeign(['id_subcategoria']);
            $table->dropColumn('id_subcategoria');
        });

        Schema::dropIfExists('subcategoria_inventario');
    }

    public function down(): void
    {
        Schema::create('subcategoria_inventario', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->boolean('activo')->default(true);
        });

        Schema::table('categoria', function (Blueprint $table) {
            $table->unsignedBigInteger('id_subcategoria')->nullable()->after('tipo_categoria');
            $table->foreign('id_subcategoria')->references('id')->on('subcategoria_inventario');
        });
    }
};
