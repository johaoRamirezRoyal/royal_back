<?php

namespace App\Services\Reservas;

use App\Enums\Mails;
use App\Mail\ReservaEncuestaMail;
use App\Models\Encuestas\EncuestaSalon;
use App\Models\Inventario\Inventario;
use App\Models\Prestamos\PrestamosInventario;
use App\Models\Reservas\ConfiguracionReservas;
use App\Models\Reservas\Horas;
use App\Models\Reservas\Reservas;
use App\Models\Reservas\Salones;
use App\Models\Usuarios\Usuario;
use App\Services\branding\MarcaDominioService;
use App\Services\MailService;
use App\Services\Prestamos\PrestamosService;
use App\Services\Service;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReservasServices extends Service
{
    public function __construct(
        protected PrestamosService $prestamosService,
    ) {}

    /**
     * Los portátiles no son un recurso del salón (no hay columna "cantidad de portátiles"
     * en `salones`): son un pool de inventario compartido por todo el colegio
     * (Inventario con descripcion LIKE '%PORTATIL%'), reservable para cualquier salón.
     * La disponibilidad se calcula por franja fecha+hora, no por salón. Público (antes
     * privado) para que el frontend pueda consultarla antes de enviar la reserva, en vez
     * de enterarse del choque recién al hacer submit.
     */
    public function validarDisponibilidadPortatil(string $fecha, int $hora): array
    {
        $total = Inventario::where('descripcion', 'like', '%PORTATIL%')
            ->where('activo', 1)
            ->whereNotIn('estado', [2, 5, 8])
            ->count();

        $reservados = Reservas::whereDate('fecha_reserva', $fecha)
            ->where('hora_reserva', $hora)
            ->where('activo', 1)
            ->whereNull('fecha_cancelado')
            ->sum('portatil');

        return [
            'reservados' => $reservados,
            'total' => $total,
            'disponibles' => $total - $reservados,
        ];
    }

    /**
     * `$sinLimiteFecha`: el Super Admin (perfil 1) reserva sin la ventana de tiempo de
     * validarFechaReserva (anticipación, domingo, corte de las 12:00). El resto de
     * validaciones (choque de salón) sigue aplicando.
     *
     * Portátiles y sonido NO se validan por ahora (ni disponibilidad ni recurso del salón):
     * las reglas anteriores estaban mal (conteo doble de portátiles prestados, `'no'`
     * truthy en sonido) y se rediseñarán. Si no alcanzan los portátiles, el préstamo
     * automático presta los que haya.
     */
    public function crearReserva(array $data, bool $sinLimiteFecha = false): array
    {
        $salon = Salones::activo()->find($data['id_salon']);

        if (! $salon) {
            return [
                'error' => true,
                'message' => 'El salón no existe o está inactivo.',
                'data' => [],
            ];
        }

        $usuario = Usuario::find($data['id_user']);

        if (! $usuario) {
            return [
                'error' => true,
                'message' => 'El usuario no existe.',
                'data' => [],
            ];
        }

        $fechas = (array) ($data['fecha_reserva'] ?? []);
        $horas = (array) ($data['hora_reserva'] ?? []);
        $portatilSolicitado = $data['portatil'] ?? 0;

        $combos = collect($fechas)->crossJoin($horas)->map(function ($combo) {
            return ['fecha' => $combo[0], 'hora' => $combo[1]];
        })->unique(function ($combo) {
            return $combo['fecha'].'-'.$combo['hora'];
        })->values();

        if ($combos->isEmpty()) {
            return [
                'error' => true,
                'message' => 'Debe especificar al menos una fecha y hora.',
                'data' => [],
            ];
        }

        $configReservas = ConfiguracionReservas::actual();

        // Una sola consulta para todo el rango: como los combos son fechas × horas,
        // cualquier reserva activa del salón en esas fechas y horas es un choque. Se
        // informan todos (con título y dueño) para que el usuario sepa con qué reserva choca.
        $choques = Reservas::with(['usuario:id_user,nombre,apellido', 'hora:id,horas'])
            ->where('id_salon', $data['id_salon'])
            ->whereIn('fecha_reserva', $fechas)
            ->whereIn('hora_reserva', $horas)
            ->where('activo', 1)
            ->whereNull('fecha_cancelado')
            ->orderBy('fecha_reserva')
            ->orderBy('hora_reserva')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'fecha' => (string) $r->fecha_reserva,
                'hora' => trim(str_replace("\n", ' ', $r->hora?->horas ?? (string) $r->hora_reserva)),
                'titulo' => $r->titulo,
                'usuario' => trim(($r->usuario?->nombre ?? '').' '.($r->usuario?->apellido ?? '')),
            ]);

        if ($choques->isNotEmpty()) {
            $detalle = $choques->map(fn ($c) => "{$c['fecha']} {$c['hora']} — «".($c['titulo'] ?: 'Sin título')."» ({$c['usuario']})")->implode('; ');

            return [
                'error' => true,
                'message' => "El salón ya está reservado en el rango elegido: {$detalle}.",
                'data' => $choques->all(),
            ];
        }

        foreach ($combos as $combo) {
            if (! strtotime($combo['fecha'])) {
                return [
                    'error' => true,
                    'message' => "La fecha {$combo['fecha']} no tiene un formato válido (Y-m-d).",
                    'data' => [],
                ];
            }

            $fechaReserva = Carbon::parse($combo['fecha'])->startOfDay();

            if (! $sinLimiteFecha && ($error = $this->validarFechaReserva($fechaReserva, Carbon::today(), now(), $configReservas))) {
                return ['error' => true, 'message' => $error, 'data' => []];
            }

            $hora = Horas::find($combo['hora']);

            if (! $hora) {
                return [
                    'error' => true,
                    'message' => "La hora {$combo['hora']} no existe.",
                    'data' => [],
                ];
            }
        }

        // Salón con responsable: la reserva queda pendiente y ocupa la franja, pero los
        // portátiles se prestan recién al aprobarla (ver decidirReservas).
        $requiereAutorizacion = (bool) $salon->id_responsable;

        try {
            $reservas = DB::transaction(function () use ($data, $combos, $portatilSolicitado, $requiereAutorizacion) {
                $creadas = [];

                foreach ($combos as $combo) {
                    $reserva = Reservas::create([
                        ...$data,
                        'fecha_reserva' => $combo['fecha'],
                        'hora_reserva' => $combo['hora'],
                        'confirmado' => $requiereAutorizacion ? Reservas::CONFIRMADO_PENDIENTE : Reservas::CONFIRMADO_RESERVADA,
                        'activo' => 1,
                    ]);

                    if (! $requiereAutorizacion) {
                        $this->prestarPortatiles($reserva, (int) $portatilSolicitado);
                    }

                    $creadas[] = $reserva->fresh()->toArray();
                }

                return $creadas;
            });

            $total = count($reservas);
            $mensaje = $total === 1 ? 'Reserva creada correctamente.' : "$total reservas creadas correctamente.";

            return [
                'error' => false,
                'message' => $requiereAutorizacion
                    ? $mensaje.' Este salón requiere autorización: la reserva queda pendiente hasta que el responsable la apruebe.'
                    : $mensaje,
                'data' => $reservas,
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear la reserva');

            return [
                'error' => true,
                'message' => 'Error al asignar portátil(es): '.$e->getMessage(),
                'data' => [],
            ];
        }
    }

    /**
     * Préstamo automático de `$cantidad` portátiles para la fecha de la reserva (marcador
     * "reserva #{id}" en la observación, el mismo que usan finalizar/cancelar para
     * devolverlos). Si no alcanzan, presta los que haya (sin validar, ver crearReserva).
     */
    private function prestarPortatiles(Reservas $reserva, int $cantidad): void
    {
        if ($cantidad <= 0) {
            return;
        }

        // disponiblePrestamo excluye los equipos con un préstamo abierto aunque su
        // `estado` no sea 8: si no, registrarPrestamo lanza "ya se encuentra
        // prestado" y se revierte toda la reserva.
        $portatiles = Inventario::disponiblePrestamo('PORTATIL', null)
            ->whereNotIn('estado', [2, 5, 8])
            ->lockForUpdate()
            ->limit($cantidad)
            ->get();

        $fecha = Carbon::parse($reserva->fecha_reserva)->toDateString();

        foreach ($portatiles as $portatil) {
            $this->prestamosService->registrarPrestamo([
                'id_inventario' => $portatil->id,
                'id_user_prestamo' => $reserva->id_user,
                'fecha_prestamo' => $fecha,
                'id_user_entrega' => $portatil->id_user,
                'fecha_compromiso' => $fecha.' 23:59:59',
                'observacion' => 'Préstamo automático por reserva #'.$reserva->id,
            ]);
        }
    }

    /**
     * Reservas pendientes de autorización de los salones donde `$usuario` es responsable
     * (el Super Admin ve las de todos los salones con responsable).
     */
    public function reservasPendientes(Usuario $usuario): array
    {
        try {
            $reservas = Reservas::with(['salon:id,nombre', 'hora:id,horas', 'usuario:id_user,nombre,apellido'])
                ->where('confirmado', Reservas::CONFIRMADO_PENDIENTE)
                ->where('activo', 1)
                ->whereNull('fecha_cancelado')
                ->whereHas('salon', fn ($q) => (int) $usuario->perfil === 1
                    ? $q->whereNotNull('id_responsable')
                    : $q->where('id_responsable', $usuario->id_user))
                ->orderBy('fecha_reserva')
                ->orderBy('id_salon')
                ->orderBy('hora_reserva')
                ->get();

            return ['error' => false, 'message' => 'Reservas pendientes obtenidas correctamente.', 'data' => $reservas];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al obtener las reservas pendientes');

            return ['error' => true, 'message' => 'Error en el servidor al obtener las reservas pendientes.', 'data' => []];
        }
    }

    /**
     * Solo el responsable del salón (o el Super Admin) decide. Las reservas deben venir con
     * la relación `salon` cargada.
     *
     * @param  iterable<Reservas>  $reservas
     */
    public function puedeDecidir(iterable $reservas, Usuario $usuario): bool
    {
        if ((int) $usuario->perfil === 1) {
            return true;
        }

        foreach ($reservas as $reserva) {
            if ((int) $reserva->salon?->id_responsable !== (int) $usuario->id_user) {
                return false;
            }
        }

        return true;
    }

    /**
     * Aprueba (confirmado=2, presta portátiles) o rechaza (confirmado=4, libera la franja)
     * reservas pendientes, y avisa por correo a cada solicitante. Todo o nada: si alguna
     * no está pendiente o no es de un salón del responsable, no se toca ninguna.
     *
     * @param  array<int, int>  $ids
     */
    public function decidirReservas(array $ids, bool $aprobar, Usuario $usuario, ?string $motivo = null): array
    {
        $reservas = Reservas::with(['salon:id,nombre,id_responsable', 'hora:id,horas', 'usuario:id_user,nombre,apellido,correo'])
            ->whereIn('id', $ids)
            ->where('confirmado', Reservas::CONFIRMADO_PENDIENTE)
            ->where('activo', 1)
            ->whereNull('fecha_cancelado')
            ->get();

        if ($reservas->isEmpty() || $reservas->count() !== count(array_unique($ids))) {
            return ['error' => true, 'message' => 'Alguna de las reservas ya no está pendiente de autorización.', 'data' => []];
        }

        if (! $this->puedeDecidir($reservas, $usuario)) {
            return ['error' => true, 'message' => 'Solo el responsable del salón puede autorizar o rechazar estas reservas.', 'data' => []];
        }

        try {
            DB::transaction(function () use ($reservas, $aprobar, $usuario) {
                foreach ($reservas as $reserva) {
                    if ($aprobar) {
                        $reserva->update(['confirmado' => Reservas::CONFIRMADO_RESERVADA, 'fecha_confirmado' => now()]);
                        $this->prestarPortatiles($reserva, (int) $reserva->portatil);
                    } else {
                        $reserva->update([
                            'confirmado' => Reservas::CONFIRMADO_RECHAZADA,
                            'activo' => 0,
                            'fecha_cancelado' => now(),
                            'id_cancelado' => $usuario->id_user,
                        ]);
                    }
                }
            });
        } catch (Exception $e) {
            $this->sendError($e, 'Error al decidir la autorización de reservas');

            return ['error' => true, 'message' => 'Error en el servidor al guardar la decisión.', 'data' => []];
        }

        // Fuera de la transacción: si el correo falla, la decisión ya quedó guardada.
        $reservas->groupBy('id_user')->each(fn ($grupo) => $this->enviarCorreoDecision($grupo, $aprobar, $motivo));

        $total = $reservas->count();

        return [
            'error' => false,
            'message' => ($aprobar ? 'Reserva aprobada' : 'Reserva rechazada')." ($total franja(s)). Se notificó al solicitante.",
            'data' => [],
        ];
    }

    /**
     * Correo al solicitante con la decisión del responsable (uno por solicitante).
     *
     * @param  Collection<int, Reservas>  $reservas
     */
    private function enviarCorreoDecision($reservas, bool $aprobar, ?string $motivo): void
    {
        try {
            $primera = $reservas->first();
            $correo = $primera->usuario?->correo;
            if (! $correo) {
                return;
            }

            $salon = $primera->salon?->nombre ?? '';
            $lineas = [
                $aprobar
                    ? "Tu reserva del salón {$salon} fue APROBADA por el responsable del salón. Estado: Reservado."
                    : "Tu reserva del salón {$salon} fue RECHAZADA por el responsable del salón.",
                '',
                ...$reservas->map(fn ($r) => '• '.Carbon::parse($r->fecha_reserva)->locale('es')->translatedFormat('l j \d\e F \d\e Y')
                    .' — '.trim(str_replace("\n", ' ', $r->hora?->horas ?? '')))->all(),
            ];

            if (! $aprobar && $motivo) {
                $lineas[] = '';
                $lineas[] = "Motivo: {$motivo}";
            }

            app(MailService::class)->sendGeneric(
                $correo,
                'Reserva '.($aprobar ? 'aprobada' : 'rechazada')." ($salon)",
                implode("\n", $lineas),
                rtrim((string) config('app.frontend_url'), '/').'/reservas/mis-reservas',
                'Ver mis reservas'
            );
        } catch (Exception $e) {
            $this->sendError($e, 'Error al enviar el correo de decisión de la reserva');
        }
    }

    /**
     * Correo al responsable del salón con el enlace a la página de autorizaciones.
     *
     * @param  array<int, string>  $fechas
     * @param  array<int, string>  $horas
     */
    private function enviarCorreoResponsable(Salones $salon, Usuario $solicitante, array $fechas, array $horas): void
    {
        $correo = $salon->responsable?->correo;
        if (! $correo) {
            return;
        }

        app(MailService::class)->sendGeneric(
            $correo,
            "Reserva pendiente de autorización ({$salon->nombre})",
            implode("\n", [
                trim($solicitante->nombre.' '.$solicitante->apellido)." solicitó reservar el salón {$salon->nombre}, que requiere tu autorización.",
                '',
                'Fechas: '.implode(', ', $fechas),
                'Horas: '.implode(', ', array_map(fn ($h) => trim(str_replace("\n", ' ', $h)), $horas)),
                '',
                'Ingresa a OMNIA para aprobarla o rechazarla.',
            ]),
            rtrim((string) config('app.frontend_url'), '/').'/reservas/autorizaciones',
            'Revisar solicitud en OMNIA'
        );
    }

    // Salones con destinatarios propios (ids heredados de la app legada).
    private const SALON_MAKER_LAB = 9;

    private const SALON_BIBLIOTECA = 8;

    private const SALON_CAFETERIA = 15;

    /**
     * Aviso interno de reserva (una sola vez por solicitud, aunque tenga varias franjas).
     * Siempre va a reservas.sistemas; según el salón se suman otros destinatarios (y, para
     * Maker Lab y Biblioteca, el propio usuario). Si el salón tiene responsable, añade la
     * alerta de autorización y le envía aparte el enlace para aprobar/rechazar. Es un extra: si falla solo se registra y no afecta la reserva.
     * `$reservas` es el `data` de crearReserva().
     */
    public function enviarCorreoAvisoReserva(array $reservas, Usuario $usuario): bool
    {
        try {
            $primera = $reservas[0] ?? null;
            if (! $primera) {
                return false;
            }

            $idSalon = (int) $primera['id_salon'];
            $correos = array_merge(Mails::RESERVAS_SISTEMAS->recipients(), match ($idSalon) {
                self::SALON_MAKER_LAB => [...Mails::RESERVAS_MAKER_LAB->recipients(), $usuario->correo],
                self::SALON_BIBLIOTECA => [...Mails::RESERVAS_BIBLIOTECA->recipients(), $usuario->correo],
                self::SALON_CAFETERIA => Mails::RESERVAS_CAFETERIA->recipients(),
                default => [],
            });

            $salon = Salones::with('responsable:id_user,correo')->find($idSalon);
            $nombreSalon = $salon?->nombre ?? '';
            $horas = Horas::whereIn('id', array_column($reservas, 'hora_reserva'))->orderBy('id')->pluck('horas')->all();
            $fechas = array_values(array_unique(array_column($reservas, 'fecha_reserva')));
            $fechasTexto = array_map(fn ($f) => Carbon::parse($f)->locale('es')->translatedFormat('l j \d\e F \d\e Y'), $fechas);
            $requiereAutorizacion = (bool) $salon?->id_responsable;

            if ($requiereAutorizacion) {
                $this->enviarCorreoResponsable($salon, $usuario, $fechasTexto, $horas);
            }

            $resultado = app(MailService::class)->sendView(
                array_values(array_filter($correos)),
                "Reserva de salón ($nombreSalon)",
                'emails.reservaAviso',
                [
                    'usuario' => trim($usuario->nombre.' '.$usuario->apellido),
                    'salon' => $nombreSalon,
                    'portatil' => ($primera['portatil'] ?? 0) > 0 ? 'Sí ('.$primera['portatil'].')' : 'No',
                    'sonido' => ! empty($primera['sonido']) && $primera['sonido'] !== 'no' ? 'Sí' : 'No',
                    'fechas' => $fechasTexto,
                    'horas' => $horas,
                    'detalle' => $primera['detalle_reserva'] ?? null,
                    'requiereAprobacion' => $requiereAutorizacion,
                    'nombreColegio' => app(MarcaDominioService::class)->nombreInstitucion($usuario->correo),
                ]
            );

            return ! $resultado['error'];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al enviar el correo de aviso de la reserva');

            return false;
        }
    }

    /**
     * Envía al usuario que generó la reserva un correo con su información y, abajo, el
     * JPG (armado en el frontend: info de la reserva + QR de la encuesta del salón) para
     * que lo comparta con los asistentes. `$reservas` es el `data` de crearReserva() (una
     * fila por franja, todas del mismo salón). Revalida que el salón tenga una encuesta
     * activa y que la imagen sea realmente un JPEG — no se confía en el frontend.
     */
    public function enviarCorreoEncuesta(array $reservas, string $imagenDataUrl, Usuario $usuario): bool
    {
        try {
            $primera = $reservas[0] ?? null;
            if (! $primera || empty($usuario->correo)) {
                return false;
            }

            $filaSalon = EncuestaSalon::with('encuesta:id,titulo,activo')->where('id_salon', $primera['id_salon'])->first();
            if (! $filaSalon?->encuesta?->activo) {
                return false;
            }

            $jpg = base64_decode(preg_replace('#^data:image/jpe?g;base64,#', '', $imagenDataUrl), true);
            if ($jpg === false || (@getimagesizefromstring($jpg)['mime'] ?? null) !== 'image/jpeg') {
                return false;
            }

            $horas = Horas::whereIn('id', array_column($reservas, 'hora_reserva'))->orderBy('id')->pluck('horas')->all();
            $fechas = array_values(array_unique(array_column($reservas, 'fecha_reserva')));

            return app(MailService::class)->send($usuario->correo, new ReservaEncuestaMail([
                'nombre' => trim($usuario->nombre.' '.$usuario->apellido),
                'salon' => Salones::find($primera['id_salon'])?->nombre ?? '',
                'titulo' => $primera['titulo'] ?? null,
                'detalle' => $primera['detalle_reserva'] ?? null,
                'fechas' => array_map(fn ($f) => Carbon::parse($f)->locale('es')->translatedFormat('l j \d\e F \d\e Y'), $fechas),
                'horas' => $horas,
                'encuesta' => $filaSalon->encuesta->titulo,
            ], $jpg));
        } catch (Exception $e) {
            $this->sendError($e, 'Error al enviar el correo de la encuesta de la reserva');

            return false;
        }
    }

    /**
     * Valida que $fechaReserva caiga dentro de la ventana de anticipación configurada
     * (ConfiguracionReservas::dias_min_anticipacion/dias_max_anticipacion, en días
     * calendario — incluye fines de semana), relativa a $hoy/$ahora. Null si es válida,
     * mensaje de error si no. Aislado de crearReserva() (que además necesita BD para
     * salón/usuario) para poder testear esta regla de negocio sin fixtures.
     */
    private function validarFechaReserva(Carbon $fechaReserva, Carbon $hoy, Carbon $ahora, ConfiguracionReservas $config): ?string
    {
        if ($fechaReserva->lt($hoy)) {
            return 'La fecha de la reserva no puede ser anterior a hoy.';
        }

        if ($fechaReserva->equalTo($hoy)) {
            return 'No se puede reservar para el día de hoy: debes reservar con anticipación.';
        }

        // Domingo es festivo por convención, nunca reservable. Y si hoy es sábado tampoco
        // se puede reservar para el lunes siguiente: el colegio no tiene a nadie
        // disponible ese fin de semana para gestionar/confirmar la reserva.
        if ($fechaReserva->isSunday()) {
            return 'No se puede reservar para un domingo.';
        }

        if ($hoy->isSaturday() && $fechaReserva->isMonday()) {
            return 'Reservando en sábado no se puede reservar para el lunes siguiente.';
        }

        $diasMin = $config->dias_min_anticipacion;
        $diasMax = $config->dias_max_anticipacion;

        // Después de las 12:00 m. de hoy, la ventana completa se corre un día más — mismo
        // cutoff que existía antes de generalizar esto a una ventana mín/máx configurable.
        if ($ahora->gte($hoy->copy()->setTime(12, 0, 0))) {
            $diasMin++;
            $diasMax++;
        }

        $fechaMin = $hoy->copy()->addDays($diasMin);
        $fechaMax = $hoy->copy()->addDays($diasMax);

        if ($fechaReserva->lt($fechaMin)) {
            return "Debes reservar con al menos {$diasMin} día(s) de anticipación — la fecha más próxima disponible es {$fechaMin->toDateString()}.";
        }

        if ($fechaReserva->gt($fechaMax)) {
            return "No se puede reservar con más de {$diasMax} día(s) de anticipación (hasta el {$fechaMax->toDateString()}).";
        }

        return null;
    }

    public function actualizarReserva(array $data): array
    {
        DB::beginTransaction();

        try {
            $reserva = Reservas::find($data['id']);

            if (! $reserva) {
                DB::rollBack();

                return [
                    'error' => true,
                    'message' => "No se encontró la reserva con id: {$data['id']}.",
                    'data' => [],
                ];
            }

            // Finalizar (devuelve portátiles, marca como completada)
            if (! empty($data['finalizar'])) {
                $prestamos = PrestamosInventario::where('observacion', 'like', "%reserva #{$reserva->id}")
                    ->whereNull('fecha_devolucion')
                    ->get();

                foreach ($prestamos as $prestamo) {
                    $prestamo->update(['fecha_devolucion' => now()]);
                    $prestamo->inventario()->update(['estado' => 1]);
                    $this->prestamosService->registrarLog($prestamo->inventario, 1, Auth::id());
                }

                $reserva->update(['confirmado' => 1]);

                DB::commit();

                return [
                    'error' => false,
                    'message' => 'Reserva finalizada correctamente.',
                    'data' => $reserva->fresh()->toArray(),
                ];
            }

            // Cancelación
            if (! empty($data['cancelar'])) {
                $reserva->update([
                    'fecha_cancelado' => now(),
                    'activo' => 0,
                ]);

                // Devolver portátiles prestados por esta reserva
                $prestamos = PrestamosInventario::where('observacion', 'like', "%reserva #{$reserva->id}")
                    ->whereNull('fecha_devolucion')
                    ->get();

                foreach ($prestamos as $prestamo) {
                    $prestamo->update(['fecha_devolucion' => now()]);
                    $prestamo->inventario()->update(['estado' => 1]);
                    $this->prestamosService->registrarLog($prestamo->inventario, 1, Auth::id());
                }

                DB::commit();

                return [
                    'error' => false,
                    'message' => 'Reserva cancelada correctamente.',
                    'data' => $reserva->fresh()->toArray(),
                ];
            }

            // Edición completa (fecha/hora/salón/descripción/portátil/sonido) — gateada en
            // el controller detrás de OPCION_EDITAR_RESERVAS, a diferencia de
            // cancelar/finalizar arriba (autoservicio, sin permiso propio).
            $salon = $reserva->salon;

            if (array_key_exists('hora_reserva', $data)) {
                $hora = Horas::find($data['hora_reserva']);

                if (! $hora) {
                    DB::rollBack();

                    return [
                        'error' => true,
                        'message' => 'La hora seleccionada no existe.',
                        'data' => [],
                    ];
                }
            }

            if (array_key_exists('fecha_reserva', $data) && ! strtotime($data['fecha_reserva'])) {
                DB::rollBack();

                return [
                    'error' => true,
                    'message' => 'La fecha de la reserva no tiene un formato válido (Y-m-d).',
                    'data' => [],
                ];
            }

            if (array_key_exists('id_salon', $data)) {
                $salon = Salones::activo()->find($data['id_salon']);

                if (! $salon) {
                    DB::rollBack();

                    return [
                        'error' => true,
                        'message' => 'El salón no existe o está inactivo.',
                        'data' => [],
                    ];
                }
            }

            // Choque con otra reserva: se revalida siempre que cualquiera de los tres
            // (salón/fecha/hora) cambie, no solo cuando cambia el salón — antes, mover una
            // reserva de fecha/hora dentro del mismo salón se guardaba sin chequear que la
            // nueva franja ya estuviera ocupada por otra reserva.
            if (array_key_exists('id_salon', $data) || array_key_exists('fecha_reserva', $data) || array_key_exists('hora_reserva', $data)) {
                $idSalonEfectivo = $data['id_salon'] ?? $reserva->id_salon;
                $fechaEfectiva = $data['fecha_reserva'] ?? $reserva->fecha_reserva;
                $horaEfectiva = $data['hora_reserva'] ?? $reserva->hora_reserva;

                $ocupado = Reservas::where('id_salon', $idSalonEfectivo)
                    ->where('fecha_reserva', $fechaEfectiva)
                    ->where('hora_reserva', $horaEfectiva)
                    ->where('activo', 1)
                    ->whereNull('fecha_cancelado')
                    ->where('id', '!=', $data['id'])
                    ->exists();

                if ($ocupado) {
                    DB::rollBack();

                    return [
                        'error' => true,
                        'message' => 'El salón ya se encuentra reservado para esa fecha y hora.',
                        'data' => [],
                    ];
                }
            }

            // Reconcilia los préstamos de portátiles (mismo marcador "reserva #{id}" que usa
            // crearReserva) cuando cambia la cantidad solicitada o la reserva se mueve de
            // fecha/hora — en ese caso se libera todo lo prestado para la franja vieja y se
            // vuelve a asignar desde cero en la nueva, en vez de intentar "mover" cada unidad.
            $fechaCambia = array_key_exists('fecha_reserva', $data) && (string) $data['fecha_reserva'] !== (string) $reserva->fecha_reserva;
            $horaCambia = array_key_exists('hora_reserva', $data) && (int) $data['hora_reserva'] !== (int) $reserva->hora_reserva;

            if (array_key_exists('portatil', $data) || $fechaCambia || $horaCambia) {
                $prestamosActivos = PrestamosInventario::where('observacion', 'like', "%reserva #{$reserva->id}")
                    ->whereNull('fecha_devolucion')
                    ->get();

                $fechaEfectiva = $data['fecha_reserva'] ?? $reserva->fecha_reserva;
                $horaEfectiva = $data['hora_reserva'] ?? $reserva->hora_reserva;
                $solicitado = array_key_exists('portatil', $data) ? max(0, (int) $data['portatil']) : $prestamosActivos->count();

                if ($fechaCambia || $horaCambia) {
                    foreach ($prestamosActivos as $prestamo) {
                        $prestamo->update(['fecha_devolucion' => now()]);
                        $prestamo->inventario()->update(['estado' => 1]);
                        $this->prestamosService->registrarLog($prestamo->inventario, 1, Auth::id());
                    }
                    $yaAsignados = 0;
                } else {
                    $yaAsignados = $prestamosActivos->count();
                }

                if ($solicitado < $yaAsignados) {
                    foreach ($prestamosActivos->take($yaAsignados - $solicitado) as $prestamo) {
                        $prestamo->update(['fecha_devolucion' => now()]);
                        $prestamo->inventario()->update(['estado' => 1]);
                        $this->prestamosService->registrarLog($prestamo->inventario, 1, Auth::id());
                    }
                } elseif ($solicitado > $yaAsignados) {
                    // Sin validar disponibilidad (temporal, ver crearReserva): si no
                    // alcanzan, limit() presta los que haya.
                    $faltan = $solicitado - $yaAsignados;

                    // Mismo criterio que crearReserva: sin préstamo abierto.
                    $portatiles = Inventario::disponiblePrestamo('PORTATIL', null)
                        ->whereNotIn('estado', [2, 5, 8])
                        ->lockForUpdate()
                        ->limit($faltan)
                        ->get();

                    foreach ($portatiles as $portatil) {
                        $this->prestamosService->registrarPrestamo([
                            'id_inventario' => $portatil->id,
                            'id_user_prestamo' => $reserva->id_user,
                            'fecha_prestamo' => $fechaEfectiva,
                            'id_user_entrega' => $portatil->id_user,
                            'fecha_compromiso' => $fechaEfectiva.' 23:59:59',
                            'observacion' => 'Préstamo automático por reserva #'.$reserva->id,
                        ]);
                    }
                }
            }

            unset($data['id'], $data['cancelar'], $data['finalizar']);
            $reserva->update($data);

            DB::commit();

            return [
                'error' => false,
                'message' => 'Reserva actualizada correctamente.',
                'data' => $reserva->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            DB::rollBack();

            $this->sendError($e, 'Error al actualizar la reserva');

            return [
                'error' => true,
                'message' => 'Error al actualizar la reserva.',
                'data' => [],
            ];
        }
    }

    public function mostrarReservas(
        ?int $id_user,
        ?int $id_salon,
        ?string $fechaReserva,
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?bool $cancelado,
        ?int $perpage = 10,
        string $orden = 'asc'
    ): array {
        try {

            $reservas = Reservas::with([
                'salon:id,nombre',
                'hora:id,horas',
                'usuario:id_user,nombre,apellido',
            ])
                ->when($id_user, function ($query) use ($id_user) {
                    $query->where('id_user', $id_user);
                })
                ->when($id_salon, function ($query) use ($id_salon) {
                    $query->where('id_salon', $id_salon);
                })
                ->when($fechaReserva, function ($query) use ($fechaReserva) {
                    $query->whereDate('fecha_reserva', $fechaReserva);
                })
                ->when($fechaDesde, function ($query) use ($fechaDesde) {
                    $query->whereDate('fecha_reserva', '>=', $fechaDesde);
                })
                ->when($fechaHasta, function ($query) use ($fechaHasta) {
                    $query->whereDate('fecha_reserva', '<=', $fechaHasta);
                })
                ->when(! is_null($cancelado), function ($query) use ($cancelado) {
                    if ($cancelado) {
                        $query->whereNotNull('fecha_cancelado');
                    } else {
                        $query->whereNull('fecha_cancelado');
                    }
                })
                ->orderBy('fecha_reserva', $orden)
                ->orderBy('id_salon')
                ->orderBy('hora_reserva')
                ->paginate($perpage);

            return [
                'error' => false,
                'message' => 'Se obtuvieron correctamente las reservas.',
                'data' => $reservas,
            ];
        } catch (Exception $e) {

            $this->sendError($e, 'Error al obtener las reservas');

            return [
                'error' => true,
                'message' => 'Error en el servidor al obtener las reservas.',
                'data' => [],
            ];
        }
    }

    /*
    -------------------------------------------------
    |
    |             SALONES
    |
    -------------------------------------------------
    */

    public function crearSalon(array $datos): array
    {
        try {
            $salon = Salones::create([
                ...$datos,
                'id_user' => Auth::id(),
                'activo' => 1,
            ]);

            return [
                'error' => false,
                'message' => 'Salón creado correctamente.',
                'data' => $salon->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear el salón');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear el salón.',
                'data' => [],
            ];
        }
    }

    public function actualizarSalon(array $datos, int $id): array
    {
        try {
            $salon = Salones::activo()->find($id);

            if (! $salon) {
                return [
                    'error' => true,
                    'message' => "No se encontró el salón con id: {$id}.",
                    'data' => [],
                ];
            }

            $salon->update($datos);

            return [
                'error' => false,
                'message' => 'Salón actualizado correctamente.',
                'data' => $salon->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar el salón');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar el salón.',
                'data' => [],
            ];
        }
    }

    /**
     * Baja lógica (activo=0): un hard delete dejaría huérfanas las reservas
     * históricas que apuntan a id_salon (no hay FK en BD para esa columna).
     */
    public function eliminarSalon(int $id): array
    {
        try {
            $salon = Salones::activo()->find($id);

            if (! $salon) {
                return [
                    'error' => true,
                    'message' => "No se encontró el salón con id: {$id}.",
                    'data' => [],
                ];
            }

            $salon->update(['activo' => 0]);

            return [
                'error' => false,
                'message' => 'Salón eliminado correctamente.',
                'data' => [],
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar el salón');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar el salón.',
                'data' => [],
            ];
        }
    }

    /*
    -------------------------------------------------
    |
    |             HORAS
    |
    -------------------------------------------------
    */

    public function crearHora(array $datos): array
    {
        try {
            $hora = Horas::create([
                ...$datos,
                'id_user' => Auth::id(),
                'activo' => 1,
            ]);

            return [
                'error' => false,
                'message' => 'Franja horaria creada correctamente.',
                'data' => $hora->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al crear la franja horaria');

            return [
                'error' => true,
                'message' => 'Error en el servidor al crear la franja horaria.',
                'data' => [],
            ];
        }
    }

    public function actualizarHora(array $datos, int $id): array
    {
        try {
            $hora = Horas::where('activo', 1)->find($id);

            if (! $hora) {
                return [
                    'error' => true,
                    'message' => "No se encontró la franja horaria con id: {$id}.",
                    'data' => [],
                ];
            }

            $hora->update($datos);

            return [
                'error' => false,
                'message' => 'Franja horaria actualizada correctamente.',
                'data' => $hora->fresh()->toArray(),
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al actualizar la franja horaria');

            return [
                'error' => true,
                'message' => 'Error en el servidor al actualizar la franja horaria.',
                'data' => [],
            ];
        }
    }

    /**
     * Baja lógica (activo=0): un hard delete dejaría huérfanas las reservas
     * históricas que apuntan a hora_reserva (no hay FK en BD para esa columna).
     */
    public function eliminarHora(int $id): array
    {
        try {
            $hora = Horas::where('activo', 1)->find($id);

            if (! $hora) {
                return [
                    'error' => true,
                    'message' => "No se encontró la franja horaria con id: {$id}.",
                    'data' => [],
                ];
            }

            $hora->update(['activo' => 0]);

            return [
                'error' => false,
                'message' => 'Franja horaria eliminada correctamente.',
                'data' => [],
            ];
        } catch (Exception $e) {
            $this->sendError($e, 'Error al eliminar la franja horaria');

            return [
                'error' => true,
                'message' => 'Error en el servidor al eliminar la franja horaria.',
                'data' => [],
            ];
        }
    }
}
