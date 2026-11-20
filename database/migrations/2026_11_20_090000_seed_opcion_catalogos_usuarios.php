<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opción 224 «Catálogos de usuarios — Perfiles y Niveles»: el módulo
 * /administrativo/perfiles y /administrativo/niveles (crear, editar, activar/desactivar
 * filas de `perfiles` y `nivel`; nunca se borran de verdad). A diferencia de otras
 * opciones nuevas, esta migración SOLO crea la fila en `cron_opciones` — el otorgamiento
 * inicial a perfiles lo hace el usuario manualmente desde /permisos.
 *
 * El id 176 (usado originalmente) resultó ya ocupado en producción por una fila
 * duplicada de "Configuración de asistencia" (ver cron_opciones: hay varias filas 176-223
 * con nombres repetidos de otra migración/reseed ajena a este módulo) — 224 es el primer
 * id libre contra el volcado de producción confirmado el 2026-11-20.
 */
return new class extends Migration
{
    private const ID = 224;
    private const NOMBRE = 'Catálogos de usuarios — Perfiles y Niveles';
    private const MODULO = 2; // "Usuarios" — confirmado contra cron_modulos de producción

    public function up(): void
    {
        $existente = DB::table('cron_opciones')->where('id', self::ID)->value('nombre');

        if ($existente !== null && $existente !== self::NOMBRE) {
            throw new RuntimeException('cron_opciones.id ' . self::ID . " ya está ocupado por \"{$existente}\". Ajusta el id aquí y en App\\Support\\Permiso.");
        }

        if ($existente === null) {
            DB::table('cron_opciones')->insert([
                'id' => self::ID, 'nombre' => self::NOMBRE, 'id_modulo' => self::MODULO, 'activo' => 1, 'fechareg' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('cron_permisos')->where('id_opcion', self::ID)->delete();
        DB::table('cron_opciones')->where('id', self::ID)->where('nombre', self::NOMBRE)->delete();
    }
};
