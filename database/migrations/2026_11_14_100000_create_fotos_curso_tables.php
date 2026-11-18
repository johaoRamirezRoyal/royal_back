<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fotos del curso (portal para padres): un docente publica fotos de una clase o actividad y los
 * acudientes las ven en la semana de su hijo durante el año escolar de la publicación. Sin
 * aprobación previa; el autor (o quien tenga la opción 174) la borra. Las fotos son archivos
 * privados (`fotos-curso/`, ver ArchivosPrivados).
 *
 * - Una publicación es de una asignatura (`id_asignatura`; null = actividad del curso, sin
 *   asignatura) y puede ir a VARIOS cursos (`fotos_curso_publicacion_curso`): las fotos se suben
 *   una sola vez aunque la clase o la salida haya sido con dos cursos.
 * - Sin FK a `curso` ni a `academico_asignatura` (MyISAM en algunas bases) ni a `usuarios` (legacy).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fotos_curso_publicaciones', function (Blueprint $table) {
            $table->id();
            $table->integer('id_anio_escolar');
            $table->integer('id_docente'); // quien publica (usuarios.id_user)
            $table->integer('id_asignatura')->nullable(); // null: actividad del curso, sin asignatura
            $table->date('fecha');         // día de la clase o actividad, no el de la subida
            $table->string('descripcion', 500)->nullable();
            // El docente confirmó que no aparecen estudiantes sin autorización de imagen.
            $table->boolean('confirma_autorizacion')->default(false);
            $table->timestamps();

            $table->index(['id_anio_escolar', 'fecha']);
            $table->index('id_docente');
        });

        Schema::create('fotos_curso_publicacion_curso', function (Blueprint $table) {
            $table->foreignId('id_publicacion')->constrained('fotos_curso_publicaciones')->cascadeOnDelete();
            $table->integer('id_curso');

            $table->primary(['id_publicacion', 'id_curso']);
            $table->index('id_curso');
        });

        Schema::create('fotos_curso_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_publicacion')->constrained('fotos_curso_publicaciones')->cascadeOnDelete();
            $table->string('ruta', 255); // ruta en el disco de uploads (FileStorageService)
            $table->unsignedTinyInteger('orden')->default(0);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fotos_curso_fotos');
        Schema::dropIfExists('fotos_curso_publicacion_curso');
        Schema::dropIfExists('fotos_curso_publicaciones');
    }
};
