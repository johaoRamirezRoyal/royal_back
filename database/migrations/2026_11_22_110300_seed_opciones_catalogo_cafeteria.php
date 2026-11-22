<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Opciones de la etapa 2 de la cafetería (módulo 16):
 * - 178 «Cafetería — Catálogo»: categorías, unidades, conversiones, productos y precios.
 * - 179 «Cafetería — Proveedores y productos»: qué productos ofrece cada proveedor (el
 *   proveedor en sí se administra en Compras, opción 61).
 * Ids explícitos (falla si están ocupados por otra cosa). No se otorgan: el Super Admin las tiene
 * todas y cada colegio decide en /permisos.
 */
return new class extends Migration
{
    private const MODULO = 16; // Cafetería

    private const OPCIONES = [
        178 => 'Cafetería — Catálogo',
        179 => 'Cafetería — Proveedores y productos',
    ];

    public function up(): void
    {
        foreach (self::OPCIONES as $id => $nombre) {
            $existente = DB::table('cron_opciones')->where('id', $id)->value('nombre');

            if ($existente !== null && $existente !== $nombre) {
                throw new RuntimeException("cron_opciones.id {$id} ya está ocupado por \"{$existente}\". Ajusta el id aquí y en App\\Support\\Permiso.");
            }

            if ($existente === null) {
                DB::table('cron_opciones')->insert([
                    'id' => $id, 'nombre' => $nombre, 'id_modulo' => self::MODULO, 'activo' => 1, 'fechareg' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('cron_permisos')->whereIn('id_opcion', array_keys(self::OPCIONES))->delete();
        DB::table('cron_opciones')->whereIn('id', array_keys(self::OPCIONES))->delete();
    }
};
