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
     * original (Preescolar/Primaria/Bachillerato) como el descriptivo largo ("Educación
     * preescolar"/"Educación básica primaria"/"Educación media") por si un rename manual
     * anterior ya lo había cambiado — y en ese caso, además de fijar la FK, revierte
     * nivel.nombre a su forma corta original: el nombre largo pasa a vivir solo en
     * nivel_academico.nombre.
     *
     * "Secundaria" NO tiene fila propia en `nivel` (nunca existió como categoría de
     * usuario — nivel.nombre históricamente solo distinguía Preescolar/Primaria/
     * Bachillerato) y a propósito no se crea acá: `nivel` es para clasificar un usuario
     * (usuarios.id_nivel) y otros módulos no académicos; para lo académico (cursos,
     * esquemas de horario) donde sí se necesitan los 4 niveles reales, usar
     * nivel_academico directamente en vez de nivel.
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
