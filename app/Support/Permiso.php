<?php

namespace App\Support;

/**
 * Única fuente de los ids de permisos: opciones (`cron_opciones.id`) y perfiles (`id_perfil`).
 * La usan las rutas (middleware `permiso`, vía ruta()) y los controllers (sus constantes
 * OPCION_* apuntan aquí), para que un id exista en un solo lugar.
 *
 * Los ids son los de producción (confirmados contra `cron_opciones`); entre comillas, el
 * nombre de la opción en la BD. Si un id cambia (ya pasó: Noticias, Instituciones,
 * Acudientes), se cambia aquí y nada más. Una opción nueva se agrega aquí antes de usarla.
 */
final class Permiso
{
    // ─── Opciones ───────────────────────────────────────────────
    public const USUARIOS = 2;                          // "Usuarios"
    public const INVENTARIO = 12;                       // "Listado" (Inventario)
    public const MIS_INVENTARIOS = 16;                  // "Listado Inventario"
    public const PRESTAMOS_INVENTARIO = 17;             // "Prestamos"
    public const BIBLIOTECA = 20;                       // "Biblioteca"
    public const CERTIFICADOS_LISTADO = 25;             // "Certificados solicitados"
    public const PERMISOS_SISTEMA = 28;                 // "Permisos" (/permisos: cron_opciones/cron_permisos)
    public const SALONES = 40;                          // "Salones"
    public const RESERVAS_GESTIONAR = 41;               // "Reserva de salones"
    public const LISTADO_MAESTRO = 46;                  // "Registro - Listado maestro de documentos"
    public const ENFERMERIA_CATEGORIAS = 54;            // "Enfermeria"
    public const ENFERMERIA_ATENCIONES = 57;            // "Atencion Medica"
    public const COMPRAS_LISTADO_SOLICITUDES = 60;      // "Listado de solicitudes"
    public const PROVEEDORES = 61;                      // "Proveedores"
    public const ASISTENCIA_TRABAJADORES = 63;          // "Asistencia" (control de acceso Hikvision, ver todas)
    public const ENFERMERIA_METRICAS = 64;              // "Estadisticas (Enferm)"
    public const NOTICIAS = 69;                         // "NEWS Royal"
    public const TRAMITES_LISTADO = 77;                 // "Listado de tramites"
    public const PERMISOS_LICENCIAS = 80;               // "Permisos/Licencia"
    public const PERMISOS_LICENCIAS_LISTADO = 81;       // "Listado de permisos"
    public const PERMISOS_LICENCIAS_EDITAR_PROPIA = 82; // "Mis solicitudes (permisos)"
    public const PERMISOS_LICENCIAS_APROBAR = 83;       // "Permisos - Aprobar/Rechazar"
    public const CAPACITACIONES = 85;                   // "Capacitacion"
    public const CAPACITACIONES_CONFIGURAR = 86;        // "Configurar Capacitacion"
    public const PERMISOS_LICENCIAS_DETALLE = 90;       // "Ver Detalle Permiso"
    public const PERMISOS_LICENCIAS_OPERATIVOS = 92;    // "Permisos Operativos"
    public const CAPACITACIONES_REALIZADAS = 93;        // "Listado de certificados Capacitaciones"
    public const INDICADORES_SISTEMAS = 94;             // "Indicadores de gestion Sistemas"
    public const INDICADORES_AIRES = 95;                // "Indicadores de gestion Aires"
    public const GESTION_ACADEMICA = 99;                // "Gestión Académica"
    public const CONFIGURACION_ASISTENCIA = 100;        // "Configuración de asistencia"
    // 101 "Llegadas Tarde Academicas" no tiene constante: el backend no la consulta (sin la 99
    // el acceso a Llegadas tarde ya es el restringido); solo la usa el frontend para gatear.
    public const METRICAS_ASISTENCIA_ACADEMICA = 102;   // "Asistencia Docentes"
    public const COMPRAS_GESTION = 104;                 // "Compras — Gestión de compras"
    public const COMPRAS_VENTAS = 105;                  // "Compras — Ventas"
    public const INSTITUCIONES_GESTION = 106;           // "Gestión de Instituciones"
    public const ANIO_ESCOLAR_PERIODOS = 107;           // "Año Escolar y Periodos"
    public const AREAS_COMUNES_ADMIN = 108;             // "Administrador areas comunes"
    public const AREAS_COMUNES_USO = 109;               // "Uso areas comunes"
    public const INSTITUCIONES_LECTURA = 111;           // "Ver Instituciones y Documentos"
    public const ACUDIENTES_GESTION = 112;              // "Gestión de Acudientes"
    public const COMPRAS_GENERAR_SOLICITUDES = 114;     // "Compras — Generar solicitudes"
    public const MIS_AREAS_COMUNES = 119;               // "Mis areas comunes"
    public const RESERVAS_EDITAR = 120;                 // "Editar Reservas"
    public const PERMISOS_LICENCIAS_CONFIGURACION = 121; // "Permisos y Licencias — Configuración de catálogos"
    public const EVALUACIONES_ADMIN = 122;              // "Evaluaciones — Configuración y administración"
    public const EVALUACIONES_RESULTADOS = 123;         // "Evaluaciones — Ver resultados"
    public const EVALUACIONES_REALIZAR = 124;           // "Evaluaciones — Realizar evaluaciones"
    public const NOTICIAS_CORREOS_DISTRIBUCION = 128;   // "Noticias — Correos de distribución"
    public const ENCUESTAS_ADMIN = 129;                 // "Encuestas — Administrar encuestas y salones"
    public const ENCUESTAS_RESULTADOS = 130;            // "Encuestas — Ver resultados"
    public const VINCULACION_ACUDIENTES = 131;          // "Vinculación Estudiante - Padre"
    public const CONSULTA_ASISTENCIAS_CLASES = 132;     // "Consulta de asistencias de clases"
    public const OBSERVACIONES = 133;                   // "Observaciones de estudiantes"
    public const OBSERVACIONES_VER_TODAS = 134;         // "Ver todas las observaciones"
    public const OBSERVACIONES_SOLO_CONSULTA = 135;     // "Observaciones — solo consulta"
    public const ACTAS_VER = 136;                       // "Ver actas de estudiantes"
    public const ACTAS_GESTIONAR = 137;                 // "Gestionar actas de estudiantes"
    public const ACTAS_MIS_ESTUDIANTES = 138;           // "Ver actas de mis estudiantes"
    public const OBSERVACIONES_ELEGIR_DOCENTE = 139;    // "Observaciones — Elegir docente"
    public const INVENTARIO_FECHA_REPORTE = 140;        // "Fecha de Reporte" (fecha de un reporte de daño)

