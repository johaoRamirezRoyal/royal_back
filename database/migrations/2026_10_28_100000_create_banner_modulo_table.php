<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Banners por módulo: un aviso por módulo del sidebar (ej. "Gestion Academica"), que se ve
 * solo dentro de ese módulo, aparte del banner informativo global (`banner_informativo`).
 * Transversal en `admin_management`, igual que el global: un Super Admin lo administra sin
 * importar la connection activa, y `dominio` lo restringe a un colegio (null = todos).
 * `modulo` es el nombre del módulo top-level del sidebar (modulePathMap en el frontend).
 */
return new class extends Migration
{
    protected $connection = 'admin_management';

    public function up(): void
    {
        Schema::create('banner_modulo', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->string('modulo', 100)->unique();
            $table->string('dominio', 190)->nullable();
            $table->text('mensaje')->nullable();
            $table->string('imagen', 500)->nullable();
            $table->string('imagen_public_id', 255)->nullable();
            $table->string('variante', 20)->default('info');
            $table->string('tamano', 5)->default('sm');
            $table->boolean('activo')->default(false);
            $table->timestamp('expira_en')->nullable();
            $table->boolean('mostrar_modal')->default(false);
            $table->unsignedInteger('actualizado_por')->nullable();
            $table->timestamp('fechareg')->useCurrent();
            $table->timestamp('fecha_updated')->nullable()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banner_modulo');
    }
};
