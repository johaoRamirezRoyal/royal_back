<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cursos de trabajo en alturas de los operativos (Gestión Humana, opción 143). Un curso
 * está vigente si `fecha_expedicion <= hoy` y `fecha_finalizacion` es NULL o `>= hoy`; los
 * mantenimientos de Áreas Comunes en altura solo se asignan a operativos con uno vigente
 * (reportes.id_operativo). Columnas `int` simples para calzar con usuarios.id_user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos_altura', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_user');
            $table->string('ruta_certificado', 255);
            $table->date('fecha_expedicion');
            $table->date('fecha_finalizacion')->nullable();
            $table->integer('user_log')->nullable();
            $table->tinyInteger('activo')->default(1);
            $table->timestamp('fechareg')->useCurrent();

            $table->index(['id_user', 'fecha_finalizacion']);
            $table->foreign('id_user')->references('id_user')->on('usuarios');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos_altura');
    }
};
