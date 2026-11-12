<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3 de permisos por colegio, segunda tanda: avisos por correo, administración de
 * Evaluaciones y psicólogas de Admisiones. Lo que decidía un número de perfil (o, en
 * Admisiones, el NOMBRE del perfil) pasa a ser una opción que cada colegio otorga en /permisos.
 *
 * Mismo esquema que 2026_11_10_110000_seed_opciones_roles: ids explícitos (falla si alguno
 * está ocupado por otra opción) y se otorgan por nombre del perfil, para que en cada colegio
 * queden los mismos roles que antes. Ninguna es restrictiva.
 */
return new class extends Migration
{
    /** id => [nombre, módulo, perfiles (nombre normalizado) que la reciben] */
    private const OPCIONES = [
        161 => ['Evaluaciones — Administración total (todas las evaluaciones y respuestas)', 3, ['administrador', 'recursos humanos']],
        162 => ['Evaluaciones — Realizar cualquier evaluación activa', 3, ['recursos humanos']],
        163 => ['Gestión Humana — Recibe avisos de llegadas tarde de trabajadores', 3, ['recursos humanos']],
        164 => ['Llegadas tarde — Recibe avisos de estudiantes en el límite', 14, ['directivo docente', 'asistentes de nivel']],
        165 => ['Asistencia de nivel — Avisos y responsable sugerido de su nivel', 1, ['asistentes de nivel']],
        166 => ['Permisos y Licencias — Sus solicitudes avisan a la dirección administrativa', 3, [
            'trabajador', 'operativo', 'mensajero', 'recepcion', 'asistentes de nivel',
        ]],
        167 => ['Admisiones — Psicóloga de preescolar', 1, ['psicologa preescolar']],
        168 => ['Admisiones — Psicóloga de primaria', 1, ['psicologa primaria']],
        169 => ['Admisiones — Psicóloga de bachillerato', 1, ['psicologa secundaria']],
        170 => ['Admisiones — Gestionar inscripciones como personal', 1, ['administrador', 'admisiones']],
    ];

    public function up(): void
    {
        foreach (self::OPCIONES as $id => [$nombre, $modulo]) {
            $existente = DB::table('cron_opciones')->where('id', $id)->value('nombre');

            if ($existente !== null && $existente !== $nombre) {
                throw new RuntimeException("cron_opciones.id {$id} ya está ocupado por \"{$existente}\". Ajusta el id aquí, en App\\Support\\Permiso y en el frontend.");
            }

            if ($existente === null) {
                DB::table('cron_opciones')->insert([
                    'id' => $id, 'nombre' => $nombre, 'id_modulo' => $modulo, 'activo' => 1, 'fechareg' => now(),
                ]);
            }
        }

        $perfiles = DB::table('perfiles')->pluck('nombre', 'id_perfil')
            ->map(fn ($n) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $n))));

        foreach (self::OPCIONES as $id => [, , $nombres]) {
            foreach ($perfiles as $idPerfil => $nombre) {
                $yaLaTiene = DB::table('cron_permisos')->where('id_opcion', $id)->where('id_perfil', $idPerfil)->exists();

                if (in_array($nombre, $nombres, true) && ! $yaLaTiene) {
                    DB::table('cron_permisos')->insert([
                        'id_opcion' => $id, 'id_perfil' => $idPerfil, 'activo' => 1, 'fechareg' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = array_keys(self::OPCIONES);
        DB::table('cron_permisos')->whereIn('id_opcion', $ids)->delete();
        foreach (self::OPCIONES as $id => [$nombre]) {
            DB::table('cron_opciones')->where('id', $id)->where('nombre', $nombre)->delete();
        }
    }
};
