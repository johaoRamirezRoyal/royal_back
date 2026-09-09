<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * InventarioServices::obtenerListadoInventario (usado por /inventario/listado y, por
 * extensión, /inventario/descontinuado y /inventario/liberado) hace LEFT JOIN a dos
 * tablas derivadas en cada request, sin filtrar por lo que pida la query externa:
 * - `ur`: ROW_NUMBER() OVER (PARTITION BY id_inventario ORDER BY fechareg DESC) sobre TODA
 *   `reportes` donde id_reporte IS NULL, más un join adicional reportes.id_reporte = ur.id
 *   para la solución — sin índice, MySQL escanea/ordena la tabla completa en cada llamada.
 * - `idesc`: MAX(fechareg) GROUP BY id_inventario sobre TODA `inventario_desc`.
 * Este índice deja resolver ambas rutas de acceso (el filtro id_reporte IS NULL + partición
 * por id_inventario + orden por fechareg, y el lookup por id_reporte para la solución) desde
 * el índice, sin escanear la tabla completa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->index(['id_reporte', 'id_inventario', 'fechareg'], 'reportes_id_reporte_inventario_fechareg_idx');
        });

        Schema::table('inventario_desc', function (Blueprint $table) {
            $table->index(['id_inventario', 'fechareg'], 'inventario_desc_id_inventario_fechareg_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->dropIndex('reportes_id_reporte_inventario_fechareg_idx');
        });

        Schema::table('inventario_desc', function (Blueprint $table) {
            $table->dropIndex('inventario_desc_id_inventario_fechareg_idx');
        });
    }
};
