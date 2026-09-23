<?php

namespace App\Services\Estudiantes;

use App\Models\Estudiantes\EstudiantesPadre;
use App\Models\Usuarios\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Vínculos acudiente (perfil 6) ↔ estudiante (perfil 16) en `estudiantes_padres`.
 * Desvincular es soft (activo=0) — el resto del sistema (llegadas tarde, enfermería,
 * /usuarios/paginados) ya filtra por activo=1, y volver a vincular el mismo par reactiva
 * la fila existente en vez de duplicarla (la tabla no tiene índice único).
 */
class VinculacionAcudienteService
{
    private const PERFIL_ACUDIENTE = 6;

    private const PERFIL_ESTUDIANTE = 16;

    private const MAX_FILAS_IMPORTACION = 3000;

    public function estudiantesVinculados(int $idAcudiente): array
    {
        try {
            $vinculos = EstudiantesPadre::where('id_acudiente', $idAcudiente)
                ->where('activo', 1)
                ->with([
                    'estudiante:id_user,documento,nombre,apellido,id_curso,estado',
                    'estudiante.cursoRelacion:id,nombre',
                ])
                ->get()
                ->filter(fn ($vinculo) => $vinculo->estudiante);

            $idsEstudiantes = $vinculos->pluck('id_estudiante')->unique()->values();

            $otrosPorEstudiante = EstudiantesPadre::whereIn('id_estudiante', $idsEstudiantes)
                ->where('id_acudiente', '!=', $idAcudiente)
                ->where('activo', 1)
                ->with('acudiente:id_user,nombre,apellido')
                ->get()
                ->filter(fn ($vinculo) => $vinculo->acudiente)
                ->groupBy('id_estudiante');

            $estudiantes = $vinculos->unique('id_estudiante')->map(function ($vinculo) use ($otrosPorEstudiante) {
                $estudiante = $vinculo->estudiante;

                return [
                    'id_user' => $estudiante->id_user,
                    'documento' => $estudiante->documento,
                    'nombre' => $estudiante->nombre,
                    'apellido' => $estudiante->apellido,
                    'estado' => $estudiante->estado,
                    'curso' => $estudiante->cursoRelacion?->nombre,
                    'otros_acudientes' => $otrosPorEstudiante->get($estudiante->id_user, collect())
                        ->unique('id_acudiente')
                        ->map(fn ($otro) => [
                            'id_user' => $otro->acudiente->id_user,
                            'nombre' => $otro->acudiente->nombre,
                            'apellido' => $otro->acudiente->apellido,
                        ])->values()->all(),
                ];
            })->sortBy('nombre')->values()->all();

            return [
                'error' => false,
                'message' => 'Datos obtenidos satisfactoriamente',
                'data' => $estudiantes,
            ];
        } catch (QueryException $e) {
            return [
                'error' => true,
                'message' => 'Ha ocurrido un error inesperado',
                'data' => $e->getMessage(),
            ];
        }
    }

    public function vincular(int $idAcudiente, int $idEstudiante): array
    {
        if (!Usuario::where('id_user', $idAcudiente)->where('perfil', self::PERFIL_ACUDIENTE)->exists()) {
            return ['error' => true, 'message' => 'El usuario indicado no es un acudiente.', 'data' => []];
        }

        if (!Usuario::where('id_user', $idEstudiante)->where('perfil', self::PERFIL_ESTUDIANTE)->exists()) {
            return ['error' => true, 'message' => 'El usuario indicado no es un estudiante.', 'data' => []];
        }

        try {
            $existente = EstudiantesPadre::where('id_acudiente', $idAcudiente)
                ->where('id_estudiante', $idEstudiante)
                ->orderByDesc('activo')
                ->first();

            if ($existente?->activo === 1) {
                return ['error' => true, 'message' => 'El estudiante ya está vinculado a este acudiente.', 'data' => []];
            }

            if ($existente) {
                $existente->update(['activo' => 1]);
            } else {
                $existente = EstudiantesPadre::create([
                    'id_acudiente' => $idAcudiente,
                    'id_estudiante' => $idEstudiante,
                    'activo' => 1,
                    'fechareg' => now(),
                ]);
            }

            return ['error' => false, 'message' => 'Estudiante vinculado correctamente.', 'data' => $existente];
        } catch (QueryException $e) {
            return ['error' => true, 'message' => 'Ha ocurrido un error inesperado', 'data' => $e->getMessage()];
        }
    }

    /**
     * Vinculación masiva desde Excel: columna A = documento del acudiente, B = documento
     * del estudiante, fila 1 = encabezados (formato que descarga el frontend). Los
     * documentos se comparan normalizados (solo alfanuméricos) en ambos lados — hay
     * documentos guardados con \t, \r\n o puntos sobrantes. Un documento que corresponde
     * a más de un usuario del perfil se rechaza como ambiguo en vez de adivinar. Cada fila
     * se reporta por separado; las válidas se aplican aunque otras fallen.
     */
    public function importarExcel(string $ruta): array
    {
        try {
            $hoja = IOFactory::load($ruta)->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => 'No se pudo leer el archivo. Verifica que sea un Excel válido.', 'data' => []];
        }

        $filas = [];
        foreach (array_slice($hoja, 1, null, true) as $i => $celdas) {
            $docAcudiente = $this->normalizarDocumento($celdas[0] ?? null);
            $docEstudiante = $this->normalizarDocumento($celdas[1] ?? null);

            if ($docAcudiente === '' && $docEstudiante === '') {
                continue;
            }

            $filas[] = ['fila' => $i + 1, 'acudiente' => $docAcudiente, 'estudiante' => $docEstudiante];
        }

