<?php

namespace App\Services\Noticias;

use App\Models\Noticias\ConfiguracionNoticias;
use App\Models\Noticias\CumpleanosGenerado;
use App\Models\Noticias\MensajeProgramado;
use App\Models\Noticias\PlantillaCumpleanos;
use App\Models\PerfilUsuario\InfoAdicionalUsuario;
use App\Models\Usuarios\Usuario;
use App\Services\AsistenciaTrabajadores\AsistenciaGestionService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Configuración de cumpleaños (Noticias → Noticias programadas). Plantillas por género,
 * datos del personal (fecha de nacimiento y género de `info_adicional_usuarios`, foto de
 * `foto_perfil` o `foto_carnet`) y la generación automática: cada cumpleaños se genera
 * `cumpleanos_dias_anticipacion` días antes como una noticia programada normal
 * (`asistencia_mensaje`, tipo cumpleanos, con la imagen ya compuesta), así el envío por
 * correo y el Home siguen funcionando sin cambios (NoticiasService).
 */
class CumpleanosService
{
    public const GENEROS = ['masculino', 'femenino'];

    // Ejemplo de la vista previa sin usuario elegido (no es un dato real).
    private const NOMBRE_EJEMPLO = ['María Fernanda', 'Rodríguez Gómez'];

    public function __construct(private CumpleanosImagen $imagen)
    {
    }

    private function disco(): Filesystem
    {
        return Storage::disk(config('filesystems.uploads_disk', 'public'));
    }

    // ── Plantillas ──────────────────────────────────────────────

    public function listarPlantillas(): array
    {
        $config = ConfiguracionNoticias::actual();

        $plantillas = PlantillaCumpleanos::orderByDesc('activo')->orderBy('genero')->orderBy('nombre')->get()
            ->map(fn (PlantillaCumpleanos $p) => $this->serializarPlantilla($p, $config));

        return ['error' => false, 'data' => $plantillas];
    }

    public function obtenerPlantilla(int $id): array
    {
        $plantilla = PlantillaCumpleanos::find($id);
        if (!$plantilla) {
            return ['error' => true, 'message' => 'La plantilla no existe'];
        }

        return ['error' => false, 'data' => $this->serializarPlantilla($plantilla, ConfiguracionNoticias::actual())];
    }

    public function guardarPlantilla(?int $id, array $datos, int $idLog): array
    {
        try {
            $plantilla = $id ? PlantillaCumpleanos::find($id) : new PlantillaCumpleanos(['id_log' => $idLog]);
            if (!$plantilla) {
                return ['error' => true, 'message' => 'La plantilla no existe'];
            }

            $imagen = $datos['imagen'] ?? null;
            if ($datos['activo'] && !$imagen) {
                return ['error' => true, 'message' => 'Sube la imagen de la plantilla antes de activarla'];
            }

            // Medidas y transparencia se leen del archivo, no del cliente.
            if ($imagen && $imagen !== $plantilla->imagen) {
                if (!$this->disco()->exists($imagen)) {
                    return ['error' => true, 'message' => 'La imagen subida ya no existe; súbela de nuevo'];
                }
                $plantilla->fill($this->imagen->inspeccionar($this->disco()->path($imagen)));
            }
            if (!$imagen) {
                $plantilla->fill(['ancho' => null, 'alto' => null, 'transparente' => false]);
            }

            $anterior = $plantilla->imagen;
            $plantilla->fill([
                'nombre' => trim($datos['nombre']),
                'descripcion' => isset($datos['descripcion']) && trim($datos['descripcion']) !== '' ? trim($datos['descripcion']) : null,
                'genero' => $datos['genero'],
                'activo' => (bool) $datos['activo'],
                'imagen' => $imagen,
                'elementos' => CumpleanosImagen::normalizar($datos['elementos'] ?? null),
            ])->save();

            if ($anterior && $anterior !== $imagen) {
                $this->disco()->delete($anterior);
            }
            $this->soltarDeConfiguracionSiNoAplica($plantilla);

            return [
                'error' => false,
                'message' => $id ? 'Plantilla guardada' : 'Plantilla creada',
                'data' => $this->serializarPlantilla($plantilla->fresh(), ConfiguracionNoticias::actual()),
            ];
        } catch (Throwable $e) {
            Log::error('Cumpleaños: error al guardar plantilla. ' . $e->getMessage());

            return ['error' => true, 'message' => 'No se pudo guardar la plantilla: ' . $e->getMessage()];
        }
    }

