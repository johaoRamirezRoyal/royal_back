<?php

namespace App\Services\Cafeteria;

use App\Enums\Cafeteria\EstadoAsignacion;
use App\Enums\Cafeteria\EstadoCafeteria;
use App\Enums\TipoPerfil;
use App\Models\Cafeteria\AsignacionPersonal;
use App\Models\Cafeteria\Cafeteria;
use App\Models\Cafeteria\RolOperativo;
use App\Models\Usuarios\Perfil;
use App\Models\Usuarios\Usuario;
use Throwable;

/**
 * Personal de la cafetería (opción 177): quién trabaja en cada cafetería, con qué función y
 * desde cuándo. La función (rol operativo) no da permisos de la API: eso sigue en
 * cron_opciones/cron_permisos. Una persona puede estar en varias cafeterías y funciones.
 */
class PersonalCafeteriaService
{
    use EjecutaOperacion;

    /** Tipos de perfil que pueden trabajar en una cafetería (personal de la institución). */
    public const TIPOS_PERSONAL = [TipoPerfil::ADMINISTRATIVO, TipoPerfil::DOCENTE, TipoPerfil::OPERATIVO];

    /** @param array{id_cafeteria?: mixed, estado?: mixed, id_user?: mixed} $filtros */
    public function listar(array $filtros): array
    {
        try {
            $estado = EstadoAsignacion::tryFrom((string) ($filtros['estado'] ?? ''));

            $asignaciones = AsignacionPersonal::query()
                ->with(['usuario:id_user,nombre,apellido,documento,correo', 'rol:id,codigo,nombre', 'cafeteria:id,codigo,nombre'])
                ->when(is_numeric($filtros['id_cafeteria'] ?? null), fn ($q) => $q->where('id_cafeteria', (int) $filtros['id_cafeteria']))
                ->when(is_numeric($filtros['id_user'] ?? null), fn ($q) => $q->where('id_user', (int) $filtros['id_user']))
                ->when($estado, fn ($q) => $q->where('estado', $estado))
                ->orderByRaw("estado = 'ACTIVA' DESC")
                ->orderByDesc('fecha_inicio')
                ->get();

            return ['error' => false, 'message' => 'Personal de la cafetería', 'data' => $asignaciones];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** Usuarios activos que se pueden asignar (personal de la institución), para el buscador. */
    public function candidatos(?string $busqueda): array
    {
        try {
            $s = trim((string) $busqueda);

            $usuarios = Usuario::query()
                ->where('estado', 'activo')
                ->whereIn('perfil', Perfil::idsDeTipo(...self::TIPOS_PERSONAL))
                ->when($s !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('nombre', 'like', "%{$s}%")
                    ->orWhere('apellido', 'like', "%{$s}%")
                    ->orWhere('documento', 'like', "%{$s}%")
                    ->orWhere('correo', 'like', "%{$s}%")))
                ->orderBy('nombre')
                ->limit(20)
                ->get(['id_user', 'nombre', 'apellido', 'documento', 'correo']);

            return ['error' => false, 'message' => 'Personal disponible', 'data' => $usuarios];
        } catch (Throwable $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /** @param array{id_cafeteria:int, id_user:int, id_rol_operativo:int, fecha_inicio:string, fecha_fin?:?string} $datos */
    public function asignar(array $datos, int $idAsignador): array
    {
        return $this->operacion(function () use ($datos, $idAsignador) {
            $cafeteria = Cafeteria::find($datos['id_cafeteria']) ?? throw ReglaCafeteria::noExiste('La cafetería no existe');
            if ($cafeteria->estado === EstadoCafeteria::CERRADA) {
                throw new ReglaCafeteria('La cafetería está cerrada: no se le asigna personal');
            }

            $rol = RolOperativo::find($datos['id_rol_operativo']) ?? throw ReglaCafeteria::noExiste('La función no existe');
            if (! $rol->activo) {
                throw new ReglaCafeteria('La función está inactiva');
            }

            $esPersonal = Usuario::where('id_user', $datos['id_user'])
                ->where('estado', 'activo')
                ->whereIn('perfil', Perfil::idsDeTipo(...self::TIPOS_PERSONAL))
                ->exists();
            if (! $esPersonal) {
                throw new ReglaCafeteria('Solo se asigna personal activo de la institución (administrativo, docente u operativo)');
            }

            // Duplicada = otra ACTIVA de la misma persona y función en esta cafetería cuyas fechas
            // se cruzan con las nuevas. Una vencida (fin antes del nuevo inicio) ya no bloquea.
            // lockForUpdate: dos asignaciones iguales enviadas a la vez no pasan las dos.
            $inicio = $datos['fecha_inicio'];
            $fin = $datos['fecha_fin'] ?? null;
            $duplicada = AsignacionPersonal::where('id_cafeteria', $cafeteria->id)
                ->where('id_user', $datos['id_user'])
                ->where('id_rol_operativo', $rol->id)
                ->where('estado', EstadoAsignacion::ACTIVA)
                ->where(fn ($q) => $q->whereNull('fecha_fin')->orWhere('fecha_fin', '>=', $inicio))
                ->when($fin !== null, fn ($q) => $q->where('fecha_inicio', '<=', $fin))
                ->lockForUpdate()
                ->exists();
            if ($duplicada) {
                throw new ReglaCafeteria('Esa persona ya tiene esa función en esta cafetería en esas fechas');
            }

            $asignacion = AsignacionPersonal::create([
                'id_cafeteria' => $cafeteria->id,
                'id_user' => $datos['id_user'],
                'id_rol_operativo' => $rol->id,
                'asignado_por' => $idAsignador,
                'fecha_inicio' => $datos['fecha_inicio'],
                'fecha_fin' => $datos['fecha_fin'] ?? null,
                'estado' => EstadoAsignacion::ACTIVA,
            ]);

            return $asignacion->load(['usuario:id_user,nombre,apellido,documento,correo', 'rol:id,codigo,nombre', 'cafeteria:id,codigo,nombre']);
        }, 'Personal asignado');
    }

    /**
     * Termina la asignación en `fechaFin` (sin fecha: hoy, o su fin si ya venció).
     * - Nunca antes del inicio ni después del fin que ya tenía (para ampliarla, otra asignación).
     * - Con una fecha futura solo programa el fin: sigue ACTIVA y deja de ser vigente ese día.
     * - Con hoy o una fecha pasada queda FINALIZADA.
     */
    public function finalizar(int $id, ?string $fechaFin): array
    {
        return $this->operacion(function () use ($id, $fechaFin) {
            $asignacion = $this->activa($id);
            $hoy = now()->toDateString();
            $finActual = $asignacion->fecha_fin?->toDateString();
            $fin = $fechaFin ?? min($hoy, $finActual ?? $hoy);

            if ($fin < $asignacion->fecha_inicio->toDateString()) {
                throw new ReglaCafeteria('La fecha de fin no puede ser anterior al inicio de la asignación');
            }
            if ($finActual !== null && $fin > $finActual) {
                throw new ReglaCafeteria('La asignación ya termina antes de esa fecha; para ampliarla, crea una nueva');
            }

            $asignacion->update([
                'estado' => $fin > $hoy ? EstadoAsignacion::ACTIVA : EstadoAsignacion::FINALIZADA,
                'fecha_fin' => $fin,
            ]);

            return $asignacion;
        }, 'Asignación finalizada');
    }

    /**
     * Retira la asignación antes de tiempo (queda en el historial como REVOCADA). El fin queda
     * hoy, pero nunca antes del inicio (si aún no empezaba) ni después del fin (si ya venció).
     */
    public function revocar(int $id): array
    {
        return $this->operacion(function () use ($id) {
            $asignacion = $this->activa($id);
            $fin = max(now()->toDateString(), $asignacion->fecha_inicio->toDateString());
            $finActual = $asignacion->fecha_fin?->toDateString();
            if ($finActual !== null && $finActual < $fin) {
                $fin = $finActual;
            }
            $asignacion->update(['estado' => EstadoAsignacion::REVOCADA, 'fecha_fin' => $fin]);

            return $asignacion;
        }, 'Asignación revocada');
    }

    private function activa(int $id): AsignacionPersonal
    {
        $asignacion = AsignacionPersonal::lockForUpdate()->find($id) ?? throw ReglaCafeteria::noExiste('La asignación no existe');
        if ($asignacion->estado !== EstadoAsignacion::ACTIVA) {
            throw new ReglaCafeteria('La asignación ya no está activa');
        }

        return $asignacion;
    }
}