        if (count($filas) === 0) {
            return ['error' => true, 'message' => 'El archivo no tiene filas para importar.', 'data' => []];
        }

        if (count($filas) > self::MAX_FILAS_IMPORTACION) {
            return ['error' => true, 'message' => 'El archivo supera el máximo de ' . self::MAX_FILAS_IMPORTACION . ' filas.', 'data' => []];
        }

        try {
            $acudientes = $this->usuariosPorDocumento(array_column($filas, 'acudiente'), self::PERFIL_ACUDIENTE);
            $estudiantes = $this->usuariosPorDocumento(array_column($filas, 'estudiante'), self::PERFIL_ESTUDIANTE);

            $existentes = EstudiantesPadre::whereIn('id_acudiente', $acudientes->flatten()->unique())
                ->whereIn('id_estudiante', $estudiantes->flatten()->unique())
                ->orderByDesc('activo')
                ->get()
                ->unique(fn ($v) => "{$v->id_acudiente}-{$v->id_estudiante}")
                ->keyBy(fn ($v) => "{$v->id_acudiente}-{$v->id_estudiante}");

            $resumen = ['vinculados' => 0, 'ya_vinculados' => 0, 'errores' => []];

            DB::transaction(function () use ($filas, $acudientes, $estudiantes, $existentes, &$resumen) {
                foreach ($filas as $fila) {
                    $idAcudiente = $this->resolverUnico($acudientes, $fila['acudiente'], 'acudiente', $fila['fila'], $resumen);
                    $idEstudiante = $this->resolverUnico($estudiantes, $fila['estudiante'], 'estudiante', $fila['fila'], $resumen);

                    if ($idAcudiente === null || $idEstudiante === null) {
                        continue;
                    }

                    $clave = "{$idAcudiente}-{$idEstudiante}";
                    $vinculo = $existentes->get($clave);

                    if ($vinculo?->activo === 1) {
                        $resumen['ya_vinculados']++;
                        continue;
                    }

                    if ($vinculo) {
                        $vinculo->update(['activo' => 1]);
                    } else {
                        $vinculo = EstudiantesPadre::create([
                            'id_acudiente' => $idAcudiente,
                            'id_estudiante' => $idEstudiante,
                            'activo' => 1,
                            'fechareg' => now(),
                        ]);
                    }

                    // Mismo par repetido más abajo en el archivo → cuenta como ya vinculado.
                    $existentes->put($clave, $vinculo);
                    $resumen['vinculados']++;
                }
            });

            return [
                'error' => false,
                'message' => "Importación finalizada: {$resumen['vinculados']} vinculado(s), {$resumen['ya_vinculados']} ya existían, " . count($resumen['errores']) . ' con error.',
                'data' => $resumen,
            ];
        } catch (QueryException $e) {
            return ['error' => true, 'message' => 'Ha ocurrido un error inesperado', 'data' => $e->getMessage()];
        }
    }

    private function normalizarDocumento(mixed $valor): string
    {
        // Excel entrega documentos numéricos como float (1048082892.0).
        if (is_float($valor) && floor($valor) === $valor) {
            $valor = number_format($valor, 0, '', '');
        }

        return preg_replace('/[^0-9A-Za-z]/', '', (string) $valor);
    }

    /** documento normalizado => [id_user, ...] (más de uno = documento duplicado en BD). */
    private function usuariosPorDocumento(array $documentos, int $perfil)
    {
        $documentos = array_values(array_unique(array_filter($documentos)));

        return Usuario::where('perfil', $perfil)
            ->whereIn(DB::raw("REGEXP_REPLACE(documento, '[^0-9A-Za-z]', '')"), $documentos)
            ->get(['id_user', 'documento'])
            ->groupBy(fn ($u) => $this->normalizarDocumento($u->documento))
            ->map(fn ($grupo) => $grupo->pluck('id_user')->all());
    }

    private function resolverUnico($mapa, string $documento, string $tipo, int $fila, array &$resumen): ?int
    {
        $ids = $documento === '' ? [] : $mapa->get($documento, []);

        if (count($ids) === 1) {
            return $ids[0];
        }

        $resumen['errores'][] = [
            'fila' => $fila,
            'mensaje' => match (true) {
                $documento === '' => "Falta el documento del {$tipo}.",
                count($ids) === 0 => "No existe un {$tipo} con documento {$documento}.",
                default => "Hay más de un {$tipo} con documento {$documento}; vincúlalo manualmente.",
            },
        ];

        return null;
    }

    public function desvincular(int $idAcudiente, int $idEstudiante): array
    {
        try {
            $afectadas = EstudiantesPadre::where('id_acudiente', $idAcudiente)
                ->where('id_estudiante', $idEstudiante)
                ->where('activo', 1)
                ->update(['activo' => 0]);

            if ($afectadas === 0) {
                return ['error' => true, 'message' => 'El estudiante no está vinculado a este acudiente.', 'data' => []];
            }

            return ['error' => false, 'message' => 'Estudiante desvinculado correctamente.', 'data' => []];
        } catch (QueryException $e) {
            return ['error' => true, 'message' => 'Ha ocurrido un error inesperado', 'data' => $e->getMessage()];
        }
    }
}