    public function cambiarEstadoPlantilla(int $id, bool $activo): array
    {
        $plantilla = PlantillaCumpleanos::find($id);
        if (!$plantilla) {
            return ['error' => true, 'message' => 'La plantilla no existe'];
        }
        if ($activo && !$plantilla->imagen) {
            return ['error' => true, 'message' => 'Sube la imagen de la plantilla antes de activarla'];
        }

        $plantilla->update(['activo' => $activo]);
        $this->soltarDeConfiguracionSiNoAplica($plantilla);

        return ['error' => false, 'message' => $activo ? 'Plantilla activada' : 'Plantilla desactivada'];
    }

    public function duplicarPlantilla(int $id, int $idLog): array
    {
        $original = PlantillaCumpleanos::find($id);
        if (!$original) {
            return ['error' => true, 'message' => 'La plantilla no existe'];
        }

        $copia = $original->replicate(['activo']);
        $copia->fill(['nombre' => Str::limit($original->nombre . ' (copia)', 120, ''), 'activo' => false, 'id_log' => $idLog]);

        // Copia propia del archivo: eliminar o reemplazar la imagen de una no debe dejar a la otra sin imagen.
        if ($original->imagen && $this->disco()->exists($original->imagen)) {
            $ruta = 'noticias/cumpleanos/plantillas/' . Str::uuid() . '.' . pathinfo($original->imagen, PATHINFO_EXTENSION);
            $this->disco()->copy($original->imagen, $ruta);
            $copia->imagen = $ruta;
        }
        $copia->save();

        return ['error' => false, 'message' => 'Plantilla duplicada (inactiva)', 'data' => ['id' => $copia->id]];
    }

    public function eliminarPlantilla(int $id): array
    {
        $plantilla = PlantillaCumpleanos::find($id);
        if (!$plantilla) {
            return ['error' => true, 'message' => 'La plantilla no existe'];
        }

        $plantilla->delete();
        $this->soltarDeConfiguracionSiNoAplica($plantilla, true);
        if ($plantilla->imagen) {
            $this->disco()->delete($plantilla->imagen);
        }

        return ['error' => false, 'message' => 'Plantilla eliminada'];
    }

    /** Quita la plantilla de la configuración si ya no puede usarse (inactiva, otro género o eliminada). */
    private function soltarDeConfiguracionSiNoAplica(PlantillaCumpleanos $plantilla, bool $eliminada = false): void
    {
        $config = ConfiguracionNoticias::actual();
        foreach (self::GENEROS as $genero) {
            $campo = "cumpleanos_plantilla_{$genero}";
            if ($config->{$campo} === $plantilla->id && ($eliminada || !$plantilla->activo || $plantilla->genero !== $genero)) {
                $config->{$campo} = null;
            }
        }
        $config->save();
    }

