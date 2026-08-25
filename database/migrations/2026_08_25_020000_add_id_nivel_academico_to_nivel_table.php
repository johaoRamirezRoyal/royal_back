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
     * El match contra `nivel` es por nombre, no por id, y contempla tanto el nombre corto
     * original (Preescolar/Primaria/Secundaria/Bachillerato) como el descriptivo largo
     * ("Educación preescolar"/"Educación básica primaria"/"Educación básica secundaria"/
     * "Educación media") por si un rename manual anterior ya lo había cambiado — y en ese
     * caso, además de fijar la FK, revierte nivel.nombre a su forma corta original: el
     * nombre largo pasa a vivir solo en nivel_academico.nombre.
     */
    public function up(): void
    {
        Schema::table('nivel', function (Blueprint $table) {
            $table->integer('id_nivel_academico')->nullable()->after('nombre');
            $table->foreign('id_nivel_academico')->references('id')->on('nivel_academico')->nullOnDelete();
        });

        $mapa = [
            1 => ['canonico' => 'Preescolar', 'nombres' => ['Preescolar', 'Educación preescolar']],
            2 => ['canonico' => 'Primaria', 'nombres' => ['Primaria', 'Educación básica primaria']],
            3 => ['canonico' => 'Secundaria', 'nombres' => ['Secundaria', 'Educación básica secundaria']],
            4 => ['canonico' => 'Bachillerato', 'nombres' => ['Bachillerato', 'Media', 'Educación media']],
        ];

        foreach ($mapa as $idNivelAcademico => $info) {
            DB::table('nivel')
                ->whereIn('nombre', $info['nombres'])
                ->update(['id_nivel_academico' => $idNivelAcademico, 'nombre' => $info['canonico']]);
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
