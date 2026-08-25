<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Repunta lo académico (curso.id_nivel, academico_esquema_horario.id_nivel) hacia
     * nivel_academico en vez de nivel — nivel queda solo para clasificar usuarios y otros
     * módulos no académicos (ver evaluacion_profesores.id_nivel, que sigue apuntando a
     * `nivel` sin tocar).
     *
     * curso.id_nivel venía de un bucket ancho por nivel (nivel.id: 2=Preescolar,
     * 3=Primaria, 4=Bachillerato) que en la práctica mezclaba grados de niveles académicos
     * distintos bajo la misma etiqueta — ej. "Segundo C"/"Sexto A-C" estaban bajo
     * id_nivel=3 ("Primaria") aunque Sexto es académicamente Secundaria, y todo 6°-11°
     * compartía un solo esquema "Bachillerato". Acá se reclasifica cada curso por su grado
     * real (parseado del nombre) contra los 4 nivel_academico verdaderos, y se separa el
     * esquema "Bachillerato" en dos: Media (mismo esquema/franjas, solo renombrado — sigue
     * sirviendo 10°-11°) y un esquema "Secundaria" nuevo para 6°-9°, que antes no existía.
     *
     * Como consecuencia, los horario_clase de los cursos que pasan a Secundaria (Sexto a
     * Noveno) quedan apuntando a franjas del esquema viejo que ya no les corresponde — se
     * eliminan acá; FranjaHorarioSeeder + HorarioSeeder (ya actualizados con la lógica de
     * 4 niveles) los vuelven a sembrar contra el esquema Secundaria nuevo. Cursos sin grado
     * identificable en el nombre (Stem A/B, Estudiantes Graduados, Egresado) quedan con
     * id_nivel = null — nunca tuvieron un nivel académico real, solo heredaban el bucket
     * ancho en el que estuvieran guardados.
     */
    public function up(): void
    {
        $porNombre = [
            1 => ['Pre Kinder A', 'Pre Kinder B', 'Pre Kinder C', 'Kinder A', 'Kinder B', 'Kinder C', 'Transicion A', 'Transicion B', 'Transicion C'],
            2 => ['Primero A', 'Primero B', 'Primero C', 'Segundo A', 'Segundo B', 'Segundo C', 'Tercero A', 'Tercero B', 'Tercero C', 'Cuarto A', 'Cuarto B', 'Cuarto C', 'Quinto A', 'Quinto B', 'Quinto C'],
            3 => ['Sexto A', 'Sexto B', 'Sexto C', 'Septimo A', 'Septimo B', 'Septimo C', 'Octavo A', 'Octavo B', 'Octavo C', 'Noveno A', 'Noveno B', 'Noveno C'],
            4 => ['Decimo A', 'Decimo B', 'Decimo C', 'Undecimo A', 'Undecimo B', 'Undecimo C', '11aanti', '11banti', '11canti'],
        ];

        // Cursos sin grado identificable (Stem, administrativos de egreso) — sin nivel
        // académico real, se limpian explícitamente en vez de dejar lo que traían del
        // bucket ancho anterior.
        DB::table('curso')->whereIn('nombre', ['Stem A', 'Stem B', 'Estudiantes Graduados', 'Egresado'])->update(['id_nivel' => null]);

        foreach ($porNombre as $idNivelAcademico => $nombres) {
            DB::table('curso')->whereIn('nombre', $nombres)->update(['id_nivel' => $idNivelAcademico]);
        }

        Schema::table('academico_esquema_horario', function (Blueprint $table) {
            $table->dropForeign('academico_esquema_horario_id_nivel_foreign');
        });

        // Preescolar y Primaria conservan su esquema tal cual, solo cambia a qué apunta
        // id_nivel (nivel.id -> nivel_academico.id): 2->1, 3->2.
        DB::table('academico_esquema_horario')->where('id_nivel', 2)->update(['id_nivel' => 1]);
        DB::table('academico_esquema_horario')->where('id_nivel', 3)->update(['id_nivel' => 2]);

        // "Bachillerato" pasa a ser "Media" (mismo esquema/franjas — sigue sirviendo
        // 10°-11°); nivel_academico Media también es id=4, así que el valor no cambia,
        // solo el nombre y a qué tabla apunta la FK.
        $bachillerato = DB::table('academico_esquema_horario')->where('id_nivel', 4)->get();
        foreach ($bachillerato as $esquema) {
            DB::table('academico_esquema_horario')->where('id', $esquema->id)->update([
                'nombre' => str_replace('Bachillerato', 'Media', $esquema->nombre),
            ]);

            // Esquema "Secundaria" nuevo para el mismo año escolar (6°-9°, no existía).
            DB::table('academico_esquema_horario')->updateOrInsert(
                ['id_nivel' => 3, 'id_anio_escolar' => $esquema->id_anio_escolar],
                ['nombre' => str_replace('Bachillerato', 'Secundaria', $esquema->nombre), 'activo' => 1]
            );
        }

        Schema::table('academico_esquema_horario', function (Blueprint $table) {
            $table->foreign('id_nivel')->references('id')->on('nivel_academico')->cascadeOnDelete();
        });

        // Los horario_clase de los cursos que pasaron a Secundaria (Sexto-Noveno) quedan
        // apuntando a franjas del esquema Media, que ya no les corresponde — se eliminan;
        // los seeders actualizados los vuelven a crear contra el esquema Secundaria nuevo.
        // Ninguna de las FK de la cadena tiene cascadeOnDelete, así que se borra manualmente
        // de abajo hacia arriba: asistencia_estudiante -> asistencia_clase -> horario_clase.
        $idsCursoSecundaria = DB::table('curso')->where('id_nivel', 3)->pluck('id');
        $idsCargaSecundaria = DB::table('academico_carga_academica')->whereIn('id_curso', $idsCursoSecundaria)->pluck('id');
        $idsHorarioSecundaria = DB::table('academico_horario_clase')->whereIn('id_carga_academica', $idsCargaSecundaria)->pluck('id');
        $idsAsistenciaClaseSecundaria = DB::table('academico_asistencia_clase')->whereIn('id_horario_clase', $idsHorarioSecundaria)->pluck('id');
        DB::table('academico_asistencia_estudiante')->whereIn('id_asistencia_clase', $idsAsistenciaClaseSecundaria)->delete();
        DB::table('academico_asistencia_clase')->whereIn('id', $idsAsistenciaClaseSecundaria)->delete();
        DB::table('academico_horario_clase')->whereIn('id', $idsHorarioSecundaria)->delete();
    }

    /**
     * Reversión de mejor esfuerzo: repone la FK vieja hacia `nivel` y deshace el split de
     * esquemas (Media -> Bachillerato, borra los Secundaria nuevos). NO reconstruye los
     * id_nivel anchos originales de `curso` ni los horario_clase eliminados — esa
     * reclasificación era intencionalmente correctiva, no hay un estado "anterior válido"
     * al que volver.
     */
    public function down(): void
    {
        Schema::table('academico_esquema_horario', function (Blueprint $table) {
            $table->dropForeign(['id_nivel']);
        });

        DB::table('academico_esquema_horario')->where('nombre', 'like', 'Secundaria%')->delete();

        $media = DB::table('academico_esquema_horario')->where('id_nivel', 4)->get();
        foreach ($media as $esquema) {
            DB::table('academico_esquema_horario')->where('id', $esquema->id)->update([
                'nombre' => str_replace('Media', 'Bachillerato', $esquema->nombre),
            ]);
        }

        // Ids capturados antes de mutar nada: como el rango de valores se solapa
        // (1->2, 2->3), actualizar por id evita que la primera pasada alcance a las filas
        // que la segunda pasada también debía tocar.
        $idsPreescolar = DB::table('academico_esquema_horario')->where('id_nivel', 1)->pluck('id');
        $idsPrimaria = DB::table('academico_esquema_horario')->where('id_nivel', 2)->pluck('id');
        DB::table('academico_esquema_horario')->whereIn('id', $idsPreescolar)->update(['id_nivel' => 2]);
        DB::table('academico_esquema_horario')->whereIn('id', $idsPrimaria)->update(['id_nivel' => 3]);

        Schema::table('academico_esquema_horario', function (Blueprint $table) {
            $table->foreign('id_nivel')->references('id')->on('nivel')->cascadeOnDelete();
        });
    }
};
