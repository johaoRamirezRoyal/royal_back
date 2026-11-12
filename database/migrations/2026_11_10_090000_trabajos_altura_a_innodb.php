<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * trabajos_altura y trabajos_altura_operativos quedaron en MyISAM en producción (el motor
 * por defecto del servidor), así que sus FKs nunca se crearon: borrar un reporte deja su
 * trabajo en altura huérfano. Las pasa a InnoDB y crea las FKs de
 * 2026_11_09_120000_create_trabajos_altura_tables.
 *
 * Antes borra los huérfanos que la FK no dejaría crear: los que el cascade ya habría
 * borrado y los operativos que apuntan a un usuario que ya no existe.
 * Si las tablas ya son InnoDB con sus FKs (BD locales), no hace nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $faltantes = array_filter([
            'trabajos_altura_id_reporte_foreign' => 'ALTER TABLE trabajos_altura ADD CONSTRAINT trabajos_altura_id_reporte_foreign FOREIGN KEY (id_reporte) REFERENCES reportes (id) ON DELETE CASCADE',
            'trabajos_altura_operativos_id_trabajo_foreign' => 'ALTER TABLE trabajos_altura_operativos ADD CONSTRAINT trabajos_altura_operativos_id_trabajo_foreign FOREIGN KEY (id_trabajo) REFERENCES trabajos_altura (id) ON DELETE CASCADE',
            'trabajos_altura_operativos_id_operativo_foreign' => 'ALTER TABLE trabajos_altura_operativos ADD CONSTRAINT trabajos_altura_operativos_id_operativo_foreign FOREIGN KEY (id_operativo) REFERENCES usuarios (id_user)',
        ], fn (string $sql, string $nombre) => ! DB::scalar(
            "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$nombre]
        ), ARRAY_FILTER_USE_BOTH);

        if ($faltantes === []) {
            return;
        }

        // Huérfanos (MyISAM: sin transacción, cada sentencia queda aplicada).
        DB::statement('DELETE ta FROM trabajos_altura ta LEFT JOIN reportes rp ON rp.id = ta.id_reporte WHERE rp.id IS NULL');
        DB::statement('DELETE tao FROM trabajos_altura_operativos tao LEFT JOIN trabajos_altura ta ON ta.id = tao.id_trabajo WHERE ta.id IS NULL');
        DB::statement('DELETE tao FROM trabajos_altura_operativos tao LEFT JOIN usuarios u ON u.id_user = tao.id_operativo WHERE u.id_user IS NULL');

        DB::statement('ALTER TABLE trabajos_altura ENGINE = InnoDB');
        DB::statement('ALTER TABLE trabajos_altura_operativos ENGINE = InnoDB');

        foreach ($faltantes as $sql) {
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        // ponytail: sin vuelta atrás; regresar a MyISAM solo volvería a perder las FKs.
    }
};
