<?php

namespace App\Services\Acudientes;

use App\Enums\TipoPerfil;
use App\Exceptions\MailRateLimitException;
use App\Mail\AccesoPortalPadresMail;
use App\Models\PasswordResetTokens;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\Usuario;
use App\Services\branding\ColegioAdmisionService;
use App\Services\Estudiantes\VinculacionAcudienteService;
use App\Services\MailService;
use App\Services\Service;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Acudientes creados por el colegio (módulo /gestion-academica/acudientes): son `usuarios` de
 * tipo ACUDIENTE, los mismos que se registran solos en Admisiones y que entran al portal para
 * padres. El colegio nunca escribe ni ve la contraseña: la cuenta nace con una al azar y el
 * acudiente crea la suya con el enlace de «acceso al portal».
 */
class AcudientesService extends Service
{
    /** Días que dura el enlace de acceso al portal (el de recuperar contraseña dura 60 minutos). */
    public const DIAS_ENLACE_ACCESO = 7;

    /** Costo del hash de la contraseña inicial (el documento), ver nuevo(). */
    private const RONDAS_CLAVE_INICIAL = 10;

    private const MAX_FILAS_IMPORTACION = 500;

    public function __construct(
        private ColegioAdmisionService $colegios,
        private MailService $correo,
        private VinculacionAcudienteService $vinculacion,
    ) {
    }

