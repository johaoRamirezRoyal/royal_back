<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Usuarios\Usuario;

class DocenteAsignaturaSeeder extends Seeder
{
    /**
     * academico_docente_asignatura: cruza id_docente (usuarios.id_user) con id_asignatura.
     *
     * Los docentes se buscan en `usuarios` concatenando nombre + apellido,
     * ya que la tabla no tiene un campo de nombre completo único.
     */
    public function run(): void
    {
        $pares = [
            ['Corina Corpas', 'Biología'],
            ['Corina Corpas', 'Medio Ambiente'],
            ['Derys Mora', 'English'],
            ['Derys Mora', 'Global Perspective'],
            ['Diana Palma', 'Español'],
            ['Diógenes Visbal', 'Química'],
            ['Dorian Peña', 'Física'],
            ['Douglas Donado', 'Filosofía'],
            ['Douglas Donado', 'Religión'],
            ['Douglas Donado', 'Ética'],
            ['Edgar Fuentes', 'Economía y Política'],
            ['Edgar Fuentes', 'Sociales'],
            ['Erika Rodríguez', 'Español'],
            ['Fadia Ruíz', 'Francés'],
            ['Federico Sánchez', 'Chemistry'],
            ['Federico Sánchez', 'Science'],
            ['Ingrid Almendrales', 'Español'],
            ['Ingrid Almendrales', 'Lectura Crítica'],
            ['Jaime Mejía', 'PE'],
            ['John Arrieta', 'English'],
            ['Juan Pablo García', 'Chemistry'],
            ['Juan Pablo García', 'Science'],
            ['Katty Mercado', 'Aritmética'],
            ['Kenny Cohen', 'Enterprise'],
            ['Kenny Cohen', 'Global Perspective'],
            ['Linda Cera', 'English'],
            ['Linda Cera', 'Global Perspective'],
            ['Loly Algarín', 'Arts'],
            ['Loly Algarín', 'Technical Drawing'],
            ['Mario Esmeral', 'ICT'],
            ['Mary Torres', 'Guidance'],
            ['Nilson Santiz', 'PE'],
            ['Paulo Álvarez', 'Cálculo'],
            ['Paulo Álvarez', 'Trigonometría'],
            ['Samir Peñate', 'Filosofía'],
            ['Samir Peñate', 'Sociales'],
            ['Santiago Charris', 'Estadística'],
            ['Santiago Charris', 'Estadística y Geometría'],
            ['Santiago Charris', 'Álgebra'],
            ['Yesid Olivares', 'Estadística'],
            ['Yesid Olivares', 'Estadística y Geometría'],
            ['Yesid Olivares', 'Álgebra'],
        ];

        $noEncontrados = [];

        foreach ($pares as [$nombreDocente, $nombreMateria]) {
            $idDocente = $this->resolverDocenteId($nombreDocente);
            $idAsignatura = DB::table('academico_asignatura')->where('nombre', $nombreMateria)->value('id');

            if (!$idDocente) {
                $noEncontrados[] = $nombreDocente;
                continue;
            }

            if (!$idAsignatura) {
                continue; // materia no encontrada, revisar AsignaturaSeeder
            }

            DB::table('academico_docente_asignatura')->updateOrInsert(
                ['id_docente' => $idDocente, 'id_asignatura' => $idAsignatura],
                ['activo' => 1, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        if (!empty($noEncontrados)) {
            $this->command?->warn(
                'Docentes no encontrados en usuarios: '.implode(', ', array_unique($noEncontrados))
            );
        }
    }

    /**
     * Busca el id_user del docente en `usuarios` por nombre completo
     * (concatenación de nombre + apellido, ya que no existe un campo único).
     */
    private function resolverDocenteId(string $nombreCompleto): ?int
    {
        return Usuario::whereRaw("CONCAT(nombre, ' ', apellido) = ?", [$nombreCompleto])
            ->value('id_user');
    }
}
