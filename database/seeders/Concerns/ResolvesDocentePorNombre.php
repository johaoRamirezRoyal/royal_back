<?php

namespace Database\Seeders\Concerns;

use App\Models\Usuarios\Usuario;

/**
 * Resuelve un docente por nombre "limpio" (ej. "Corina Corpas") contra `usuarios`, cuyos
 * campos nombre/apellido vienen con datos reales sucios (nombres completos en un solo
 * campo, apellido="." como relleno, espacios dobles, tildes). Un match exacto por
 * CONCAT(nombre, ' ', apellido) = 'Corina Corpas' no encuentra nada contra
 * "CORINA CORPAS SANTOFIMIO ." — en cambio, esto normaliza (sin tildes/puntos, mayúsculas)
 * y verifica que todas las palabras del nombre buscado estén contenidas en las palabras del
 * nombre real del docente, sin importar el orden ni palabras de más (segundo nombre/apellido).
 */
trait ResolvesDocentePorNombre
{
    /** @var array<int, string[]>|null */
    private ?array $docentesNormalizados = null;

    private function resolverDocenteId(string $nombreBuscado): ?int
    {
        $this->docentesNormalizados ??= Usuario::where('perfil', 3)
            ->where('estado', 'activo')
            ->get(['id_user', 'nombre', 'apellido'])
            ->mapWithKeys(fn ($u) => [$u->id_user => $this->normalizar($u->nombre.' '.$u->apellido)])
            ->all();

        $palabrasBuscadas = $this->normalizar($nombreBuscado);

        foreach ($this->docentesNormalizados as $idUser => $palabrasReales) {
            if (empty(array_diff($palabrasBuscadas, $palabrasReales))) {
                return $idUser;
            }
        }

        return null;
    }

    /** @return string[] */
    private function normalizar(string $texto): array
    {
        $texto = mb_strtoupper($texto, 'UTF-8');
        $texto = strtr($texto, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N']);
        $texto = preg_replace('/[^A-Z ]/', ' ', $texto);

        return array_values(array_filter(explode(' ', $texto)));
    }
}