    /**
     * Opciones que QUITAN capacidades en vez de darlas. Super Admin tiene todas las demás
     * (PermisosPerfil) pero no estas: con la 135, Observaciones lo dejaría en solo lectura.
     */
    public const OPCIONES_RESTRICTIVAS = [self::OBSERVACIONES_SOLO_CONSULTA];

    // ─── Perfiles ───────────────────────────────────────────────
    public const PERFIL_SUPER_ADMIN = 1;
    public const PERFIL_ADMINISTRADOR = 2;
    public const PERFIL_DOCENTE = 3;
    public const PERFIL_DOCENTE_ICT = 14;
    public const PERFIL_COORDINADOR = 26;

    /** Docente y Docente ICT: mismo autoservicio. */
    public const PERFILES_DOCENTE = [self::PERFIL_DOCENTE, self::PERFIL_DOCENTE_ICT];

    /**
     * Middleware de ruta: entra quien tenga CUALQUIERA de las opciones o sea de uno de los
     * perfiles (ver App\Http\Middleware\RequirePermiso).
     *
     *   ->middleware(Permiso::ruta([Permiso::INVENTARIO], [Permiso::PERFIL_SUPER_ADMIN]))
     *
     * @param  array<int>  $opciones
     * @param  array<int>  $perfiles
     */
    public static function ruta(array $opciones, array $perfiles = []): string
    {
        return 'permiso:' . implode(',', [...$opciones, ...array_map(fn (int $p) => "perfil:{$p}", $perfiles)]);
    }
}
