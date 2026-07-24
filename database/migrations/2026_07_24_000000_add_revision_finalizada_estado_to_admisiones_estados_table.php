<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('admisiones_estados')->insert([
            'nombre' => 'REVISION FINALIZADA. A LA ESPERA DE RESULTADOS',
            'estado' => true,
            'fechareg' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('admisiones_estados')
            ->where('nombre', 'REVISION FINALIZADA. A LA ESPERA DE RESULTADOS')
            ->delete();
    }
};
