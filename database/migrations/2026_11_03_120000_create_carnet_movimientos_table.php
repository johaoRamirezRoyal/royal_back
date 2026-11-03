<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada cambio de estado de un carnet (impresión, entrega, pérdida, anulación) con quién y
 * cuándo. `carnets` solo guarda el último (`id_usuario_estado`, `fecha_estado`), así que al
 * entregar y luego anular se perdía quién entregó.
 *
 * Se llena con lo que ya se sabe de cada carnet: la impresión, la entrega (con quién, solo si
 * sigue ENTREGADO) y la pérdida o anulación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carnet_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_carnet')->constrained('carnets')->cascadeOnDelete();
            $table->enum('estado', ['IMPRESO', 'REIMPRESO', 'ENTREGADO', 'PERDIDO', 'ANULADO']);
            $table->dateTime('fecha');
            $table->unsignedInteger('id_usuario')->nullable();
            $table->string('observacion', 255)->nullable();
        });

        DB::statement("INSERT INTO carnet_movimientos (id_carnet, estado, fecha, id_usuario)
            SELECT id, CASE WHEN version > 1 THEN 'REIMPRESO' ELSE 'IMPRESO' END, fecha_emision, id_usuario_emite FROM carnets");
        DB::statement("INSERT INTO carnet_movimientos (id_carnet, estado, fecha, id_usuario)
            SELECT id, 'ENTREGADO', fecha_entrega, CASE WHEN estado = 'ENTREGADO' THEN id_usuario_estado END
            FROM carnets WHERE fecha_entrega IS NOT NULL");
        DB::statement("INSERT INTO carnet_movimientos (id_carnet, estado, fecha, id_usuario, observacion)
            SELECT id, estado, fecha_estado, id_usuario_estado, observacion
            FROM carnets WHERE estado IN ('PERDIDO', 'ANULADO') AND fecha_estado IS NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('carnet_movimientos');
    }
};
