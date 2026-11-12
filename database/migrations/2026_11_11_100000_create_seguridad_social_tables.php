<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seguridad social (EPS/AFP/ARL) de los trabajadores con curso de altura (Gestión Humana,
 * opción 150). Dos tablas: un catálogo único para los tres tipos de entidad y una fila por
 * trabajador con una columna por tipo (entidad + documento de soporte opcional).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entidades_seguridad_social', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->enum('tipo', ['EPS', 'AFP', 'ARL']);
            $table->string('nombre', 150);
            $table->tinyInteger('activo')->default(1);
            $table->integer('user_log')->nullable();
            $table->timestamp('fechareg')->useCurrent();

            $table->unique(['tipo', 'nombre']);
        });

        Schema::create('seguridad_social_trabajador', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_user')->unique();
            foreach (['eps', 'afp', 'arl'] as $tipo) {
                $table->integer("id_{$tipo}")->nullable();
                $table->string("ruta_{$tipo}", 255)->nullable();
                $table->foreign("id_{$tipo}")->references('id')->on('entidades_seguridad_social');
            }
            $table->integer('user_log')->nullable();
            $table->timestamp('fechareg')->useCurrent();

            $table->foreign('id_user')->references('id_user')->on('usuarios');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seguridad_social_trabajador');
        Schema::dropIfExists('entidades_seguridad_social');
    }
};
