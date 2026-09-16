<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Alias de distribución reales del colegio para Noticias (confirmados por Mr. Angel,
     * 2026-09-16) — un solo envío a cada uno reparte el mensaje del lado del proveedor,
     * evitando repetir el incidente de rate-limit de cPanel de mandar un correo por cada
     * usuario del nivel (ver App\Exceptions\MailRateLimitException). Viven en
     * `correos_institucionales` (no hardcodeados ni en .env) porque cada base de datos/
     * institución tiene sus propias direcciones reales — ver App\Enums\Mails y
     * NoticiasService::resolverDestinatariosDistribucion.
     */
    public function up(): void
    {
        DB::table('correos_institucionales')->insert([
            ['grupo' => 'NOTICIAS_TODOS', 'nombre' => null, 'correo' => 'all@royalschool.edu.co'],
            ['grupo' => 'NOTICIAS_PREESCOLAR', 'nombre' => null, 'correo' => 'preschool@royalschool.edu.co'],
            ['grupo' => 'NOTICIAS_PRIMARIA', 'nombre' => null, 'correo' => 'primarysection@royalschool.edu.co'],
            ['grupo' => 'NOTICIAS_SECUNDARIA', 'nombre' => null, 'correo' => 'midhigh@royalschool.edu.co'],
            ['grupo' => 'NOTICIAS_ADMINISTRATIVO', 'nombre' => null, 'correo' => 'administrative@royalschool.edu.co'],
        ]);
    }

    public function down(): void
    {
        DB::table('correos_institucionales')->whereIn('grupo', [
            'NOTICIAS_TODOS',
            'NOTICIAS_PREESCOLAR',
            'NOTICIAS_PRIMARIA',
            'NOTICIAS_SECUNDARIA',
            'NOTICIAS_ADMINISTRATIVO',
        ])->delete();
    }
};
