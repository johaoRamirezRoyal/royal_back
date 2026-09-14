<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opciones reales de Evaluaciones (incluye encuestas de satisfacción, campo
 * `es_satisfaccion` en la tabla `evaluaciones`). El frontend (`router/index.tsx`) gatea
 * `/evaluaciones/configuracion`, `/evaluaciones/resultados` y `/evaluaciones/realizar`
 * con los IDs 101/102/103 — esos IDs **no existen como opciones de Evaluaciones en esta
 * BD**: en producción ya pertenecen a Gestión Académica (101 = Llegadas Tarde
 * Academicas, 102 = Asistencia Docentes, 103 = Métricas de Asistencia Académica, ver
 * `2026_08_18_100000_seed_opcion_llegadas_tarde_recepcion` y
 * `2026_08_19_100000_seed_opcion_metricas_asistencia_academica`). Las opciones de
 * Evaluaciones con esos números solo existían en otra base (creadas a mano el
 * 2026-08-21, según el comentario de `2026_08_28_110000_grant_opciones_evaluaciones_a_coordinador`)
 * y nunca se migraron aquí. Esta migración las crea con IDs reales — no otorga
 * `cron_permisos` a ningún perfil a propósito, se asigna manualmente desde /permisos.
 * Tras correr esto en producción, el frontend debe actualizarse para usar los IDs
 * reales generados aquí en vez de 101/102/103.
 */
return new class extends Migration
{
    private const ID_MODULO_GESTION_HUMANA = 3;

    private const NOMBRES = [
        'Evaluaciones — Configuración y administración',
        'Evaluaciones — Ver resultados',
        'Evaluaciones — Realizar evaluaciones',
    ];

    public function up(): void
    {
        foreach (self::NOMBRES as $nombre) {
            DB::table('cron_opciones')->insert([
                'nombre' => $nombre,
                'id_modulo' => self::ID_MODULO_GESTION_HUMANA,
                'activo' => 1,
                'fechareg' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cron_opciones')->whereIn('nombre', self::NOMBRES)->delete();
    }
};