    /** El documento y el correo no se repiten en la base (el login busca por correo). */
    public static function reglas(?int $id = null): array
    {
        return [
            'documento' => ['required', 'string', 'max:50', Rule::unique('usuarios', 'documento')->ignore($id, 'id_user')],
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['nullable', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:140', Rule::unique('usuarios', 'correo')->ignore($id, 'id_user')],
            'telefono' => ['nullable', 'string', 'max:20'],
        ];
    }

    public static function mensajes(): array
    {
        return [
            'documento.required' => 'Escribe el número de documento.',
            'documento.unique' => 'Ya existe una cuenta con este documento.',
            'nombre.required' => 'Escribe los nombres.',
            'correo.required' => 'Escribe el correo: con él entra al portal para padres.',
            'correo.email' => 'El correo no tiene un formato válido.',
            'correo.unique' => 'Ya existe una cuenta con este correo.',
            'telefono.max' => 'El teléfono admite hasta 20 caracteres.',
        ];
    }

    /** @param array{documento: string, nombre: string, apellido: string, correo: string, telefono?: ?string} $datos */
    public function crear(array $datos, int $idUsuarioRegistra): array
    {
        try {
            $this->nuevo($datos, $idUsuarioRegistra);

            return ['error' => false, 'message' => 'Acudiente creado', 'data' => null];
        } catch (Exception $e) {
            return $this->sendError($e, 'Error al crear el acudiente');
        }
    }

    public function actualizar(int $id, array $datos): array
    {
        try {
            $acudiente = $this->buscar($id);
            if (! $acudiente) {
                return ['error' => true, 'message' => 'El acudiente no existe', 'data' => null];
            }

            $acudiente->update([...$this->limpiar($datos), 'fecha_editado' => now()]);

            return ['error' => false, 'message' => 'Acudiente actualizado', 'data' => null];
        } catch (Exception $e) {
            return $this->sendError($e, 'Error al actualizar el acudiente');
        }
    }

    /**
     * Carga masiva desde Excel (fila 1 = encabezados): A documento del acudiente, B nombres,
     * C apellidos, D correo, E teléfono, F documento del hijo (opcional).
     * - Documento nuevo: crea al acudiente (nombres y correo obligatorios; apellidos opcionales), sin enviarle
     *   el acceso al portal: eso se hace después desde la lista.
     * - Documento de un acudiente existente: actualiza solo las columnas que vengan llenas.
     * - Con documento del hijo: lo vincula. Un acudiente con varios hijos va en varias filas.
     * Cada fila se aplica sola; las que fallan se reportan y no detienen al resto.
     */
    public function importarExcel(string $ruta, int $idUsuarioRegistra): array
    {
        // Cada acudiente nuevo cuesta un hash (~60 ms): 500 filas ≈ 35 s.
        set_time_limit(180);

        try {
            $hoja = IOFactory::load($ruta)->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => 'No se pudo leer el archivo. Verifica que sea un Excel válido.', 'data' => null];
        }

        $filas = [];
        foreach (array_slice($hoja, 1, null, true) as $i => $celdas) {
            if (! array_filter($celdas, fn ($v) => $v !== null && trim((string) $v) !== '')) {
                continue;
            }

            $filas[] = [
                'fila' => $i + 1,
                'documento' => $this->normalizarDocumento($celdas[0] ?? null),
                'nombre' => trim((string) ($celdas[1] ?? '')),
                'apellido' => trim((string) ($celdas[2] ?? '')),
                'correo' => trim((string) ($celdas[3] ?? '')),
                'telefono' => trim((string) ($celdas[4] ?? '')),
                'hijo' => $this->normalizarDocumento($celdas[5] ?? null),
            ];
        }

        if (! $filas) {
            return ['error' => true, 'message' => 'El archivo no tiene filas para importar.', 'data' => null];
        }
        if (count($filas) > self::MAX_FILAS_IMPORTACION) {
            return ['error' => true, 'message' => 'El archivo supera el máximo de ' . self::MAX_FILAS_IMPORTACION . ' filas.', 'data' => null];
        }

        $resumen = ['creados' => 0, 'actualizados' => 0, 'vinculados' => 0, 'errores' => []];
        $nuevos = [];
        // Una consulta por tipo para todo el archivo (no una por fila: REGEXP recorre `usuarios`).
        $acudientes = $this->idsPorDocumento(array_column($filas, 'documento'), TipoPerfil::ACUDIENTE);
        $estudiantes = $this->idsPorDocumento(array_column($filas, 'hijo'), TipoPerfil::ESTUDIANTE);

        foreach ($filas as $fila) {
            $error = function (string $mensaje) use ($fila, &$resumen) {
                $resumen['errores'][] = ['fila' => $fila['fila'], 'mensaje' => $mensaje];
            };

            try {
                if ($fila['documento'] === '') {
                    $error('Falta el documento del acudiente.');
                    continue;
                }

                $ids = $acudientes[$fila['documento']] ?? [];
                if (count($ids) > 1) {
                    $error("Hay más de un acudiente con documento {$fila['documento']}; corrígelo a mano.");
                    continue;
                }

                if ($ids) {
                    $acudiente = Usuario::find($ids[0]);
                    // Solo lo que venga en el archivo y sea distinto de lo guardado: una columna vacía
                    // no borra el dato, y repetir los mismos datos (otra fila del mismo acudiente,
                    // por otro hijo) no cuenta como «actualizado».
                    $cambios = array_filter(
                        array_intersect_key($fila, array_flip(['nombre', 'apellido', 'correo', 'telefono'])),
                        fn ($v, $campo) => $v !== '' && $this->normalizarCampo($campo, $v) !== $this->normalizarCampo($campo, $acudiente->$campo),
                        ARRAY_FILTER_USE_BOTH,
                    );
                    $reglas = array_intersect_key(self::reglas($acudiente->id_user), $cambios);
                    $validacion = Validator::make($cambios, $reglas, self::mensajes());
                    if ($validacion->fails()) {
                        $error($validacion->errors()->first());
                        continue;
                    }
                    if ($cambios) {
                        if (isset($cambios['correo'])) {
                            $cambios['correo'] = mb_strtolower($cambios['correo']);
                        }
                        $acudiente->update([...$cambios, 'fecha_editado' => now()]);
                        $resumen['actualizados']++;
                    }
                } else {
                    $datos = array_intersect_key($fila, array_flip(['documento', 'nombre', 'apellido', 'correo', 'telefono']));
                    $validacion = Validator::make($datos, self::reglas(), self::mensajes());
                    if ($validacion->fails()) {
                        $error($validacion->errors()->first());
                        continue;
                    }
                    $acudiente = $this->nuevo($datos, $idUsuarioRegistra);
                    // Sus filas siguientes (otro hijo) ya lo encuentran.
                    $acudientes[$fila['documento']] = [$acudiente->id_user];
                    $resumen['creados']++;
                }

                if ($fila['hijo'] !== '') {
                    $hijos = $estudiantes[$fila['hijo']] ?? [];
                    if (count($hijos) !== 1) {
                        $error($hijos
                            ? "Hay más de un estudiante con documento {$fila['hijo']}; vincúlalo a mano."
                            : "No existe un estudiante con documento {$fila['hijo']}.");
                        continue;
                    }

                    $vinculo = $this->vinculacion->vincular($acudiente->id_user, $hijos[0]);
                    // «Ya vinculado» no es error: el archivo se puede volver a subir.
                    if (! $vinculo['error']) {
                        $resumen['vinculados']++;
                        $nuevos[] = ['fila' => $fila['fila'], 'id_acudiente' => $acudiente->id_user, 'id_estudiante' => $hijos[0]];
                    }
                }
            } catch (Exception $e) {
                $this->sendError($e, "Error en la fila {$fila['fila']} de la carga de acudientes");
                $error('No se pudo guardar esta fila.');
            }
        }

        $resumen['vinculos'] = $this->detalleVinculos($nuevos);

        return [
            'error' => false,
            'message' => "Carga terminada: {$resumen['creados']} creado(s), {$resumen['actualizados']} actualizado(s), {$resumen['vinculados']} hijo(s) vinculado(s), " . count($resumen['errores']) . ' con error.',
            'data' => $resumen,
        ];
    }

