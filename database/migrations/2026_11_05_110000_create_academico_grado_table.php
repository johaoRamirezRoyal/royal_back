<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Catálogo de grados (academico_grado) y el grado de cada curso (curso.id_grado).
 *
 * Los grados se editan en Configuración académica → Grados (nombre, nivel, orden). Agrupan los
 * cursos que comparten logros en Notas (Primero A y B: mismo id_grado) y ordenan los cursos.
 * Se precargan los grados del calendario colombiano y cada curso existente recibe el suyo
 * deducido del nombre; lo que no se reconoce queda sin grado para completarlo a mano.
 *
 * curso es MyISAM en algunas BD: id_grado sin FK y ALTER directo.
 */
return new class extends Migration
{
    /** Grados precargados: [nombre, nivel_academico, palabras con que empieza el nombre de un curso]. */
    private const GRADOS = [
        ['Párvulos', 1, ['parvulos']],
        ['Pre-jardín', 1, ['pre jardin', 'prejardin', 'pre kinder', 'prekinder']],
        ['Jardín', 1, ['jardin', 'kinder']],
        ['Transición', 1, ['transicion']],
        ['Primero', 2, ['primero']],
        ['Segundo', 2, ['segundo']],
        ['Tercero', 2, ['tercero']],
        ['Cuarto', 2, ['cuarto']],
        ['Quinto', 2, ['quinto']],
        ['Sexto', 3, ['sexto']],
        ['Séptimo', 3, ['septimo']],
        ['Octavo', 3, ['octavo']],
        ['Noveno', 3, ['noveno']],
        ['Décimo', 4, ['decimo']],
        ['Undécimo', 4, ['undecimo', 'once']],
    ];

    public function up(): void
    {
        Schema::create('academico_grado', function (Blueprint $table) {
            $table->integer('id')->autoIncrement()->primary();
            $table->string('nombre', 80);
            $table->integer('id_nivel_academico')->nullable()->index();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE curso ADD COLUMN id_grado INT NULL AFTER id_nivel');

        // Precarga y, para cada curso, el grado cuya palabra encabeza su nombre (las más largas
        // primero: "pre kinder" antes que "kinder").
        $porPalabra = [];
        foreach (self::GRADOS as $orden => [$nombre, $nivel, $palabras]) {
            $id = DB::table('academico_grado')->insertGetId([
                'nombre' => $nombre, 'id_nivel_academico' => $nivel, 'orden' => $orden + 1,
                'activo' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($palabras as $p) {
                $porPalabra[$p] = $id;
            }
        }
        uksort($porPalabra, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach (DB::table('curso')->get(['id', 'nombre']) as $curso) {
            $n = str_replace('-', ' ', Str::lower(Str::ascii(trim((string) $curso->nombre))));
            foreach ($porPalabra as $palabra => $idGrado) {
                if (str_starts_with($n, $palabra)) {
                    DB::table('curso')->where('id', $curso->id)->update(['id_grado' => $idGrado]);
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE curso DROP COLUMN id_grado');
        Schema::dropIfExists('academico_grado');
    }
};
