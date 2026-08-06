<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un registro por navegación a un módulo (nombre legible del NavItem del sidebar,
     * no la ruta cruda) — usado para las métricas de "módulos más visitados" en el Home.
     */
    public function up(): void
    {
        Schema::create('modulo_visitas', function (Blueprint $table) {
            $table->integer("id")->autoIncrement()->primary()->index();

            $table->integer("id_usuario");
            $table->foreign("id_usuario")->references("id_user")->on("usuarios")->cascadeOnDelete();

            $table->string("modulo", 100);
            $table->index("modulo");

            $table->timestamp("fechareg")->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modulo_visitas');
    }
};
