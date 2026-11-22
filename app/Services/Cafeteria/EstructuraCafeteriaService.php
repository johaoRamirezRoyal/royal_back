<?php

namespace App\Services\Cafeteria;

use App\Enums\Cafeteria\EstadoAlmacen;
use App\Enums\Cafeteria\EstadoAsignacion;
use App\Enums\Cafeteria\EstadoCafeteria;
use App\Enums\Cafeteria\EstadoPuntoVenta;
use App\Enums\Cafeteria\TipoAlmacen;
use App\Enums\Cafeteria\TipoPuntoVenta;
use App\Models\Cafeteria\Almacen;
use App\Models\Cafeteria\Cafeteria;
use App\Models\Cafeteria\HorarioCafeteria;
use App\Models\Cafeteria\HorarioPuntoVenta;
use App\Models\Cafeteria\PuntoVenta;
use App\Models\Cafeteria\RolOperativo;
use App\Models\Cafeteria\TerminalVenta;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Estructura de la cafetería (opción 176): cafeterías, puntos de venta, terminales, horarios,
 * almacenes y de qué almacenes se abastece cada punto. Las reglas viven aquí, no en el
 * frontend: estados por transición, nada nuevo en una cafetería cerrada, un solo almacén
 * predeterminado por punto y franjas horarias que no se cruzan.
 */
class EstructuraCafeteriaService
{
    use EjecutaOperacion;
    use GeneraCodigo;

