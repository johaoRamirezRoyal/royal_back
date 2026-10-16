<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // `descripcion` nació como VARCHAR(255) y el controller no le pone máximo: una
    // descripción larga fallaba con SQLSTATE 1406 "Data too long". `texto` de la pregunta
    // era VARCHAR(255) pero el controller acepta hasta 500 — mismo error.
    // DB::statement crudo: el proyecto no tiene doctrine/dbal para Blueprint::change().
    public function up(): void
    {
        DB::statement('ALTER TABLE encuestas MODIFY descripcion TEXT NULL');
        DB::statement('ALTER TABLE encuestas_preguntas MODIFY texto VARCHAR(500) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE encuestas MODIFY descripcion VARCHAR(255) NULL');
        DB::statement('ALTER TABLE encuestas_preguntas MODIFY texto VARCHAR(255) NOT NULL');
    }
};
