<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Estado REIMPRESO: el carnet de quien ya tenía uno (versión 2 en adelante). Se comporta como
 * IMPRESO (se entrega, se pierde o se anula), solo dice que es una reimpresión.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `carnets` MODIFY `estado` ENUM('IMPRESO', 'REIMPRESO', 'ENTREGADO', 'PERDIDO', 'ANULADO') NOT NULL DEFAULT 'IMPRESO'");
    }

    public function down(): void
    {
        DB::table('carnets')->where('estado', 'REIMPRESO')->update(['estado' => 'IMPRESO']);
        DB::statement("ALTER TABLE `carnets` MODIFY `estado` ENUM('IMPRESO', 'ENTREGADO', 'PERDIDO', 'ANULADO') NOT NULL DEFAULT 'IMPRESO'");
    }
};
