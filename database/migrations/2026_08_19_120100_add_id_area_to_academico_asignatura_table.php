<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academico_asignatura', function (Blueprint $table) {
            // nullable: una asignatura puede no tener área asignada todavía.
            $table->foreignId('id_area')->nullable()->after('color')
                ->constrained('academico_area')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('academico_asignatura', function (Blueprint $table) {
            $table->dropConstrainedForeignId('id_area');
        });
    }
};
