<?php

namespace App\Services\GestionAcademica;

/**
 * Qué puede hacer el usuario actual con las observaciones de estudiantes. Lo arma
 * ObservacionEstudianteController a partir de sus permisos; nunca viene del cliente.
 */
final class AccesoObservaciones
{
    /**
     * @param  bool  $verTodas  Opción 134: ve las observaciones de TODOS los profesores. Sin ella,
     *                          cada quien ve solo las que registró.
     * @param  bool  $puedeRegistrar  Docente, opción 133 u opción 99 — siempre false con la 135
     *                                 ("solo consulta"), que además implica $verTodas.
     * @param  array<int>|null  $idsCursosRegistro  Cursos donde puede registrar: null = todos
     *                                               (133/99), array = los suyos (docente).
     * @param  bool  $tieneElegirDocente  Opción 139 "Elegir docente". Sin ella, la observación
     *                                     queda a nombre de quien la registra.
     */
    public function __construct(
        public readonly int $idUsuario,
        public readonly bool $verTodas,
        public readonly bool $puedeRegistrar,
        public readonly ?array $idsCursosRegistro,
        public readonly bool $tieneElegirDocente = false,
    ) {
    }

    /** Estudiantes que puede buscar/abrir: todos si ve todo; si no, aquellos a quienes puede registrar. */
    public function idsCursosEstudiantes(): ?array
    {
        return $this->verTodas ? null : ($this->puedeRegistrar ? $this->idsCursosRegistro : []);
    }

    /** null = sin filtro por autor; si no, solo las propias (como docente o como quien la registró). */
    public function soloDocente(): ?int
    {
        return $this->verTodas ? null : $this->idUsuario;
    }

    /**
     * Opción 139 (y poder registrar): al registrar elige el docente a quien corresponde la
     * observación. Sin ella queda a nombre de quien la registra, aunque tenga la 133/99.
     */
    public function eligeDocente(): bool
    {
        return $this->puedeRegistrar && $this->tieneElegirDocente;
    }
}
