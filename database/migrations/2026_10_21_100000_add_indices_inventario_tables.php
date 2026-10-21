<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `inventario` (~28k filas) no tenía ningún índice salvo la PK: todo listado de
 * inventario (listado, listado-consolidado, mis inventarios, reportes, áreas) escaneaba la
 * tabla completa aunque filtrara por usuario, área o bloque.
 * Sin índice en `id_categoria` ni `(activo, estado)` a propósito: son poco selectivos
 * (23 categorías, casi todo activo) y medido en local, con el de id_categoria el
 * optimizador recorre la tabla a saltos por categoría y /inventario/listado tardaba el doble.
 * - `reportes (id_inventario, ...)`: las subconsultas correlacionadas
 *   (`WHERE r.id_inventario = iv.id ...`) de mostrarReportesDeInventario y del modo detalle
 *   de obtenerListadoConsolidado — el índice previo empieza por id_reporte y no les sirve.
 * - `inventario_log (id_inventario)`: historial / hoja de vida de un ítem (~84k filas).
 * - `cron_permisos (id_opcion, id_perfil, activo)`: tienePermiso() corre en cada request.
 * - `areas (id_bloque)`: filtro "Bloque" (filtrarPorBloqueDeArea) y Áreas Comunes.
 * Con hasIndex() para no fallar en una BD donde alguno ya se creó a mano.
 */
return new class extends Migration
{
    private const INDICES = [
        'inventario' => [
            'inventario_id_user_idx' => ['id_user'],
            'inventario_id_area_idx' => ['id_area'],
            'inventario_id_bloque_idx' => ['id_bloque'],
        ],
        'reportes' => [
            'reportes_id_inventario_reporte_tipo_estado_idx' => ['id_inventario', 'id_reporte', 'tipo_reporte', 'estado'],
        ],
        'inventario_log' => [
            'inventario_log_id_inventario_idx' => ['id_inventario'],
        ],
        'cron_permisos' => [
            'cron_permisos_opcion_perfil_activo_idx' => ['id_opcion', 'id_perfil', 'activo'],
        ],
        'areas' => [
            'areas_id_bloque_idx' => ['id_bloque'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDICES as $tabla => $indices) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla, $indices) {
                foreach ($indices as $nombre => $columnas) {
                    if (! Schema::hasIndex($tabla, $nombre)) {
                        $table->index($columnas, $nombre);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::INDICES as $tabla => $indices) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla, $indices) {
                foreach (array_keys($indices) as $nombre) {
                    if (Schema::hasIndex($tabla, $nombre)) {
                        $table->dropIndex($nombre);
                    }
                }
            });
        }
    }
};