    private function serializarPlantilla(PlantillaCumpleanos $p, ConfiguracionNoticias $config): array
    {
        return [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'descripcion' => $p->descripcion,
            'genero' => $p->genero,
            'activo' => $p->activo,
            'imagen' => $p->imagen,
            'ancho' => $p->ancho,
            'alto' => $p->alto,
            'transparente' => $p->transparente,
            'elementos' => CumpleanosImagen::normalizar($p->elementos),
            'en_uso' => $config->{"cumpleanos_plantilla_{$p->genero}"} === $p->id,
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    public function inspeccionarImagen(string $ruta): array
    {
        return $this->imagen->inspeccionar($this->disco()->path($ruta));
    }

    /** JPEG de la felicitación con la persona elegida, o con un nombre de ejemplo y sin foto. */
    public function vistaPrevia(string $imagen, array $elementos, ?int $idUser): array
    {
        if (str_contains($imagen, '..') || !$this->disco()->exists($imagen)) {
            return ['error' => true, 'message' => 'La imagen de la plantilla no existe'];
        }

        $persona = $idUser ? $this->personal($idUser)->first() : null;
        if ($idUser && !$persona) {
            return ['error' => true, 'message' => 'La persona elegida no está en el personal activo'];
        }

        $e = CumpleanosImagen::normalizar($elementos);
        [$nombre, $apellido] = $persona ? [$persona['nombre'], $persona['apellido']] : self::NOMBRE_EJEMPLO;

        return [
            'error' => false,
            'data' => $this->imagen->componer(
                $this->disco()->path($imagen),
                $e,
                CumpleanosImagen::formatearNombre($nombre, $apellido, $e['nombre']['formato'], $e['nombre']['mayusculas']),
                $persona && $persona['foto_existe'] ? $this->disco()->path($persona['foto']) : null,
            ),
        ];
    }

    // ── Personal ────────────────────────────────────────────────

    /**
     * Personal activo (sin estudiantes, egresados, acudientes ni perfil 28 — los mismos
     * excluidos de la asistencia de trabajadores) con sus datos de cumpleaños y qué les falta.
     * Con `$idUser`, solo esa persona (colección vacía si no es del personal activo).
     */
    public function personal(?int $idUser = null): \Illuminate\Support\Collection
    {
        $hoy = Carbon::today();
        $excluidos = DB::table('cumpleanos_excluidos')->pluck('id_user')->flip();
        $disco = $this->disco();

        return Usuario::query()
            ->from('usuarios as u')
            ->leftJoin('info_adicional_usuarios as ia', 'ia.id_user', '=', 'u.id_user')
            ->leftJoin('perfiles as p', 'p.id_perfil', '=', 'u.perfil')
            ->where('u.estado', 'activo')
            ->whereNotIn('u.perfil', AsistenciaGestionService::PERFILES_EXCLUIDOS_ASISTENCIA)
            ->when($idUser, fn ($q) => $q->where('u.id_user', $idUser))
            ->select([
                'u.id_user', 'u.nombre', 'u.apellido', 'u.foto_carnet', 'u.perfil',
                'p.nombre as nombre_perfil', 'ia.fecha_nacimiento', 'ia.genero',
                DB::raw('(SELECT fp.nombre_foto FROM foto_perfil fp WHERE fp.id_user = u.id_user AND fp.activo = 1 ORDER BY fp.id DESC LIMIT 1) AS foto_perfil'),
            ])
            ->orderBy('u.nombre')
            ->toBase()
            ->get()
            ->map(function ($u) use ($hoy, $excluidos, $disco) {
                $foto = $u->foto_perfil ?: ($u->foto_carnet ?: null);
                $fotoExiste = $foto && !str_contains($foto, '..') && $disco->exists($foto);
                $genero = $this->normalizarGenero($u->genero);
                $nacimiento = $u->fecha_nacimiento ? Carbon::parse($u->fecha_nacimiento) : null;

                $faltantes = array_keys(array_filter([
                    'fecha' => !$nacimiento,
                    'genero' => !$genero,
                    'foto' => !$fotoExiste,
                ]));
                $proximo = $nacimiento ? $this->proximoCumpleanos($nacimiento, $hoy) : null;

                return [
                    'id_user' => (int) $u->id_user,
                    'nombre' => $u->nombre,
                    'apellido' => $u->apellido,
                    'nombre_completo' => CumpleanosImagen::formatearNombre($u->nombre, $u->apellido, 'completo'),
                    'perfil' => $u->nombre_perfil,
                    'foto' => $fotoExiste ? $foto : null,
                    // Registrada en la BD pero sin archivo: se muestra como "la foto no se encuentra".
                    'foto_registrada' => (bool) $foto,
                    'foto_existe' => $fotoExiste,
                    'fecha_nacimiento' => $nacimiento?->toDateString(),
                    'genero' => $genero,
                    'participa' => !isset($excluidos[$u->id_user]),
                    'faltantes' => $faltantes,
                    'proximo_cumpleanos' => $proximo?->toDateString(),
                    'dias_para' => $proximo ? (int) $hoy->diffInDays($proximo) : null,
                ];
            });
    }

    private function normalizarGenero(?string $genero): ?string
    {
        return match (mb_strtolower(trim((string) $genero))) {
            'masculino', 'm', 'hombre' => 'masculino',
            'femenino', 'f', 'mujer' => 'femenino',
            default => null,
        };
    }

    /** Próximo cumpleaños desde hoy (incluido). El 29 de febrero cae el 28 en años no bisiestos. */
    private function proximoCumpleanos(Carbon $nacimiento, Carbon $hoy): Carbon
    {
        $en = function (int $anio) use ($nacimiento) {
            $dia = $nacimiento->month === 2 && $nacimiento->day === 29 && !Carbon::create($anio)->isLeapYear() ? 28 : $nacimiento->day;

            return Carbon::create($anio, $nacimiento->month, $dia)->startOfDay();
        };
        $fecha = $en($hoy->year);

        return $fecha->lt($hoy) ? $en($hoy->year + 1) : $fecha;
    }

    public function listarPersonal(): array
    {
        return ['error' => false, 'data' => $this->personal()->values()];
    }

    public function actualizarDatosPersona(int $idUser, ?string $fechaNacimiento, ?string $genero): array
    {
        if ($this->personal($idUser)->isEmpty()) {
            return ['error' => true, 'message' => 'La persona no está en el personal activo'];
        }

        InfoAdicionalUsuario::updateOrCreate(
            ['id_user' => $idUser],
            ['fecha_nacimiento' => $fechaNacimiento, 'genero' => $genero],
        );

        return ['error' => false, 'message' => 'Datos de cumpleaños guardados', 'data' => $this->personal($idUser)->first()];
    }

    public function cambiarParticipacion(array $ids, bool $participa): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($participa) {
            DB::table('cumpleanos_excluidos')->whereIn('id_user', $ids)->delete();
        } else {
            DB::table('cumpleanos_excluidos')->insertOrIgnore(array_map(fn ($id) => ['id_user' => $id], $ids));
        }

        $n = count($ids);

        return [
            'error' => false,
            'message' => $participa
                ? ($n === 1 ? '1 persona vuelve a la automatización' : "{$n} personas vuelven a la automatización")
                : ($n === 1 ? '1 persona quedó fuera de la automatización' : "{$n} personas quedaron fuera de la automatización"),
        ];
    }

    // ── Automatización ──────────────────────────────────────────

    public function obtenerConfiguracion(): array
    {
        $c = ConfiguracionNoticias::actual();

        return [
            'error' => false,
            'data' => [
                'automatico' => $c->cumpleanos_automatico,
                'plantilla_masculino' => $c->cumpleanos_plantilla_masculino,
                'plantilla_femenino' => $c->cumpleanos_plantilla_femenino,
                'dias_anticipacion' => $c->cumpleanos_dias_anticipacion,
                'titulo' => $c->cumpleanos_titulo,
                'enviar_correos' => $c->enviar_correos_programadas,
            ],
        ];
    }

    public function actualizarConfiguracion(array $datos): array
    {
        foreach (self::GENEROS as $genero) {
            $id = $datos["plantilla_{$genero}"] ?? null;
            if ($id && !PlantillaCumpleanos::where('id', $id)->where('genero', $genero)->where('activo', true)->exists()) {
                return ['error' => true, 'message' => "La plantilla elegida para {$genero} no existe, no está activa o es de otro género"];
            }
        }

        ConfiguracionNoticias::actual()->update([
            'cumpleanos_automatico' => (bool) $datos['automatico'],
            'cumpleanos_plantilla_masculino' => $datos['plantilla_masculino'] ?? null,
            'cumpleanos_plantilla_femenino' => $datos['plantilla_femenino'] ?? null,
            'cumpleanos_dias_anticipacion' => (int) $datos['dias_anticipacion'],
            'cumpleanos_titulo' => trim($datos['titulo']),
        ]);

        return ['error' => false, 'message' => 'Automatización guardada', 'data' => $this->obtenerConfiguracion()['data']];
    }

    /** Plantilla activa configurada para el género, o null. */
    private function plantillaPara(?string $genero, ConfiguracionNoticias $config): ?PlantillaCumpleanos
    {
        $id = $genero ? $config->{"cumpleanos_plantilla_{$genero}"} : null;

        return $id ? PlantillaCumpleanos::where('id', $id)->where('activo', true)->whereNotNull('imagen')->first() : null;
    }

    /**
     * Próximos cumpleaños del personal que participa, con lo que va a pasar:
     * generada / error (ya se intentó), programada (se generará en `fecha_generacion`) o
     * pendiente (algo lo impide, en `detalle`). Quien no tiene fecha de nacimiento no
     * tiene fecha que mostrar: va en `resumen.sin_fecha`.
     */
    public function listarProximos(int $dias = 45): array
    {
        $config = ConfiguracionNoticias::actual();
        $hoy = Carbon::today();
        $personal = $this->personal()->where('participa', true);
        $plantillas = ['masculino' => $this->plantillaPara('masculino', $config), 'femenino' => $this->plantillaPara('femenino', $config)];

        $proximos = $personal->filter(fn ($p) => $p['dias_para'] !== null && $p['dias_para'] <= $dias)->sortBy('dias_para');
        $generados = CumpleanosGenerado::whereIn('id_user', $proximos->pluck('id_user'))->get()
            ->keyBy(fn ($g) => "{$g->id_user}-{$g->anio}");
        $mensajes = MensajeProgramado::whereIn('id', $generados->pluck('id_mensaje')->filter())->pluck('id')->flip();

        $filas = $proximos->map(function ($p) use ($config, $hoy, $plantillas, $generados, $mensajes) {
            $fecha = Carbon::parse($p['proximo_cumpleanos']);
            $g = $generados->get("{$p['id_user']}-{$fecha->year}");
            $plantilla = $p['genero'] ? $plantillas[$p['genero']] : null;
            $fila = [
                'id_user' => $p['id_user'],
                'nombre_completo' => $p['nombre_completo'],
                'perfil' => $p['perfil'],
                'foto' => $p['foto'],
                'genero' => $p['genero'],
                'fecha' => $p['proximo_cumpleanos'],
                'dias_para' => $p['dias_para'],
                'plantilla' => $plantilla ? ['id' => $plantilla->id, 'nombre' => $plantilla->nombre] : null,
                'fecha_generacion' => null,
                'id_mensaje' => null,
            ];

            if ($g && $g->estado === 'generada') {
                $existe = $g->id_mensaje && isset($mensajes[$g->id_mensaje]);

                return $fila + [
                    'estado' => 'generada',
                    'detalle' => $existe ? 'Noticia creada el ' . $g->updated_at->locale('es')->translatedFormat('j \d\e F') : 'La noticia generada fue eliminada',
                    'id_mensaje' => $existe ? $g->id_mensaje : null,
                ];
            }

            $faltan = array_intersect($p['faltantes'], ['genero', 'foto']);
            $motivo = match (true) {
                $faltan === ['foto'] && $p['foto_registrada'] => 'No se encuentra el archivo de la foto',
                (bool) $faltan => 'Falta ' . implode(' y ', array_map(fn ($f) => $f === 'foto' ? 'la foto' : 'el género', $faltan)),
                !$plantilla => 'No hay una plantilla activa para ' . $p['genero'],
                !$config->cumpleanos_automatico => 'La automatización está desactivada',
                default => null,
            };

            if ($g && $g->estado === 'error') {
                return array_merge($fila, ['estado' => 'error', 'detalle' => $g->error]);
            }
            if ($motivo) {
                return array_merge($fila, ['estado' => 'pendiente', 'detalle' => $motivo]);
            }

            $generacion = $fecha->copy()->subDays($config->cumpleanos_dias_anticipacion);
            $generacion = $generacion->lt($hoy) ? $hoy : $generacion;

            return array_merge($fila, [
                'estado' => 'programada',
                'detalle' => $generacion->isSameDay($hoy) ? 'Se genera en la próxima hora' : 'Se genera el ' . $generacion->locale('es')->translatedFormat('j \d\e F'),
                'fecha_generacion' => $generacion->toDateString(),
            ]);
        })->values();

        return [
            'error' => false,
            'data' => [
                'proximos' => $filas,
                'resumen' => [
                    'personal' => $personal->count(),
                    'listos' => $personal->filter(fn ($p) => !$p['faltantes'])->count(),
                    'incompletos' => $personal->filter(fn ($p) => (bool) $p['faltantes'])->count(),
                    'sin_fecha' => $personal->filter(fn ($p) => in_array('fecha', $p['faltantes'], true))->count(),
                    'sin_plantilla' => array_keys(array_filter($plantillas, fn ($p) => !$p)),
                    'dias' => $dias,
                ],
            ],
        ];
    }

    /**
     * Genera las felicitaciones que ya entraron en su ventana (hoy ≥ cumpleaños −
     * anticipación). Corre cada hora antes del envío (EnviarNoticiasDiariasCommand); un
     * error queda en `cumpleanos_generados` y se reintenta en la siguiente hora.
     * Nunca usa una plantilla de otro género: sin plantilla activa para el género, no genera.
     */
    public function generarPendientes(): array
    {
        $config = ConfiguracionNoticias::actual();
        if (!$config->cumpleanos_automatico) {
            return ['error' => false, 'message' => 'La automatización de cumpleaños está desactivada', 'data' => ['generadas' => 0, 'errores' => 0]];
        }

        $hoy = Carbon::today();
        $generadas = 0;
        $errores = 0;

        $personal = $this->personal()->filter(fn ($p) => $p['participa'] && !$p['faltantes']
            && $p['dias_para'] <= $config->cumpleanos_dias_anticipacion);

        foreach ($personal as $p) {
            $fecha = Carbon::parse($p['proximo_cumpleanos']);
            $previo = CumpleanosGenerado::where('id_user', $p['id_user'])->where('anio', $fecha->year)->first();
            if ($previo?->estado === 'generada') {
                continue;
            }

            $plantilla = $this->plantillaPara($p['genero'], $config);
            if (!$plantilla) {
                continue;
            }

            $this->generarPara($p, $fecha, $plantilla, $config)->estado === 'generada' ? $generadas++ : $errores++;
        }

        Log::info("Cumpleaños: {$generadas} felicitaciones generadas, {$errores} con error ({$hoy->toDateString()}).");

        return [
            'error' => false,
            'message' => $generadas || $errores
                ? "Felicitaciones generadas: {$generadas}" . ($errores ? ". Con error: {$errores}" : '')
                : 'No había felicitaciones por generar ahora',
            'data' => ['generadas' => $generadas, 'errores' => $errores],
        ];
    }

    /** Borra la felicitación generada (noticia + imagen) de una persona y la vuelve a generar con la plantilla actual. */
    public function regenerar(int $idUser): array
    {
        $p = $this->personal($idUser)->first();
        if (!$p || !$p['proximo_cumpleanos']) {
            return ['error' => true, 'message' => 'La persona no está en el personal activo o no tiene fecha de nacimiento'];
        }
        if ($p['faltantes']) {
            return ['error' => true, 'message' => 'Completa los datos de cumpleaños de la persona antes de generar la felicitación'];
        }

        $config = ConfiguracionNoticias::actual();
        $plantilla = $this->plantillaPara($p['genero'], $config);
        if (!$plantilla) {
            return ['error' => true, 'message' => "No hay una plantilla activa para {$p['genero']}: no se genera con una plantilla de otro género"];
        }

        $fecha = Carbon::parse($p['proximo_cumpleanos']);
        $previo = CumpleanosGenerado::where('id_user', $idUser)->where('anio', $fecha->year)->first();
        if ($previo?->id_mensaje && ($mensaje = MensajeProgramado::find($previo->id_mensaje))) {
            if ($mensaje->enviado_at) {
                return ['error' => true, 'message' => 'Esa felicitación ya se envió por correo; no se vuelve a generar'];
            }
            if ($mensaje->imagen) {
                $this->disco()->delete($mensaje->imagen);
            }
            $mensaje->delete();
        }

        $g = $this->generarPara($p, $fecha, $plantilla, $config);

        return $g->estado === 'generada'
            ? ['error' => false, 'message' => 'Felicitación generada de nuevo']
            : ['error' => true, 'message' => 'No se pudo generar: ' . $g->error];
    }

    private function generarPara(array $p, Carbon $fecha, PlantillaCumpleanos $plantilla, ConfiguracionNoticias $config): CumpleanosGenerado
    {
        $registro = CumpleanosGenerado::firstOrNew(['id_user' => $p['id_user'], 'anio' => $fecha->year]);
        $registro->fill(['fecha' => $fecha->toDateString(), 'id_plantilla' => $plantilla->id]);

        try {
            $e = CumpleanosImagen::normalizar($plantilla->elementos);
            $jpeg = $this->imagen->componer(
                $this->disco()->path($plantilla->imagen),
                $e,
                CumpleanosImagen::formatearNombre($p['nombre'], $p['apellido'], $e['nombre']['formato'], $e['nombre']['mayusculas']),
                $this->disco()->path($p['foto']),
            );
            $ruta = 'noticias/cumpleanos/' . Str::uuid() . '.jpg';
            $this->disco()->put($ruta, $jpeg);

            $mensaje = MensajeProgramado::create([
                'fecha' => $fecha->toDateString(),
                'titulo' => Str::limit(str_replace('{nombre}', CumpleanosImagen::formatearNombre($p['nombre'], $p['apellido'], 'nombre_apellido'), $config->cumpleanos_titulo), 200, ''),
                'imagen' => $ruta,
                'mensaje' => null,
                'nivel' => 0,
                'tipo' => 'cumpleanos',
                'activo' => 1,
            ]);

            $registro->fill(['estado' => 'generada', 'id_mensaje' => $mensaje->id, 'error' => null])->save();
        } catch (Throwable $e) {
            Log::error("Cumpleaños: no se pudo generar la felicitación de {$p['id_user']}. {$e->getMessage()}");
            $registro->fill(['estado' => 'error', 'id_mensaje' => null, 'error' => Str::limit($e->getMessage(), 500)])->save();
        }

        return $registro;
    }
}
