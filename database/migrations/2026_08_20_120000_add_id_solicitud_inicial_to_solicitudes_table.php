<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            // vincula la solicitud formalizada con la solicitud inicial de la que proviene
            // (el legacy crea ambas al registrar la solicitud).
            $table->unsignedBigInteger('id_solicitud_inicial')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropColumn('id_solicitud_inicial');
        });
    }
};
