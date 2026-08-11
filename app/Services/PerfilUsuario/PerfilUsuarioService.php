<?php
namespace App\Services\PerfilUsuario;

use App\Models\PerfilUsuario\CertificadoFormacion;
use App\Models\PerfilUsuario\ExperienciaLaboral;
use App\Models\PerfilUsuario\Formacion;
use App\Models\PerfilUsuario\FotoPerfil;
use App\Models\PerfilUsuario\InfoAdicionalUsuario;
use App\Models\PerfilUsuario\ProduccionIntelectual;
use App\Services\FileStorageService;
use App\Services\Service;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PerfilUsuarioService extends Service {

    public function __construct(
        private FileStorageService $fileStorageService
    ) {}

    public function mostrarInformacionPefilUsuario(int $id_usuario){
        try {
            $infoPerfilUsuario = InfoAdicionalUsuario::with([
                'usuario:id_user,documento,nombre,apellido,correo,telefono,user,perfil,id_nivel',
                'tipoDocumento:id,nombre',
                'usuario.perfilRelacion:id_perfil,nombre',
                'usuario.nivelRelacion:id,nombre',
                'usuario.fotoPerfil:id,id_user,nombre_foto',
            ])
                ->where('id_user', $id_usuario)
                ->first();

            if (!$infoPerfilUsuario) {
                return [
                    'error' => true,
                    'message' => 'No se encontró la información del usuario.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Información obtenida correctamente.',
                'data' => $infoPerfilUsuario->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener la información del perfil del usuario.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener la información.',
                'data' => []
            ];
        }
    }

    public function obtenerHojaDeVida(int $id_usuario): array
    {
        try {
            $usuario = \App\Models\Usuarios\Usuario::with([
                'perfilRelacion:id_perfil,nombre',
                'nivelRelacion:id,nombre',
            ])
                ->where('id_user', $id_usuario)
                ->first([
                    'id_user', 'nombre', 'apellido', 'correo', 'telefono', 'documento', 'user', 'perfil', 'id_nivel'
                ]);

            if (!$usuario) {
                return [
                    'error' => true,
                    'message' => 'Usuario no encontrado.',
                    'data' => []
                ];
            }

            $infoAdicional = InfoAdicionalUsuario::with('tipoDocumento:id,nombre')
                ->where('id_user', $id_usuario)
                ->first();

            $formaciones = Formacion::with('certificados')
                ->where('id_user', $id_usuario)
                ->get();

            $experiencias = ExperienciaLaboral::where('id_user', $id_usuario)->get();

            $producciones = ProduccionIntelectual::where('id_user', $id_usuario)->get();

            return [
                'error' => false,
                'message' => 'Hoja de vida obtenida correctamente.',
                'data' => [
                    'usuario' => $usuario->toArray(),
                    'informacion_adicional' => $infoAdicional?->toArray(),
                    'formaciones' => $this->adjuntarUrlsDocumentos($formaciones->toArray(), 'formacion'),
                    'experiencias_laborales' => $this->adjuntarUrlsDocumentos($experiencias->toArray(), 'experiencia_laboral'),
                    'producciones_intelectuales' => $this->adjuntarUrlsDocumentos($producciones->toArray(), 'produccion_intelectual'),
                ]
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener la hoja de vida del usuario.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener la hoja de vida.',
                'data' => []
            ];
        }
    }

    public function crearActualizarInfoAdicional(array $data): array
    {
        try {
            $registro = InfoAdicionalUsuario::where('id_user', $data['id_user'])->first();

            if ($registro) {
                $registro->update($data);

                return [
                    'error' => false,
                    'message' => 'Información adicional actualizada correctamente.',
                    'data' => $registro->fresh()->toArray()
                ];
            }

            $registro = InfoAdicionalUsuario::create($data);

            return [
                'error' => false,
                'message' => 'Información adicional creada correctamente.',
                'data' => $registro->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al guardar la información adicional del usuario.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al guardar la información.',
                'data' => []
            ];
        }
    }

    public function actualizarInfoAdicional(int $id, array $data): array
    {
        try {
            $registro = InfoAdicionalUsuario::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de información adicional no encontrado.',
                    'data' => []
                ];
            }

            $registro->update($data);

            return [
                'error' => false,
                'message' => 'Información adicional actualizada correctamente.',
                'data' => $registro->fresh()->toArray()
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar la información adicional del usuario.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la información.',
                'data' => []
            ];
        }
    }

    public function eliminarInfoAdicional(int $id): array
    {
        try {
            $registro = InfoAdicionalUsuario::find($id);

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'Registro de información adicional no encontrado.',
                    'data' => []
                ];
            }

            $registro->delete();

            return [
                'error' => false,
                'message' => 'Información adicional eliminada correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar la información adicional del usuario.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar la información.',
                'data' => []
            ];
        }
    }

    public function verificarCompletitudPerfil(int $id_usuario): array
    {
        try {
            $registro = InfoAdicionalUsuario::where('id_user', $id_usuario)->first();

            if (!$registro) {
                return [
                    'error' => true,
                    'message' => 'No se encontró información adicional del usuario.',
                    'data' => [
                        'completo' => false,
                        'campos_faltantes' => [],
                    ]
                ];
            }

            $camposRequeridos = [
                'tipo_documento' => 'Tipo de documento',
                'fecha_expedicion' => 'Fecha de expedición',
                'departamento_nacimiento' => 'Departamento de nacimiento',
                'fecha_nacimiento' => 'Fecha de nacimiento',
                'direccion_vivienda' => 'Dirección de vivienda',
                'genero' => 'Género',
                'correo_personal' => 'Correo personal',
            ];

            $camposFaltantes = [];

            foreach ($camposRequeridos as $campo => $nombre) {
                if (empty($registro->{$campo})) {
                    $camposFaltantes[] = $nombre;
                }
            }

            return [
                'error' => false,
                'message' => 'Verificación de completitud realizada.',
                'data' => [
                    'completo' => empty($camposFaltantes),
                    'campos_faltantes' => $camposFaltantes,
                ]
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al verificar la completitud del perfil.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al verificar la completitud.',
                'data' => []
            ];
        }
    }

    // ── Formación ───────────────────────────────────────────────

    public function obtenerFormacionesPorUsuario(int $id_usuario): array
    {
        try {
            $formaciones = Formacion::with('certificados')
                ->where('id_user', $id_usuario)
                ->orderByDesc('fecha_grado')
                ->get();

            if ($formaciones->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron formaciones para este usuario.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Formaciones obtenidas correctamente.',
                'data' => $this->adjuntarUrlsDocumentos($formaciones->toArray(), 'formacion')
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener las formaciones del usuario.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener las formaciones.',
                'data' => []
            ];
        }
    }

    public function crearFormacion(array $data, ?UploadedFile $archivo = null): array
    {
        try {
            $formacion = Formacion::create($data);

            if ($archivo) {
                $resultado = $this->fileStorageService->uploadFile($archivo, 'formacion/certificados');
                CertificadoFormacion::create([
                    'id_formacion' => $formacion->id,
                    'id_user' => $data['id_user'],
                    'nombre_archivo' => $resultado['ruta'],
                ]);
            }

            return [
                'error' => false,
                'message' => 'Formación creada correctamente.',
                'data' => $this->adjuntarUrlsDocumentos($formacion->load('certificados')->toArray(), 'formacion')
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear la formación.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear la formación.',
                'data' => []
            ];
        }
    }

    public function actualizarFormacion(int $id, array $data, ?UploadedFile $archivo = null): array
    {
        try {
            $formacion = Formacion::find($id);

            if (!$formacion) {
                return [
                    'error' => true,
                    'message' => 'Formación no encontrada.',
                    'data' => []
                ];
            }

            $formacion->update($data);

            if ($archivo) {
                $certificadoActual = $formacion->certificados()->first();

                if ($certificadoActual) {
                    $this->fileStorageService->eliminar($certificadoActual->nombre_archivo);
                    $certificadoActual->delete();
                }

                $resultado = $this->fileStorageService->uploadFile($archivo, 'formacion/certificados');
                CertificadoFormacion::create([
                    'id_formacion' => $formacion->id,
                    'id_user' => $formacion->id_user,
                    'nombre_archivo' => $resultado['ruta'],
                ]);
            }

            return [
                'error' => false,
                'message' => 'Formación actualizada correctamente.',
                'data' => $this->adjuntarUrlsDocumentos($formacion->fresh()->load('certificados')->toArray(), 'formacion')
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar la formación.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la formación.',
                'data' => []
            ];
        }
    }

    public function eliminarFormacion(int $id): array
    {
        try {
            $formacion = Formacion::find($id);

            if (!$formacion) {
                return [
                    'error' => true,
                    'message' => 'Formación no encontrada.',
                    'data' => []
                ];
            }

            foreach ($formacion->certificados as $certificado) {
                $this->fileStorageService->eliminar($certificado->nombre_archivo);
                $certificado->delete();
            }

            $formacion->delete();

            return [
                'error' => false,
                'message' => 'Formación eliminada correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar la formación.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar la formación.',
                'data' => []
            ];
        }
    }

    public function obtenerFormacionesPorTipo(int $id_usuario, string $tipo): array
    {
        try {
            $formaciones = Formacion::with('certificados')
                ->where('id_user', $id_usuario)
                ->where('tipo_formacion', $tipo)
                ->orderByDesc('fecha_grado')
                ->get();

            return [
                'error' => false,
                'message' => 'Formaciones obtenidas correctamente.',
                'data' => $this->adjuntarUrlsDocumentos($formaciones->toArray(), 'formacion')
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener las formaciones por tipo.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener las formaciones.',
                'data' => []
            ];
        }
    }

    public function eliminarFormacionesPorUsuario(int $id_usuario): array
    {
        try {
            $formaciones = Formacion::where('id_user', $id_usuario)->get();

            foreach ($formaciones as $formacion) {
                foreach ($formacion->certificados as $certificado) {
                    $this->fileStorageService->eliminar($certificado->nombre_archivo);
                }
            }

            CertificadoFormacion::where('id_user', $id_usuario)->delete();
            $eliminadas = $formaciones->count();
            Formacion::where('id_user', $id_usuario)->delete();

            return [
                'error' => false,
                'message' => "$eliminadas formación(es) eliminada(s) correctamente.",
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar las formaciones del usuario.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar las formaciones.',
                'data' => []
            ];
        }
    }

    // ── Experiencia Laboral ─────────────────────────────────────

    public function obtenerExperienciasPorUsuario(int $id_usuario): array
    {
        try {
            $experiencias = ExperienciaLaboral::where('id_user', $id_usuario)
                ->orderByDesc('fecha_ingreso')
                ->get();

            if ($experiencias->isEmpty()) {
                return [
                    'error' => true,
                    'message' => 'No se encontraron experiencias laborales para este usuario.',
                    'data' => []
                ];
            }

            return [
                'error' => false,
                'message' => 'Experiencias laborales obtenidas correctamente.',
                'data' => $this->adjuntarUrlsDocumentos($experiencias->toArray(), 'experiencia_laboral')
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener las experiencias laborales del usuario.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener las experiencias laborales.',
                'data' => []
            ];
        }
    }

    public function crearExperienciaLaboral(array $data, ?UploadedFile $archivo = null): array
    {
        try {
            if ($archivo) {
                $resultado = $this->fileStorageService->uploadFile($archivo, 'experiencia-laboral/certificados');
                $data['certificado_trabajo'] = $resultado['ruta'];
            }

            $experiencia = ExperienciaLaboral::create($data);

            return [
                'error' => false,
                'message' => 'Experiencia laboral creada correctamente.',
                'data' => $this->adjuntarUrlsDocumentos($experiencia->toArray(), 'experiencia_laboral')
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear la experiencia laboral.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear la experiencia laboral.',
                'data' => []
            ];
        }
    }

    public function actualizarExperienciaLaboral(int $id, array $data, ?UploadedFile $archivo = null): array
    {
        try {
            $experiencia = ExperienciaLaboral::find($id);

            if (!$experiencia) {
                return [
                    'error' => true,
                    'message' => 'Experiencia laboral no encontrada.',
                    'data' => []
                ];
            }

            if ($archivo) {
                $data['certificado_trabajo'] = $this->fileStorageService->reemplazar(
                    $archivo,
                    $experiencia->certificado_trabajo,
                    'experiencia-laboral/certificados'
                )['ruta'];
            }

            $experiencia->update($data);

            return [
                'error' => false,
                'message' => 'Experiencia laboral actualizada correctamente.',
                'data' => $this->adjuntarUrlsDocumentos($experiencia->fresh()->toArray(), 'experiencia_laboral')
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar la experiencia laboral.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la experiencia laboral.',
                'data' => []
            ];
        }
    }

    public function eliminarExperienciaLaboral(int $id): array
    {
        try {
            $experiencia = ExperienciaLaboral::find($id);

            if (!$experiencia) {
                return [
                    'error' => true,
                    'message' => 'Experiencia laboral no encontrada.',
                    'data' => []
                ];
            }

            if ($experiencia->certificado_trabajo) {
                $this->fileStorageService->eliminar($experiencia->certificado_trabajo);
            }

            $experiencia->delete();

            return [
                'error' => false,
                'message' => 'Experiencia laboral eliminada correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar la experiencia laboral.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar la experiencia laboral.',
                'data' => []
            ];
        }
    }

    public function obtenerExperienciasActivas(int $id_usuario): array
    {
        try {
            $experiencias = ExperienciaLaboral::where('id_user', $id_usuario)
                ->whereNull('fecha_retiro')
                ->orderByDesc('fecha_ingreso')
                ->get();

            return [
                'error' => false,
                'message' => 'Experiencias activas obtenidas correctamente.',
                'data' => $this->adjuntarUrlsDocumentos($experiencias->toArray(), 'experiencia_laboral')
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener las experiencias activas.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener las experiencias activas.',
                'data' => []
            ];
        }
    }

    public function obtenerResumenExperiencias(int $id_usuario): array
    {
        try {
            $experiencias = ExperienciaLaboral::where('id_user', $id_usuario)->get();

            $totalEmpresas = $experiencias->pluck('nombre_empresa')->filter()->unique()->count();
            $totalRegistros = $experiencias->count();

            $añosTotales = 0;
            $experienciasActivas = 0;

            foreach ($experiencias as $exp) {
                $inicio = $exp->fecha_ingreso ? \Carbon\Carbon::parse($exp->fecha_ingreso) : null;
                $fin = $exp->fecha_retiro ? \Carbon\Carbon::parse($exp->fecha_retiro) : \Carbon\Carbon::now();

                if ($inicio) {
                    $añosTotales += $inicio->diffInMonths($fin) / 12;
                }

                if (is_null($exp->fecha_retiro)) {
                    $experienciasActivas++;
                }
            }

            return [
                'error' => false,
                'message' => 'Resumen de experiencia obtenido correctamente.',
                'data' => [
                    'total_empresas' => $totalEmpresas,
                    'total_registros' => $totalRegistros,
                    'experiencias_activas' => $experienciasActivas,
                    'años_experiencia' => round($añosTotales, 1),
                ]
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener el resumen de experiencias.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener el resumen.',
                'data' => []
            ];
        }
    }

    public function eliminarExperienciasPorUsuario(int $id_usuario): array
    {
        try {
            $eliminadas = ExperienciaLaboral::where('id_user', $id_usuario)->delete();

            return [
                'error' => false,
                'message' => "$eliminadas experiencia(s) laboral(es) eliminada(s) correctamente.",
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar las experiencias del usuario.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar las experiencias.',
                'data' => []
            ];
        }
    }

    // ── Producción Intelectual ──────────────────────────────────

    public function crearProduccionIntelectual(array $data, ?UploadedFile $archivo): array
    {
        try {
            $data['fechareg'] = now();

            if ($archivo) {
                $ruta = $this->fileStorageService->uploadFile($archivo, 'produccion-intelectual');
                $data['evidencia_pdf'] = $ruta['ruta'];
            }

            $produccion = ProduccionIntelectual::create($data);

            return [
                'error' => false,
                'message' => 'Producción intelectual creada correctamente.',
                'data' => $this->adjuntarUrlsDocumentos($produccion->toArray(), 'produccion_intelectual')
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear la producción intelectual.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear la producción intelectual.',
                'data' => []
            ];
        }
    }

    public function actualizarProduccionIntelectual(int $id, array $data, ?UploadedFile $archivo): array
    {
        try {
            $produccion = ProduccionIntelectual::where('id', $id)->first();

            if (!$produccion) {
                return [
                    'error' => true,
                    'message' => 'Producción intelectual no encontrada.',
                    'data' => []
                ];
            }

            if ($archivo) {
                $ruta = $this->fileStorageService->uploadFile($archivo, 'produccion-intelectual');
                $data['evidencia_pdf'] = $ruta['ruta'];
            }

            $produccion->update($data);

            return [
                'error' => false,
                'message' => 'Producción intelectual actualizada correctamente.',
                'data' => $this->adjuntarUrlsDocumentos($produccion->fresh()->toArray(), 'produccion_intelectual')
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar la producción intelectual.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la producción intelectual.',
                'data' => []
            ];
        }
    }

    public function eliminarProduccionIntelectual(int $id): array
    {
        try {
            $produccion = ProduccionIntelectual::where('id', $id)->first();

            if (!$produccion) {
                return [
                    'error' => true,
                    'message' => 'Producción intelectual no encontrada.',
                    'data' => []
                ];
            }

            if ($produccion->evidencia_pdf) {
                $this->fileStorageService->eliminar($produccion->evidencia_pdf);
            }

            $produccion->delete();

            return [
                'error' => false,
                'message' => 'Producción intelectual eliminada correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar la producción intelectual.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar la producción intelectual.',
                'data' => []
            ];
        }
    }

    public function obtenerProduccionesPorUsuario(int $id_usuario): array
    {
        try {
            $producciones = ProduccionIntelectual::where('id_user', $id_usuario)->get();

            return [
                'error' => false,
                'message' => 'Producciones obtenidas correctamente.',
                'data' => $this->adjuntarUrlsDocumentos($producciones->toArray(), 'produccion_intelectual')
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener las producciones del usuario.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener las producciones.',
                'data' => []
            ];
        }
    }

    public function eliminarProduccionesPorUsuario(int $id_usuario): array
    {
        try {
            $eliminadas = ProduccionIntelectual::where('id_user', $id_usuario)->delete();

            return [
                'error' => false,
                'message' => "$eliminadas producción(es) intelectual(es) eliminada(s) correctamente.",
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar las producciones del usuario.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar las producciones.',
                'data' => []
            ];
        }
    }

    // ── Foto de Perfil ──────────────────────────────────────────

    public function agregarFotoPerfil(int $id_user, UploadedFile $archivo): array
    {
        try {
            $resultado = $this->fileStorageService->uploadFile($archivo, '');

            FotoPerfil::where('id_user', $id_user)->update(['activo' => 0]);

            $foto = FotoPerfil::create([
                'nombre_foto' => $resultado['ruta'],
                'id_user' => $id_user,
                'activo' => 1,
                'fechareg' => now(),
            ]);

            return [
                'error' => false,
                'message' => 'Foto de perfil agregada correctamente.',
                'data' => $this->adjuntarUrlFoto($foto->toArray())
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al agregar la foto de perfil.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al agregar la foto de perfil.',
                'data' => []
            ];
        }
    }

    public function editarFotoPerfil(int $id, UploadedFile $archivo): array
    {
        try {
            $foto = FotoPerfil::find($id);

            if (!$foto) {
                return [
                    'error' => true,
                    'message' => 'Foto de perfil no encontrada.',
                    'data' => []
                ];
            }

            $resultado = $this->fileStorageService->reemplazar($archivo, $foto->nombre_foto, '');
            $foto->update([
                'nombre_foto' => $resultado['ruta'],
                'activo' => 1,
                'fechareg' => now(),
            ]);

            return [
                'error' => false,
                'message' => 'Foto de perfil actualizada correctamente.',
                'data' => $this->adjuntarUrlFoto($foto->fresh()->toArray())
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar la foto de perfil.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la foto de perfil.',
                'data' => []
            ];
        }
    }

    public function eliminarFotoPerfil(int $id): array
    {
        try {
            $foto = FotoPerfil::find($id);

            if (!$foto) {
                return [
                    'error' => true,
                    'message' => 'Foto de perfil no encontrada.',
                    'data' => []
                ];
            }

            $this->fileStorageService->eliminar($foto->nombre_foto);
            $foto->delete();

            return [
                'error' => false,
                'message' => 'Foto de perfil eliminada correctamente.',
                'data' => []
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar la foto de perfil.');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar la foto de perfil.',
                'data' => []
            ];
        }
    }

    private function adjuntarUrlFoto(array $foto): array
    {
        if (!empty($foto['nombre_foto'])) {
            $foto['url_foto'] = Storage::disk(config('filesystems.uploads_disk', 'public'))->url($foto['nombre_foto']);
        }

        return $foto;
    }

    /**
     * Adjunta la URL pública de cada documento a la respuesta.
     */
    private function adjuntarUrlsDocumentos(array $items, string $tipo): array
    {
        foreach ($items as &$item) {
            if ($tipo === 'formacion') {
                foreach ($item['certificados'] ?? [] as &$certificado) {
                    if (!empty($certificado['nombre_archivo'])) {
                        $certificado['url_documento'] = Storage::disk(config('filesystems.uploads_disk', 'public'))->url($certificado['nombre_archivo']);
                    }
                }
                unset($certificado);
            }

            if ($tipo === 'experiencia_laboral' && !empty($item['certificado_trabajo'])) {
                $item['url_documento'] = Storage::disk(config('filesystems.uploads_disk', 'public'))->url($item['certificado_trabajo']);
            }

            if ($tipo === 'produccion_intelectual' && !empty($item['evidencia_pdf'])) {
                $item['url_documento'] = Storage::disk(config('filesystems.uploads_disk', 'public'))->url($item['evidencia_pdf']);
            }
        }
        unset($item);

        return $items;
    }
}
