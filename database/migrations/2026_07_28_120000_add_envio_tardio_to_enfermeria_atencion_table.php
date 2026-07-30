<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('enfermeria_atencion', function (Blueprint $table) {
            $table->tinyInteger('envio_tardio')->nullable()->default(0)->after('enviado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enfermeria_atencion', function (Blueprint $table) {
            $table->dropColumn('envio_tardio');
        });
    }
};
