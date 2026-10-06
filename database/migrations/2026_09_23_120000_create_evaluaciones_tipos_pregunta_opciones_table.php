<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opciones por defecto de cada tipo de pregunta — antes vivían hardcodeadas en el
     * frontend (`opcionesPorDefecto.helpers.ts` de Evaluaciones y Encuestas). Se copian
     * a cada pregunta nueva al elegir el tipo; editarlas acá no toca preguntas ya creadas.
     * `valor` es el puntaje que usa Evaluaciones; Encuestas lo ignora.
     */
    public function up(): void
    {
        Schema::create('evaluaciones_tipos_pregunta_opciones', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->integer('id_tipo_pregunta');
            $table->string('texto');
            $table->decimal('valor', 5, 2)->default(0);
            $table->integer('orden')->default(0);

            $table->foreign('id_tipo_pregunta')
                ->references('id')->on('evaluaciones_tipos_pregunta')
                ->cascadeOnDelete();
        });

        $defaults = [
            'escala_likert' => ['Totalmente en desacuerdo' => 1, 'En desacuerdo' => 2, 'Neutral' => 3, 'De acuerdo' => 4, 'Totalmente de acuerdo' => 5],
            'escala_real' => ['Bajo' => 1, 'Básico' => 2, 'Alto' => 3, 'Insignia Real' => 4],
            'si_no' => ['Sí' => 1, 'No' => 0],
            'calificacion_numerica' => array_combine(array_map('strval', range(1, 10)), range(1, 10)),
        ];

        foreach ($defaults as $slug => $opciones) {
            $idTipo = DB::table('evaluaciones_tipos_pregunta')->where('slug', $slug)->value('id');
            if (!$idTipo) continue;

            $orden = 0;
            foreach ($opciones as $texto => $valor) {
                DB::table('evaluaciones_tipos_pregunta_opciones')->insert([
                    'id_tipo_pregunta' => $idTipo,
                    'texto' => (string) $texto,
                    'valor' => $valor,
                    'orden' => ++$orden,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluaciones_tipos_pregunta_opciones');
    }
};