    /**
     * Cada vínculo nuevo con nombres y curso, para revisarlo: un documento mal escrito en la
     * columna F puede coincidir con otro estudiante, y ese acudiente vería en el portal a un niño
     * ajeno. Dos consultas para todo el archivo.
     *
     * @param  array<array{fila: int, id_acudiente: int, id_estudiante: int}>  $nuevos
     */
    private function detalleVinculos(array $nuevos): array
    {
        $personas = Usuario::with('cursoRelacion:id,nombre')
            ->whereIn('id_user', array_merge(array_column($nuevos, 'id_acudiente'), array_column($nuevos, 'id_estudiante')))
            ->get(['id_user', 'nombre', 'apellido', 'id_curso'])
            ->keyBy('id_user');
        $nombre = fn (?Usuario $u) => trim(preg_replace('/\s*\.$/', '', trim(($u?->nombre ?? '') . ' ' . ($u?->apellido ?? ''))));

        return array_map(fn (array $v) => [
            'fila' => $v['fila'],
            'acudiente' => $nombre($personas->get($v['id_acudiente'])),
            'estudiante' => $nombre($personas->get($v['id_estudiante'])),
            'curso' => $personas->get($v['id_estudiante'])?->cursoRelacion?->nombre,
        ], $nuevos);
    }

    /**
     * Envía al acudiente el acceso al portal de su colegio (/{slug}/padres): si sigue con la
     * contraseña inicial (su documento), se lo dice y le pide cambiarla; si no tiene, le pide
     * crearla. El enlace es el de recuperar contraseña por colegio, pero dura DIAS_ENLACE_ACCESO
     * días. Un enlace nuevo invalida el anterior.
     */
    public function enviarAccesoPortal(int $id): array
    {
        try {
            $acudiente = $this->buscar($id);
            if (! $acudiente) {
                return ['error' => true, 'message' => 'El acudiente no existe', 'data' => null];
            }
            if ($acudiente->estado !== 'activo') {
                return ['error' => true, 'message' => 'El acudiente está inactivo: actívalo antes de enviarle el acceso', 'data' => null];
            }

            $colegio = $this->colegios->colegioDeLaSesion();
            if (! $colegio) {
                return ['error' => true, 'message' => 'Este colegio no tiene enlace del portal para padres: créalo en Colegios', 'data' => null];
            }

            $token = Str::random(64);
            PasswordResetTokens::updateOrInsert(['email' => $acudiente->correo], [
                'token' => bcrypt($token),
                'used' => false,
                'created_at' => now(),
                'expires_at' => Carbon::now()->addDays(self::DIAS_ENLACE_ACCESO),
            ]);

            $frontend = rtrim(config('app.frontend_url'), '/');
            $url = "{$frontend}/password/restore?" . http_build_query(['token' => $token, 'email' => $acudiente->correo, 'colegio' => $colegio->slug]);
            $enviado = $this->correo->send($acudiente->correo, new AccesoPortalPadresMail(
                $acudiente->nombre,
                $acudiente->correo,
                $url,
                "{$frontend}/{$colegio->slug}/padres",
                self::DIAS_ENLACE_ACCESO,
                $colegio,
                $this->estadoContrasena($acudiente),
            ));

            if (! $enviado) {
                return ['error' => true, 'message' => 'No se pudo enviar el correo. Revisa la dirección e inténtalo de nuevo', 'data' => null];
            }

            return ['error' => false, 'message' => "Acceso enviado a {$acudiente->correo}", 'data' => null];
        } catch (MailRateLimitException $e) {
            // Límite por hora del hosting: lo maneja el controller (429) para que el envío en lote se detenga.
            throw $e;
        } catch (Exception $e) {
            return $this->sendError($e, 'Error al enviar el acceso al portal');
        }
    }

