<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migración del "check" semestral de zonas del SAMI legacy (tabla chek_zonas,
 * ya no se toca) — un responsable certifica que un ítem de inventario de Área
 * Común (categoria.tipo_categoria=3) fue revisado en un periodo institucional.
 * Ver AGENTS.md "Áreas Comunes" para el mapeo legacy → actual.
 *
 * Sin índice único a nivel de motor (el legacy tampoco lo tenía) — la regla
 * "ya se hizo check este periodo" se valida en InventarioServices::registrarCheckInventario.
 * Columnas `int` simples (no bigint unsigned) para calzar con los tipos
 * legacy ya existentes de inventario.id/anio_escolar.id/usuarios.id_user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_check', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_inventario');
            $table->integer('id_anio')->nullable();
            $table->integer('periodo')->nullable();
            $table->integer('id_user')->nullable();
            $table->timestamp('fechareg')->useCurrent();

            $table->index('id_inventario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_check');
    }
};
