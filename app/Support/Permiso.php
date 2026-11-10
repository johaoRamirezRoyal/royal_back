<?php

namespace App\Support;

use App\Enums\TipoPerfil;

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
    public const RESERVAS_GESTIONAR = 41;               // "Reserva de salones" (crear; cancelar ajenas es la 120)
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
    public const RESERVAS_EDITAR = 120;                 // "Reservas — Editar y cancelar reservas" (editar, cancelar, finalizar cualquiera)
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
    public const MIS_AREAS_NIVEL = 141;                 // "Mis áreas — Áreas comunes de mi nivel"
    public const MIS_AREAS_RESPONSABLE = 142;           // "Mis áreas — Áreas comunes de mis áreas a cargo"
    public const CURSOS_ALTURA = 143;                   // "Gestión Humana — Cursos de altura"
    public const CARNETS = 144;                         // "Carnets institucionales" (imprimir, fotos y entregas)
    public const CURSOS = 145;                          // "Gestión Académica — Cursos y estudiantes"
    public const CARNETS_MI_CURSO = 146;                // "Director de grupo — Entregar carnets de su curso"
    public const CARNETS_SOLICITAR = 147;               // "Solicitar carnet" (el propio o, como director, de su curso)
    public const ASISTENCIA_PANTALLA_QR = 148;          // "Asistencia — Pantalla del código QR" (solo la pantalla de portería)
    public const MIS_AREAS_COMUNES_CON_AREAS = 149;     // "Mis áreas comunes — Ver áreas del bloque" (complemento de la 119)
    // 150–154 libres en producción (reservados para renumerar la rama de notas).
    // Fase 3: lo que antes decidía un número de perfil (2026_11_10_110000_seed_opciones_roles).
    public const COORDINACION_NIVEL = 155;              // "Coordinación de nivel — Gestiona solo su nivel" (antes perfil 26; restrictiva)
    public const DIRECCION_ADMINISTRATIVA = 156;        // "Dirección administrativa — Gestiona los niveles administrativos" (antes perfil 7; restrictiva)
    public const COMPRAS_TODOS_LOS_NIVELES = 157;       // "Compras — Ver y aprobar solicitudes de todos los niveles" (antes perfiles 1 y 2)
    public const REVOCAR_LLEGADAS_TARDE_TRABAJADORES = 158; // "Gestión Humana — Revocar llegadas tarde de trabajadores" (antes 1 y 8)
    public const RESPONSABLE_BLOQUES = 159;             // "Áreas comunes — Puede ser responsable de un bloque"
    public const TIENE_ASIGNATURAS = 160;               // "Gestión Académica — Puede tener asignaturas" (docentes y psicólogas)
    // Fase 3, segunda tanda (2026_11_10_120000_seed_opciones_roles_avisos).
    public const EVALUACIONES_ADMIN_TOTAL = 161;        // "Evaluaciones — Administración total" (antes 1, 2 y 8)
    public const EVALUACIONES_REALIZAR_TODAS = 162;     // "Evaluaciones — Realizar cualquier evaluación activa" (antes 1 y 8)
    public const AVISO_LLEGADAS_TARDE_TRABAJADORES = 163; // "Gestión Humana — Recibe avisos de llegadas tarde de trabajadores" (antes 8)
    public const AVISO_LIMITE_LLEGADAS_TARDE = 164;     // "Llegadas tarde — Recibe avisos de estudiantes en el límite" (antes 20 y 11)
    public const ASISTENCIA_NIVEL = 165;                // "Asistencia de nivel — Avisos y responsable sugerido de su nivel" (antes 11)
    public const AVISA_DIRECCION_ADMINISTRATIVA = 166;  // "Permisos y Licencias — Sus solicitudes avisan a la dirección administrativa" (antes 10, 23, 32, 33 y 11)
    public const ADMISIONES_PSICOLOGA_PREESCOLAR = 167; // "Admisiones — Psicóloga de preescolar" (antes 21)
    public const ADMISIONES_PSICOLOGA_PRIMARIA = 168;   // "Admisiones — Psicóloga de primaria" (antes 24)
    public const ADMISIONES_PSICOLOGA_BACHILLERATO = 169; // "Admisiones — Psicóloga de bachillerato" (antes 25)
    public const ADMISIONES_GESTION_PERSONAL = 170;     // "Admisiones — Gestionar inscripciones como personal" (antes nombres "administrador"/"admisiones")

    /**
     * Opciones que QUITAN capacidades en vez de darlas. Super Admin tiene todas las demás
     * (PermisosPerfil) pero no estas: con la 135, Observaciones lo dejaría en solo lectura;
     * con la 155/156 quedaría encerrado en un nivel.
     */
    public const OPCIONES_RESTRICTIVAS = [self::OBSERVACIONES_SOLO_CONSULTA, self::COORDINACION_NIVEL, self::DIRECCION_ADMINISTRATIVA];

    // ─── Perfiles ───────────────────────────────────────────────
    // Los números de perfil cambian de un colegio a otro: para "es docente / estudiante /
    // operativo" usa App\Enums\TipoPerfil (perfiles.tipo); para "puede hacer X", una opción.
    // Super Admin es 1 en todos y es el único número de perfil que el código conoce.
    public const PERFIL_SUPER_ADMIN = 1;

    /**
     * Middleware de ruta: entra quien tenga CUALQUIERA de las opciones, sea de uno de los
     * perfiles (id) o su perfil sea de uno de los tipos (ver App\Http\Middleware\RequirePermiso).
     *
     *   ->middleware(Permiso::ruta([Permiso::INVENTARIO], [Permiso::PERFIL_SUPER_ADMIN]))
     *   ->middleware(Permiso::ruta([Permiso::GESTION_ACADEMICA], [TipoPerfil::DOCENTE]))
     *
     * @param  array<int>  $opciones
     * @param  array<int|TipoPerfil>  $perfiles
     */
    public static function ruta(array $opciones, array $perfiles = []): string
    {
        return 'permiso:' . implode(',', [...$opciones, ...array_map(
            fn (int|TipoPerfil $p) => $p instanceof TipoPerfil ? "tipo:{$p->value}" : "perfil:{$p}",
            $perfiles,
        )]);
    }
}