    /**
     * Contraseña inicial = número de documento, sin puntos ni espacios. El correo de acceso le
     * pide cambiarla. Lo que la protege no es el costo del hash (el documento se adivina) sino el
     * código por correo de equipo nuevo y el límite de intentos del login; por eso 10 rondas en vez
     * de 12: con 12, 500 filas del Excel tardaban ~2 min (con 10, ~35 s).
     */
    private function nuevo(array $datos, int $idUsuarioRegistra): Usuario
    {
        $acudiente = Usuario::create([
            ...$this->limpiar($datos),
            'perfil' => Perfil::idParaCrear(TipoPerfil::ACUDIENTE),
            'estado' => 'activo',
            'user_log' => $idUsuarioRegistra,
        ]);

        // Por la consulta y no por el modelo: setPassAttribute volvería a hashear el hash.
        Usuario::whereKey($acudiente->id_user)->update([
            'pass' => Hash::make(self::claveDeDocumento($acudiente->documento), ['rounds' => self::RONDAS_CLAVE_INICIAL]),
        ]);

        return $acudiente;
    }

    private static function claveDeDocumento(?string $documento): string
    {
        return preg_replace('/[^0-9A-Za-z]/', '', (string) $documento);
    }

    /**
     * Qué dice el correo de acceso: `documento` (sigue con la contraseña inicial, debe cambiarla),
     * `sin_contrasena` (nunca tuvo, debe crearla) o `propia` (ya eligió una).
     */
    private function estadoContrasena(Usuario $acudiente): string
    {
        $pass = $acudiente->getRawOriginal('pass');

        return match (true) {
            blank($pass) => 'sin_contrasena',
            Hash::check(self::claveDeDocumento($acudiente->documento), $pass) => 'documento',
            default => 'propia',
        };
    }

    private function buscar(int $id): ?Usuario
    {
        return Usuario::where('id_user', $id)->whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::ACUDIENTE))->first();
    }

    /**
     * documento normalizado => [ids] de ese tipo (más de uno = documento repetido en la base),
     * comparando sin espacios ni puntos (hay documentos guardados así).
     *
     * @return array<string, array<int>>
     */
    private function idsPorDocumento(array $documentos, TipoPerfil $tipo): array
    {
        $documentos = array_values(array_unique(array_filter($documentos)));
        if (! $documentos) {
            return [];
        }

        return Usuario::whereIn('perfil', Perfil::idsDeTipo($tipo))
            ->whereIn(DB::raw("REGEXP_REPLACE(documento, '[^0-9A-Za-z]', '')"), $documentos)
            ->get(['id_user', 'documento'])
            ->groupBy(fn ($u) => $this->normalizarDocumento($u->documento))
            ->map(fn ($grupo) => $grupo->pluck('id_user')->all())
            ->all();
    }

    /** Para comparar el Excel con lo guardado: sin espacios de más y el correo sin mayúsculas. */
    private function normalizarCampo(string $campo, mixed $valor): string
    {
        $texto = trim((string) $valor);

        return $campo === 'correo' ? mb_strtolower($texto) : $texto;
    }

    private function normalizarDocumento(mixed $valor): string
    {
        // Excel entrega los documentos numéricos como float (1048082892.0).
        if (is_float($valor) && floor($valor) === $valor) {
            $valor = number_format($valor, 0, '', '');
        }

        return preg_replace('/[^0-9A-Za-z]/', '', (string) $valor);
    }

    /** Recorta espacios y deja el correo en minúsculas (el login lo compara tal cual). */
    private function limpiar(array $datos): array
    {
        return [
            'documento' => trim($datos['documento']),
            'nombre' => trim($datos['nombre']),
            'apellido' => filled($datos['apellido'] ?? null) ? trim($datos['apellido']) : null,
            'correo' => mb_strtolower(trim($datos['correo'])),
            'telefono' => filled($datos['telefono'] ?? null) ? trim($datos['telefono']) : null,
        ];
    }
}
