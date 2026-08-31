<?php

namespace Database\Seeders;

use App\Models\Instituciones\Institucion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de ejemplo para el login de instituciones (jardines infantiles asociados).
 * No se llama desde DatabaseSeeder::run() a propósito — se corre a mano
 * (`php artisan db:seed --class=InstitucionesSeeder`) y los NIT de ejemplo deben
 * reemplazarse por los reales antes de usar en producción.
 */
class InstitucionesSeeder extends Seeder
{
    public function run(): void
    {
        $instituciones = [
            ['nombre' => 'Jardín Infantil Ejemplo Uno', 'nit' => '900123456-1'],
            ['nombre' => 'Jardín Infantil Ejemplo Dos', 'nit' => '900654321-2'],
        ];

        foreach ($instituciones as $institucion) {
            Institucion::firstOrCreate(
                ['nombre' => $institucion['nombre']],
                ['nit' => Hash::make($institucion['nit']), 'activo' => true]
            );
        }
    }
}
