<?php

namespace App\Enums;

use App\Models\CorreoInstitucional;

enum Mails
{
    case ADMISIONES;
    case BIBLIOTECA;
    case GESTION_HUMANA;
    case DIRECCION_ADMINISTRATIVA;

    // Listas de distribución de Noticias (un envío por grupo reparte el correo del lado
    // del proveedor — ver NoticiasService::resolverDestinatariosDistribucion). Distintas
    // de las de arriba: cada BD/institución las carga con sus propias direcciones reales
    // en vez de traerlas hardcodeadas en el código.
    case NOTICIAS_TODOS;
    case NOTICIAS_PREESCOLAR;
    case NOTICIAS_PRIMARIA;
    case NOTICIAS_SECUNDARIA;
    case NOTICIAS_ADMINISTRATIVO;

    // Destinatarios internos fijos de cada módulo (antes hardcodeados en su servicio).
    case COPIA_GESTOR;          // BCC de MailService::sendGeneric()/send(copiaGestor: true)
    case VICERRECTORIA;         // LlegadasTarde: escalación al alcanzar el límite
    case RESERVAS_SISTEMAS;     // ReservasServices: aviso de toda reserva
    case RESERVAS_MAKER_LAB;
    case RESERVAS_BIBLIOTECA;
    case RESERVAS_CAFETERIA;
    case CRONOGRAMA_SISTEMAS;   // Inventario y Préstamos
    case ADMISIONES_ESTADO;     // AdmisionesServices: cambio de estado de inscripción
    case RRHH_ASISTENCIA;       // AsistenciaGestionService: salida cerrada automáticamente

    /** Nombre legible del grupo para la pantalla "Correos institucionales" (Administración del sistema). */
    public function label(): string
    {
        return match ($this) {
            self::ADMISIONES => 'Admisiones',
            self::BIBLIOTECA => 'Biblioteca',
            self::GESTION_HUMANA => 'Gestión humana',
            self::DIRECCION_ADMINISTRATIVA => 'Dirección administrativa (áreas comunes)',
            self::NOTICIAS_TODOS => 'Noticias — Todos (trabajadores)',
            self::NOTICIAS_PREESCOLAR => 'Noticias — Preescolar',
            self::NOTICIAS_PRIMARIA => 'Noticias — Primaria',
            self::NOTICIAS_SECUNDARIA => 'Noticias — Secundaria / Bachillerato',
            self::NOTICIAS_ADMINISTRATIVO => 'Noticias — Administrativo',
            self::COPIA_GESTOR => 'Copia oculta de notificaciones institucionales',
            self::VICERRECTORIA => 'Vicerrectoría (límite de llegadas tarde)',
            self::RESERVAS_SISTEMAS => 'Reservas — Sistemas (todas las reservas)',
            self::RESERVAS_MAKER_LAB => 'Reservas — Maker Lab',
            self::RESERVAS_BIBLIOTECA => 'Reservas — Biblioteca',
            self::RESERVAS_CAFETERIA => 'Reservas — Cafetería',
            self::CRONOGRAMA_SISTEMAS => 'Inventario y préstamos (sistemas)',
            self::ADMISIONES_ESTADO => 'Admisiones — Cambio de estado de inscripción',
            self::RRHH_ASISTENCIA => 'RRHH — Salida marcada automáticamente',
        };
    }

    /**
     * Correos activos del grupo (tabla `correos_institucionales`, columna `grupo` = nombre
     * del case). Puede haber cero, uno o varios por grupo.
     */
    public function recipients(): array
    {
        return CorreoInstitucional::where('grupo', $this->name)
            ->where('activo', true)
            ->pluck('correo')
            ->all();
    }
}
