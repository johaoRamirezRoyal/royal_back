<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fase 3 de permisos por colegio: lo que el código decidía con un número de perfil (que cambia
 * de un colegio a otro) pasa a ser una opción que cada colegio otorga en /permisos.
 *
 * Ids explícitos (150–154 quedan libres para la rama de notas); si alguno ya está ocupado por
 * otra opción, falla en vez de usar otro. Se otorgan por NOMBRE del perfil, no por id, para que
 * en cada colegio queden los mismos roles que antes (Coordinador: 26 en el Royal, 22 en el
 * Hebreo). 155 y 156 son restrictivas (Permiso::OPCIONES_RESTRICTIVAS): encierran en un nivel.
 */
return new class extends Migration
{
    /** id => [nombre, módulo, perfiles (nombre normalizado) que la reciben] */
    private const OPCIONES = [
        155 => ['Coordinación de nivel — Gestiona solo su nivel', 1, ['coordinador']],
        156 => ['Dirección administrativa — Gestiona los niveles administrativos', 1, ['directora administrativa']],
        157 => ['Compras — Ver y aprobar solicitudes de todos los niveles', 9, ['administrador']],
        158 => ['Gestión Humana — Revocar llegadas tarde de trabajadores', 3, ['recursos humanos']],
        159 => ['Áreas comunes — Puede ser responsable de un bloque', 6, [
            'administrador', 'directora administrativa', 'asistentes de nivel', 'coordinador', 'supervisor operativo',
        ]],
        160 => ['Gestión Académica — Puede tener asignaturas', 14, [
            'docente', 'docente ict', 'psicologa preescolar', 'psicologa primaria', 'psicologa secundaria',
        ]],
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
