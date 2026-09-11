<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ID_NIVEL_PRIMARIA = 2;    // nivel_academico: Educación básica primaria
    private const ID_NIVEL_SECUNDARIA = 3;  // nivel_academico: Educación básica secundaria
    private const ID_NIVEL_MEDIA = 4;       // nivel_academico: Educación media

    private const DIA_LUNES = 1;
    private const DIA_MARTES = 2;
    private const DIA_MIERCOLES = 3;
    private const DIA_JUEVES = 4;
    private const DIA_VIERNES = 5;

    /**
     * "Bachillerato (grados 06°-11°)" cubre a la vez Secundaria (6°-9°) y Media (10°-11°)
     * — como `academico_esquema_horario` solo admite un esquema por (nivel, año), se crea
     * el mismo horario dos veces, una por cada nivel_academico real. Las franjas de
     * Primaria reemplazan las de prueba que ya traía el esquema existente (id_nivel=2)
     * para el año activo — ver AGENTS.md "Años escolares y calendario académico" para el
     * resto del modelo.
     */
    public function up(): void
    {
        $idAnioEscolar = DB::table('anio_escolar')->where('activo', 1)->value('id');

        if (!$idAnioEscolar) {
            return;
        }

        $anio = DB::table('anio_escolar')->where('id', $idAnioEscolar)->first();
        $nombreBachillerato = "Bachillerato {$anio->anio_inicio}-{$anio->anio_fin}";
        $nombrePrimaria = "Primaria {$anio->anio_inicio}-{$anio->anio_fin}";

        $idEsquemaPrimaria = $this->obtenerOCrearEsquema($nombrePrimaria, self::ID_NIVEL_PRIMARIA, $idAnioEscolar);
        $idEsquemaSecundaria = $this->obtenerOCrearEsquema($nombreBachillerato, self::ID_NIVEL_SECUNDARIA, $idAnioEscolar);
        $idEsquemaMedia = $this->obtenerOCrearEsquema($nombreBachillerato, self::ID_NIVEL_MEDIA, $idAnioEscolar);

        $this->reemplazarFranjas($idEsquemaPrimaria, $idAnioEscolar, [
            self::DIA_LUNES => self::primariaLunesAJueves(),
            self::DIA_MARTES => self::primariaLunesAJueves(),
            self::DIA_MIERCOLES => self::primariaLunesAJueves(),
            self::DIA_JUEVES => self::primariaLunesAJueves(),
            self::DIA_VIERNES => self::primariaViernes(),
        ]);

        foreach ([$idEsquemaSecundaria, $idEsquemaMedia] as $idEsquema) {
            $this->reemplazarFranjas($idEsquema, $idAnioEscolar, [
                self::DIA_LUNES => self::bachilleratoLunes(),
                self::DIA_MARTES => self::bachilleratoMartesAJueves(),
                self::DIA_MIERCOLES => self::bachilleratoMartesAJueves(),
                self::DIA_JUEVES => self::bachilleratoMartesAJueves(),
                self::DIA_VIERNES => self::bachilleratoViernes(),
            ]);
        }
    }

    public function down(): void
    {
        // Datos de contenido (no de esquema de tabla) — sin rollback automático, igual que
        // el resto de migraciones de seed de este repo (ver seed_opcion_*).
    }

    private function obtenerOCrearEsquema(string $nombre, int $idNivel, int $idAnioEscolar): int
    {
        $existente = DB::table('academico_esquema_horario')
            ->where('id_nivel', $idNivel)
            ->where('id_anio_escolar', $idAnioEscolar)
            ->first();

        if ($existente) {
            return $existente->id;
        }

        return DB::table('academico_esquema_horario')->insertGetId([
            'nombre' => $nombre,
            'id_nivel' => $idNivel,
            'id_anio_escolar' => $idAnioEscolar,
            'activo' => 1,
        ]);
    }

    /** @param array<int, array<int, array{0:string,1:string,2:?string,3:int,4:?string}>> $porDia */
    private function reemplazarFranjas(int $idEsquema, int $idAnioEscolar, array $porDia): void
    {
        DB::table('academico_franja_horaria')->where('id_esquema', $idEsquema)->delete();

        foreach ($porDia as $idDia => $franjas) {
            $orden = 1;
            foreach ($franjas as [$horaInicio, $horaFin, $etiqueta, $asignable, $color]) {
                DB::table('academico_franja_horaria')->insert([
                    'id_anio_escolar' => $idAnioEscolar,
                    'id_esquema' => $idEsquema,
                    'id_dia_semana' => $idDia,
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin,
                    'orden' => $orden++,
                    'asignable' => $asignable,
                    'color' => $color,
                    'etiqueta' => $etiqueta,
                ]);
            }
        }
    }

    private static function bachilleratoLunes(): array
    {
        return [
            ['07:00', '07:30', 'HOMEROOM', 0, '#93c5fd'],
            ['07:30', '08:20', null, 1, null],
            ['08:20', '09:10', null, 1, null],
            ['09:10', '10:00', null, 1, null],
            ['10:00', '10:15', 'BREAK', 0, '#fde68a'],
            ['10:15', '10:20', 'LINE UP', 0, '#d1d5db'],
            ['10:20', '11:10', null, 1, null],
            ['11:10', '12:00', null, 1, null],
            ['12:00', '12:50', null, 1, null],
            ['12:50', '13:25', 'LUNCH', 0, '#86efac'],
            ['13:25', '13:30', 'LINE UP', 0, '#d1d5db'],
            ['13:30', '14:15', null, 1, null],
            ['14:15', '15:00', null, 1, null],
            ['15:00', '16:00', null, 1, null],
        ];
    }

    private static function bachilleratoMartesAJueves(): array
    {
        return [
            ['07:00', '07:15', 'HOMEROOM', 0, '#93c5fd'],
            ['07:15', '08:10', null, 1, null],
            ['08:10', '09:05', null, 1, null],
            ['09:05', '10:00', null, 1, null],
            ['10:00', '10:15', 'BREAK', 0, '#fde68a'],
            ['10:15', '10:20', 'LINE UP', 0, '#d1d5db'],
            ['10:20', '11:10', null, 1, null],
            ['11:10', '12:00', null, 1, null],
            ['12:00', '12:50', null, 1, null],
            ['12:50', '13:25', 'LUNCH', 0, '#86efac'],
            ['13:25', '13:30', 'LINE UP', 0, '#d1d5db'],
            ['13:30', '14:15', null, 1, null],
            ['14:15', '15:00', null, 1, null],
            ['15:00', '16:00', null, 1, null],
        ];
    }

    private static function bachilleratoViernes(): array
    {
        return [
            ['07:00', '07:10', 'HOMEROOM', 0, '#93c5fd'],
            ['07:10', '07:55', null, 1, null],
            ['07:55', '08:40', null, 1, null],
            ['08:40', '09:30', 'ELECTIVE/CLUB', 1, '#c4b5fd'],
            ['09:30', '09:45', 'BREAK', 0, '#fde68a'],
            ['09:45', '09:50', 'LINE UP', 0, '#d1d5db'],
            ['09:50', '10:35', null, 1, null],
            ['10:35', '11:20', null, 1, null],
            ['11:20', '12:05', null, 1, null],
            ['12:05', '12:25', 'BREAK', 0, '#fde68a'],
            ['12:25', '12:30', 'LINE UP', 0, '#d1d5db'],
            ['12:30', '13:15', null, 1, null],
            ['13:15', '14:00', null, 1, null],
            ['14:00', '15:00', null, 1, null],
        ];
    }

    private static function primariaLunesAJueves(): array
    {
        return [
            ['07:00', '07:25', 'HOMEROOM', 0, '#93c5fd'],
            ['07:25', '08:10', null, 1, null],
            ['08:10', '08:55', null, 1, null],
            ['08:55', '09:40', null, 1, null],
            ['09:40', '10:00', 'BREAK', 0, '#fde68a'],
            ['10:00', '10:50', null, 1, null],
            ['10:50', '11:35', null, 1, null],
            ['11:35', '12:20', null, 1, null],
            ['12:20', '12:50', 'LUNCH', 0, '#86efac'],
            ['12:50', '13:40', null, 1, null],
            ['13:40', '14:30', null, 1, null],
        ];
    }

    private static function primariaViernes(): array
    {
        return [
            ['07:00', '07:10', 'HOMEROOM', 0, '#93c5fd'],
            ['07:10', '07:50', null, 1, null],
            ['07:50', '08:35', null, 1, null],
            ['08:35', '09:20', null, 1, null],
            ['09:20', '10:05', null, 1, null],
            ['10:05', '10:30', 'BREAK', 0, '#fde68a'],
            ['10:30', '11:15', 'CLUB', 1, '#c4b5fd'],
            ['11:15', '12:00', null, 1, null],
            ['12:00', '12:45', null, 1, null],
            ['12:45', '13:30', null, 1, null],
            ['13:30', '14:00', null, 1, null],
        ];
    }
};
