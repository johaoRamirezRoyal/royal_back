<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración de cumpleaños (Noticias → Noticias programadas → Configuración de
 * cumpleaños). Ver CumpleanosService:
 * - `cumpleanos_plantillas`: imagen base por género + posición del nombre y la foto
 *   (`elementos`, fracciones del ancho/alto de la imagen, ver CumpleanosImagen).
 * - `cumpleanos_generados`: una fila por persona y año — la noticia generada (o el error),
 *   para no generar dos veces el mismo cumpleaños.
 * - `cumpleanos_excluidos`: personal que no participa en la automatización.
 * - columnas `cumpleanos_*` en `configuracion_noticias`: interruptor, plantilla por género,
 *   anticipación y título de la noticia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cumpleanos_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->text('descripcion')->nullable();
            $table->string('genero', 10); // masculino | femenino
            $table->boolean('activo')->default(false);
            $table->string('imagen', 250)->nullable();
            $table->unsignedInteger('ancho')->nullable();
            $table->unsignedInteger('alto')->nullable();
            $table->boolean('transparente')->default(false);
            $table->json('elementos')->nullable();
            $table->integer('id_log')->nullable();
            $table->timestamps();
        });

        Schema::create('cumpleanos_generados', function (Blueprint $table) {
            $table->id();
            $table->integer('id_user');
            $table->unsignedSmallInteger('anio');
            $table->date('fecha');
            $table->unsignedBigInteger('id_plantilla')->nullable();
            $table->integer('id_mensaje')->nullable();
            $table->string('estado', 12); // generada | error
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['id_user', 'anio']);
        });

        Schema::create('cumpleanos_excluidos', function (Blueprint $table) {
            $table->integer('id_user')->primary();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('configuracion_noticias', function (Blueprint $table) {
            $table->boolean('cumpleanos_automatico')->default(false);
            $table->unsignedBigInteger('cumpleanos_plantilla_masculino')->nullable();
            $table->unsignedBigInteger('cumpleanos_plantilla_femenino')->nullable();
            $table->unsignedTinyInteger('cumpleanos_dias_anticipacion')->default(1);
            $table->string('cumpleanos_titulo', 200)->default('¡Feliz cumpleaños, {nombre}!');
        });
    }

    public function down(): void
    {
        Schema::table('configuracion_noticias', function (Blueprint $table) {
            $table->dropColumn([
                'cumpleanos_automatico',
                'cumpleanos_plantilla_masculino',
                'cumpleanos_plantilla_femenino',
                'cumpleanos_dias_anticipacion',
                'cumpleanos_titulo',
            ]);
        });
        Schema::dropIfExists('cumpleanos_excluidos');
        Schema::dropIfExists('cumpleanos_generados');
        Schema::dropIfExists('cumpleanos_plantillas');
    }
};