    /** Catálogos para los formularios y filtros de la cafetería (estructura y personal). */
    public function catalogos(): array
    {
        try {
            $valores = fn (array $casos) => array_map(fn ($c) => $c->value, $casos);

            return ['error' => false, 'message' => 'Catálogos de la cafetería', 'data' => [
                'cafeterias' => Cafeteria::orderBy('nombre')->get(['id', 'codigo', 'nombre', 'estado']),
                'roles_operativos' => RolOperativo::orderBy('nombre')->get(['id', 'codigo', 'nombre', 'descripcion', 'activo']),
                'dias_semana' => DB::table('dias_semana')->orderBy('orden')->get(['id', 'nombre', 'abreviatura']),
                'estados_cafeteria' => $valores(EstadoCafeteria::cases()),
                'estados_punto_venta' => $valores(EstadoPuntoVenta::cases()),
                'tipos_punto_venta' => $valores(TipoPuntoVenta::cases()),
                'estados_almacen' => $valores(EstadoAlmacen::cases()),
                'tipos_almacen' => $valores(TipoAlmacen::cases()),
            ]];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    // ─── Cafeterías ─────────────────────────────────────────────

    public function listarCafeterias(): array
    {
        try {
            $cafeterias = Cafeteria::query()
                ->withCount([
                    'puntosVenta',
                    'almacenes',
                    'asignaciones as personal_vigente_count' => fn ($q) => $q->vigentes(),
                ])
                ->orderBy('nombre')
                ->get();

            return ['error' => false, 'message' => 'Cafeterías', 'data' => $cafeterias];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function detalleCafeteria(int $id): array
    {
        try {
            $cafeteria = Cafeteria::with([
                'horarios' => fn ($q) => $q->orderBy('id_dia_semana')->orderBy('hora_apertura'),
                'puntosVenta' => fn ($q) => $q->orderBy('nombre'),
                'puntosVenta.terminales' => fn ($q) => $q->orderBy('nombre'),
                'puntosVenta.horarios' => fn ($q) => $q->orderBy('id_dia_semana')->orderBy('hora_apertura'),
                'puntosVenta.almacenes:id,codigo,nombre,estado,id_cafeteria',
                'almacenes' => fn ($q) => $q->orderBy('nombre'),
            ])->find($id);

            return $cafeteria
                ? ['error' => false, 'message' => 'Cafetería', 'data' => $cafeteria]
                : ['error' => true, 'message' => 'La cafetería no existe', 'status' => 404];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Siempre nace en BORRADOR: se activa con `cambiarEstado` cuando tiene un punto de venta. El
     * código lo asigna el sistema (CAF-001…), como el de puntos, terminales, almacenes y funciones.
     */
    public function crearCafeteria(array $datos, int $idUser): array
    {
        return $this->operacion(fn () => $this->crearConConsecutivo(Cafeteria::query(), 'CAF', fn (string $codigo) => Cafeteria::create([
            ...$datos,
            'codigo' => $codigo,
            'estado' => EstadoCafeteria::BORRADOR,
            'creado_por' => $idUser,
        ])), 'Cafetería creada');
    }

    public function actualizarCafeteria(int $id, array $datos, int $idUser): array
    {
        return $this->operacion(function () use ($id, $datos, $idUser) {
            $cafeteria = $this->cafeteriaAbierta($id);
            $cafeteria->update([...self::sinCodigo($datos), 'actualizado_por' => $idUser]);

            return $cafeteria;
        }, 'Cafetería actualizada');
    }

    public function cambiarEstado(int $id, EstadoCafeteria $nuevo, int $idUser): array
    {
        return $this->operacion(function () use ($id, $nuevo, $idUser) {
            $cafeteria = Cafeteria::lockForUpdate()->find($id) ?? throw ReglaCafeteria::noExiste('La cafetería no existe');

            if (! in_array($nuevo, $cafeteria->estado->siguientes(), true)) {
                throw new ReglaCafeteria("Una cafetería en {$cafeteria->estado->value} no puede pasar a {$nuevo->value}");
            }

            if ($nuevo === EstadoCafeteria::ACTIVA
                && ! $cafeteria->puntosVenta()->where('estado', EstadoPuntoVenta::ACTIVO)->exists()) {
                throw new ReglaCafeteria('Para activar la cafetería necesita al menos un punto de venta activo');
            }

            $cafeteria->update(['estado' => $nuevo, 'actualizado_por' => $idUser]);

            return $cafeteria;
        }, 'Estado de la cafetería actualizado');
    }

    /** @param list<array{id_dia_semana:int, hora_apertura:string, hora_cierre:string}> $horarios */
    public function guardarHorariosCafeteria(int $id, array $horarios): array
    {
        return $this->operacion(function () use ($id, $horarios) {
            $this->cafeteriaAbierta($id);
            $this->validarFranjas($horarios);
            HorarioCafeteria::where('id_cafeteria', $id)->delete();
            foreach ($horarios as $h) {
                HorarioCafeteria::create([...$this->franja($h), 'id_cafeteria' => $id]);
            }

            return HorarioCafeteria::where('id_cafeteria', $id)->orderBy('id_dia_semana')->orderBy('hora_apertura')->get();
        }, 'Horario de la cafetería guardado');
    }

    // ─── Puntos de venta y terminales ───────────────────────────

    public function crearPuntoVenta(int $idCafeteria, array $datos): array
    {
        return $this->operacion(function () use ($idCafeteria, $datos) {
            $this->cafeteriaAbierta($idCafeteria);

            // PV-001… dentro de la cafetería (el código es único por cafetería).
            return $this->crearConConsecutivo(PuntoVenta::where('id_cafeteria', $idCafeteria), 'PV', fn (string $codigo) => PuntoVenta::create([
                ...$datos, 'codigo' => $codigo, 'id_cafeteria' => $idCafeteria,
            ]));
        }, 'Punto de venta creado');
    }

    public function actualizarPuntoVenta(int $id, array $datos): array
    {
        return $this->operacion(function () use ($id, $datos) {
            $punto = PuntoVenta::find($id) ?? throw ReglaCafeteria::noExiste('El punto de venta no existe');
            $this->cafeteriaAbierta($punto->id_cafeteria);
            $punto->update(self::sinCodigo($datos));

            return $punto;
        }, 'Punto de venta actualizado');
    }

    /** @param list<array{id_dia_semana:int, hora_apertura:string, hora_cierre:string}> $horarios */
    public function guardarHorariosPunto(int $id, array $horarios): array
    {
        return $this->operacion(function () use ($id, $horarios) {
            $punto = PuntoVenta::find($id) ?? throw ReglaCafeteria::noExiste('El punto de venta no existe');
            $this->cafeteriaAbierta($punto->id_cafeteria);
            $this->validarFranjas($horarios);
            HorarioPuntoVenta::where('id_punto_venta', $id)->delete();
            foreach ($horarios as $h) {
                HorarioPuntoVenta::create([...$this->franja($h), 'id_punto_venta' => $id]);
            }

            return HorarioPuntoVenta::where('id_punto_venta', $id)->orderBy('id_dia_semana')->orderBy('hora_apertura')->get();
        }, 'Horario del punto de venta guardado');
    }

    /**
     * Reemplaza los almacenes del punto. Con al menos uno, exactamente uno es el predeterminado.
     * Cada almacén debe estar activo y ser central o de la misma cafetería del punto.
     *
     * @param list<array{id_almacen:int, es_predeterminado:bool}> $almacenes
     */
    public function guardarAlmacenesPunto(int $id, array $almacenes): array
    {
        return $this->operacion(function () use ($id, $almacenes) {
            // lockForUpdate: dos guardados del mismo punto a la vez se esperan en vez de chocar
            // en el UNIQUE del predeterminado (que saldría como un 500).
            $punto = PuntoVenta::lockForUpdate()->find($id) ?? throw ReglaCafeteria::noExiste('El punto de venta no existe');
            $this->cafeteriaAbierta($punto->id_cafeteria);

            $predeterminados = count(array_filter($almacenes, fn ($a) => (bool) $a['es_predeterminado']));
            if ($almacenes !== [] && $predeterminados !== 1) {
                throw new ReglaCafeteria('Marca exactamente un almacén predeterminado para el punto de venta');
            }

            $ids = array_map(fn ($a) => (int) $a['id_almacen'], $almacenes);
            $validos = Almacen::whereIn('id', $ids)
                ->where('estado', EstadoAlmacen::ACTIVO)
                ->where(fn ($q) => $q->whereNull('id_cafeteria')->orWhere('id_cafeteria', $punto->id_cafeteria))
                ->count();
            if ($validos !== count($ids)) {
                throw new ReglaCafeteria('Solo se pueden usar almacenes activos que sean centrales o de la misma cafetería');
            }

            // Primero se quitan todos: el UNIQUE del predeterminado no admite dos a la vez ni un instante.
            $punto->almacenes()->detach();
            foreach ($almacenes as $a) {
                $punto->almacenes()->attach((int) $a['id_almacen'], ['es_predeterminado' => (bool) $a['es_predeterminado']]);
            }

            return $punto->load('almacenes:id,codigo,nombre,estado,id_cafeteria')->almacenes;
        }, 'Almacenes del punto de venta guardados');
    }

    public function crearTerminal(int $idPunto, array $datos): array
    {
        return $this->operacion(function () use ($idPunto, $datos) {
            $punto = PuntoVenta::find($idPunto) ?? throw ReglaCafeteria::noExiste('El punto de venta no existe');
            $this->cafeteriaAbierta($punto->id_cafeteria);

            // TER-001… dentro del punto (el código es único por punto).
            return $this->crearConConsecutivo(TerminalVenta::where('id_punto_venta', $idPunto), 'TER', fn (string $codigo) => TerminalVenta::create([
                ...$datos, 'codigo' => $codigo, 'id_punto_venta' => $idPunto,
            ]));
        }, 'Terminal creada');
    }

    public function actualizarTerminal(int $id, array $datos): array
    {
        return $this->operacion(function () use ($id, $datos) {
            $terminal = TerminalVenta::with('puntoVenta')->find($id) ?? throw ReglaCafeteria::noExiste('La terminal no existe');
            $this->cafeteriaAbierta($terminal->puntoVenta->id_cafeteria);
            $terminal->update(self::sinCodigo($datos));

            return $terminal;
        }, 'Terminal actualizada');
    }

    // ─── Almacenes ──────────────────────────────────────────────

    /** @param array{id_cafeteria?: int|string|null} $filtros  `id_cafeteria=central` = solo los centrales. */
    public function listarAlmacenes(array $filtros): array
    {
        try {
            $almacenes = Almacen::query()
                ->with('cafeteria:id,codigo,nombre')
                ->withCount('puntosVenta')
                ->when(($filtros['id_cafeteria'] ?? null) === 'central', fn ($q) => $q->whereNull('id_cafeteria'))
                ->when(is_numeric($filtros['id_cafeteria'] ?? null), fn ($q) => $q->where('id_cafeteria', (int) $filtros['id_cafeteria']))
                ->orderBy('nombre')
                ->get();

            return ['error' => false, 'message' => 'Almacenes', 'data' => $almacenes];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    public function crearAlmacen(array $datos, int $idUser): array
    {
        return $this->operacion(function () use ($datos, $idUser) {
            if (! empty($datos['id_cafeteria'])) {
                $this->cafeteriaAbierta((int) $datos['id_cafeteria']);
            }

            return $this->crearConConsecutivo(Almacen::query(), 'ALM', fn (string $codigo) => Almacen::create([
                ...$datos, 'codigo' => $codigo, 'creado_por' => $idUser,
            ]));
        }, 'Almacén creado');
    }

    /**
     * - Un almacén de una cafetería cerrada no se modifica (tampoco se saca de ella).
     * - Cambiar de cafetería no puede dejar a un punto de otra cafetería abastecido por un almacén
     *   que ya no le corresponde.
     * - No se desactiva ni se bloquea mientras sea el predeterminado de un punto activo.
     * - Sin `id_cafeteria` en los datos, conserva la que tiene.
     */
    public function actualizarAlmacen(int $id, array $datos): array
    {
        return $this->operacion(function () use ($id, $datos) {
            $almacen = Almacen::lockForUpdate()->find($id) ?? throw ReglaCafeteria::noExiste('El almacén no existe');
            if ($almacen->id_cafeteria !== null) {
                $this->cafeteriaAbierta($almacen->id_cafeteria);
            }

            $nuevaCafeteria = array_key_exists('id_cafeteria', $datos) ? ($datos['id_cafeteria'] ?: null) : $almacen->id_cafeteria;

            $nuevoEstado = isset($datos['estado']) ? EstadoAlmacen::from((string) $datos['estado']) : $almacen->estado;
            if ($nuevoEstado !== EstadoAlmacen::ACTIVO) {
                $esPredeterminado = $almacen->puntosVenta()
                    ->wherePivot('es_predeterminado', true)
                    ->where('caf_puntos_venta.estado', EstadoPuntoVenta::ACTIVO)
                    ->exists();
                if ($esPredeterminado) {
                    throw new ReglaCafeteria('El almacén es el predeterminado de un punto de venta activo; cambia el predeterminado de ese punto antes de desactivarlo');
                }
            }

            if ($nuevaCafeteria !== null) {
                $this->cafeteriaAbierta((int) $nuevaCafeteria);
                $ajenos = $almacen->puntosVenta()->where('caf_puntos_venta.id_cafeteria', '!=', $nuevaCafeteria)->exists();
                if ($ajenos) {
                    throw new ReglaCafeteria('El almacén abastece puntos de venta de otra cafetería; quítalo de esos puntos antes de cambiarlo');
                }
            }

            $almacen->update([...self::sinCodigo($datos), 'id_cafeteria' => $nuevaCafeteria]);

            return $almacen;
        }, 'Almacén actualizado');
    }

    // ─── Funciones del personal (roles operativos) ──────────────

    public function crearRol(array $datos): array
    {
        // El código sale del nombre (AUXILIAR_DE_CAJA), con el estilo de los sembrados (CAJERO, COCINA).
        return $this->operacion(fn () => $this->crearConCodigoDeNombre(RolOperativo::query(), (string) $datos['nombre'], 30, fn (string $codigo) => RolOperativo::create([
            ...$datos, 'codigo' => $codigo,
        ])), 'Función creada');
    }

    public function actualizarRol(int $id, array $datos): array
    {
        return $this->operacion(function () use ($id, $datos) {
            $rol = RolOperativo::find($id) ?? throw ReglaCafeteria::noExiste('La función no existe');
            $rol->update(self::sinCodigo($datos));

            return $rol;
        }, 'Función actualizada');
    }

    // ─── Apoyo ──────────────────────────────────────────────────

    /** La cafetería, si existe y no está cerrada (una cerrada ya no se modifica). */
    private function cafeteriaAbierta(int $id): Cafeteria
    {
        $cafeteria = Cafeteria::find($id) ?? throw ReglaCafeteria::noExiste('La cafetería no existe');

        if ($cafeteria->estado === EstadoCafeteria::CERRADA) {
            throw new ReglaCafeteria('La cafetería está cerrada: ya no se puede modificar');
        }

        return $cafeteria;
    }

    /** Franjas de un mismo día que se cruzan (las horas ya vienen validadas: cierre > apertura). */
    private function validarFranjas(array $horarios): void
    {
        $porDia = [];
        foreach ($horarios as $h) {
            $porDia[(int) $h['id_dia_semana']][] = [$h['hora_apertura'], $h['hora_cierre']];
        }

        foreach ($porDia as $franjas) {
            usort($franjas, fn ($a, $b) => strcmp($a[0], $b[0]));
            for ($i = 1; $i < count($franjas); $i++) {
                if ($franjas[$i][0] < $franjas[$i - 1][1]) {
                    throw new ReglaCafeteria('Hay franjas del mismo día que se cruzan');
                }
            }
        }
    }

    private function franja(array $h): array
    {
        return [
            'id_dia_semana' => (int) $h['id_dia_semana'],
            'hora_apertura' => $h['hora_apertura'],
            'hora_cierre' => $h['hora_cierre'],
            'activo' => true,
        ];
    }

    /** El código lo asigna el sistema al crear y no cambia: se descarta si llega al editar. */
    private static function sinCodigo(array $datos): array
    {
        unset($datos['codigo']);

        return $datos;
    }
}
