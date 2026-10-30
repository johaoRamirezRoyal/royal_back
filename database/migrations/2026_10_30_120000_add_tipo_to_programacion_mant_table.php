<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `programacion_mant` (legacy): agrega `tipo` (1 Sistemas, 2 Operativos — mismos valores que
 * categoria.tipo_categoria; lo existente queda en Sistemas, que era lo único que manejaba el
 * legacy) y pasa `periodo` del ordinal 1/2 del legacy al `periodos.id` real, buscando el
 * periodo cuyo rango contiene `fecha_registro` o, si no hay, el del mismo año y ordinal. Las filas
 * de años sin periodos registrados quedan NULL (siguen filtrables por `anio`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode, 'NO_ZERO_DATE', ''))");

        if (! Schema::hasColumn('programacion_mant', 'tipo')) {
            DB::statement('ALTER TABLE programacion_mant ADD tipo TINYINT NOT NULL DEFAULT 1 AFTER descripcion');
        }

        // Respaldo cuando ningún rango cubre la fecha (los periodos viejos tienen rangos de
        // un solo día): el periodo I/II/... del mismo año escolar según el ordinal. FIELD
        // (romano o numérico) evita mezclar collations y castear 'II' a número en modo estricto.
        DB::statement("UPDATE programacion_mant pm
            LEFT JOIN periodos pf ON pm.fecha_registro BETWEEN pf.fecha_inicio AND pf.fecha_fin
            LEFT JOIN anio_escolar a ON CONCAT(a.anio_inicio, '/', a.anio_fin) = pm.anio
            LEFT JOIN periodos po ON po.id_anio = a.id
                AND pm.periodo IN (FIELD(po.numero, 'I', 'II', 'III', 'IV'), FIELD(po.numero, '1', '2', '3', '4'))
            SET pm.periodo = COALESCE(pf.id, po.id)");
    }

    // El ordinal original no se recupera: down() solo quita la columna.
    public function down(): void
    {
        DB::statement('ALTER TABLE programacion_mant DROP COLUMN tipo');
    }
};
