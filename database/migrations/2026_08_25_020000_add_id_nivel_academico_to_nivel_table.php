<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FK nullable de `nivel` (tabla legada, sin migración propia en este repo) hacia el
     * nuevo catálogo `nivel_academico` — un nivel_academico puede tener varias filas de
     * `nivel` asociadas (ej. si en el futuro se separan variantes de un mismo nivel),
     * pero cada fila de `nivel` apunta a lo sumo a un nivel_academico. Las categorías no
     * académicas (Administrativo/Acudiente/Operativo/Egresado, y cualquier fila legada sin
     * clasificar como "----------") quedan con id_nivel_academico = null.
     *
     * El match contra `nivel` es por nombre, no por id, y contempla tanto el nombre viejo
     * (Preescolar/Primaria/Secundaria/Bachillerato) como el nuevo tras el rename a
     * "Educación preescolar"/"Educación básica primaria"/"Educación básica secundaria"/
     * "Educación media" — para que esta migración quede correcta sin importar si ese
     * rename (aplicado directo en BD, no versionado) ya corrió en el entorno de destino.
     */
    public function up(): void
    {
        Schema::table('nivel', function (Blueprint $table) {
            $table->integer('id_nivel_academico')->nullable()->after('nombre');
            $table->foreign('id_nivel_academico')->references('id')->on('nivel_academico')->nullOnDelete();
        });

        $mapa = [
            1 => ['Preescolar', 'Educación preescolar'],
            2 => ['Primaria', 'Educación básica primaria'],
            3 => ['Secundaria', 'Educación básica secundaria'],
            4 => ['Bachillerato', 'Media', 'Educación media'],
        ];

        foreach ($mapa as $idNivelAcademico => $nombres) {
            DB::table('nivel')->whereIn('nombre', $nombres)->update(['id_nivel_academico' => $idNivelAcademico]);
        }
    }

    public function down(): void
    {
        Schema::table('nivel', function (Blueprint $table) {
            $table->dropForeign(['id_nivel_academico']);
            $table->dropColumn('id_nivel_academico');
        });
    }
};
