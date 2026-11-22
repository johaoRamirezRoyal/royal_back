<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo 16 «Cafetería» y sus opciones de la etapa 1:
 * - 176 «Cafetería — Estructura»: cafeterías, puntos de venta, terminales, horarios, almacenes
 *   y funciones del personal (roles operativos).
 * - 177 «Cafetería — Personal»: asignar personal a las cafeterías.
 * Ids explícitos (falla si están ocupados por otra cosa). No se otorgan: el Super Admin las
 * recibe todas y cada colegio decide en /permisos a qué perfiles dárselas.
 */
return new class extends Migration
{
    private const MODULO = ['id' => 16, 'nombre' => 'Cafetería'];

    private const OPCIONES = [
        176 => 'Cafetería — Estructura',
        177 => 'Cafetería — Personal',
    ];

    public function up(): void
    {
        $this->asegurar('cron_modulos', self::MODULO['id'], self::MODULO['nombre'], []);

        foreach (self::OPCIONES as $id => $nombre) {
            $this->asegurar('cron_opciones', $id, $nombre, ['id_modulo' => self::MODULO['id']]);
        }
    }

    public function down(): void
    {
        DB::table('cron_permisos')->whereIn('id_opcion', array_keys(self::OPCIONES))->delete();
        DB::table('cron_opciones')->whereIn('id', array_keys(self::OPCIONES))->delete();
        DB::table('cron_modulos')->where('id', self::MODULO['id'])->delete();
    }

    /** Inserta la fila con ese id, o no hace nada si ya existe con el mismo nombre. */
    private function asegurar(string $tabla, int $id, string $nombre, array $extra): void
    {
        $existente = DB::table($tabla)->where('id', $id)->value('nombre');

        if ($existente !== null && $existente !== $nombre) {
            throw new RuntimeException("{$tabla}.id {$id} ya está ocupado por \"{$existente}\". Ajusta el id aquí y en App\\Support\\Permiso.");
        }

        if ($existente === null) {
            DB::table($tabla)->insert(['id' => $id, 'nombre' => $nombre, 'activo' => 1, 'fechareg' => now(), ...$extra]);
        }
    }
};
