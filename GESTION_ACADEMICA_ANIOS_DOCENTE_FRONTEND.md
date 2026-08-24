# Gestión Académica: años escolares, calendario A/B y autoservicio del docente

Documento único para backend (`royal_back`) y frontend (`frontend-New_S.A.M.I`) — resume
todo lo agregado/corregido en la sesión del 2026-08-21/22 sobre el módulo de Gestión
Académica: gestión de años escolares, calendario institucional configurable, apertura/
cierre automático del año vigente, y el acceso de autoservicio del perfil Docente.

## 1. Años escolares (`anio_escolar`)

Tabla legacy sin migración propia (`id`, `anio_inicio`, `anio_fin`, `activo`,
`fechareg`) — nunca se borra una fila, solo se desactiva.

### 1.1 Calendario A/B

Nueva tabla `configuracion_academica` (single-row, `id=1`, columna `tipo_calendario`
enum `A`/`B` — migración `2026_08_21_140000_create_configuracion_academica_table`),
reemplaza el cutoff fijo de "Calendario B" que antes estaba hardcodeado:

| Calendario | Rango | `anio_fin` |
|---|---|---|
| **B** (default, preserva el comportamiento previo) | 1 ago → 30 jun del año siguiente | `anio_inicio + 1` |
| **A** | 1 feb → 30 nov del mismo año | `anio_inicio` (mismo valor) |

Toda la lógica vive en `AnioEscolarServices` (`app/Services/AnioEscolar/AnioEscolarServices.php`):
- `rangoParaAnioInicio(int $anioInicio, string $tipo): array` — rango de fechas válido
  dado un año de inicio y el calendario.
- `anioInicioParaFecha(Carbon $fecha, string $tipo): ?int` — a qué `anio_inicio`
  pertenece una fecha. Calendario A tiene un **hueco intencional en diciembre/enero**
  (fuera de cualquier año escolar) — devuelve `null` en ese caso.
- `rangoDeAnioEscolar(Anio $anio): array` — expuesto para validar fechas contra un año ya
  existente (lo usa `PeriodoAcademicoRequest` al crear/editar un período académico).

### 1.2 Cuál es "el año vigente"

`AnioEscolarServices::obtenerUltimoAnioEscolar()` (detrás de
`GET /api/compartido/anio-academico/ultimo`, sin gate de opción, compartido con
Admisiones) **prioriza la fila marcada `activo=1`** sobre cualquier cálculo por fecha —
es la fuente de verdad real, la que mantiene al día el cron (1.3) y la que puede
overridear un admin a mano. Solo si no hay ninguna fila activa (antes de la primera
corrida del cron, o si se deshabilitaron todas) recalcula por calendario+fecha, con
fallback al año más reciente que exista.

También lo usa `AdmissionsController` (3 sitios) para resolver el año escolar de nuevas
inscripciones.

**Frontend**: `fetchAnioAcademicoActual()` en
`src/pages/Admissions/shared/services/aniosAcademicos.service.ts` es el único punto de
entrada correcto para "cuál es el año escolar actual" en un hook nuevo — **no** derivarlo
de `getPeriodosActivos()[0]?.id_anio_escolar` (períodos académicos son un concepto
distinto y puede haber cero o varios activos a la vez, sin relación con qué año está
habilitado). Corregido en `useFranjasHorariasTab.hook.ts`, `useHorarioTab.hook.ts` y
`useMiHorario.hook.ts`, que usaban ese patrón indirecto.

### 1.3 Apertura/cierre automático

Comando programado `anio-escolar:cerrar-abrir`
(`app/Console/Commands/CerrarAbrirAnioEscolarCommand.php`,
`Schedule::command(...)->daily()` en `routes/console.php`) →
`AnioEscolarServices::cerrarYAbrirAnioEscolar()`:

1. Calcula qué `anio_inicio` corresponde a hoy según el calendario configurado.
2. **Cierra** (`activo=false`) cualquier fila activa cuyo `anio_inicio` ya no coincida.
3. **Abre**: si no existe ya una fila con el `anio_inicio` esperado, la crea y activa. Si
   ya existe pero fue desactivada a mano, **no la reactiva** — respeta el override manual.
4. Idempotente: si todo ya está correcto, no hace nada.

Como cualquier `Schedule::`, no dispara nada solo — necesita `php artisan schedule:run`
corriendo cada minuto vía el timer de systemd (mismo mecanismo que los demás jobs
programados de este repo).

### 1.4 UI (frontend) — pestaña "Años escolares"

`src/pages/academic/AcademicConfig/parts/AniosEscolaresTab.parts.tsx` /
`hook/useAniosEscolaresTab.hook.ts`:
- Tabla del historial completo (listado sigue siendo `GET /compartido/anio-academico/todos`,
  sin cambios, compartido con Admisiones).
- Botón "Nuevo año escolar" → `POST /gestion-academica/anios-escolares` (creación manual,
  respaldo si el cron no corrió, o para precrear años futuros; solo pide "año de inicio",
  el `anio_fin` lo calcula el backend según el calendario).
- Habilitar/deshabilitar por fila → `PUT /gestion-academica/anios-escolares/estado`.
- Botón "Configurar calendario" (`ConfiguracionCalendarioModal.parts.tsx`) →
  `GET|PUT /gestion-academica/configuracion-calendario`.

## 2. Autoservicio del Docente en Gestión Académica

El perfil Docente (`id_perfil = 3`) **no** tiene la opción `99` ("Gestión Académica
completa") otorgada por defecto en `cron_permisos`. En vez de eso, tiene acceso a una
whitelist explícita de acciones self-scoped, sin pasar por `/permisos`.

### 2.1 Backend — `GestionAcademicaController::METODOS_DOCENTE`

```php
private const PERFIL_DOCENTE = 3;
private const METODOS_DOCENTE = [
    'verAsistenciasClase', 'crearAsistenciaClase', 'actualizarAsistenciaClase',
    'verAsistenciasEstudiantes', 'crearAsistenciaEstudiantes', 'eliminarAsistenciaEstudiante',
    'verMiMenuHorario', 'verMiHorario', 'reservarMiHorario', 'eliminarMiHorario',
    'verMetricasAsistencia', 'obtenerMisCursos', 'verFranjasHorarias',
];
```

En el constructor: si el usuario no tiene la opción 99 pero es Docente y la acción de la
ruta está en esta lista, pasa. **El resto del controller** (asignaturas, áreas, carga
académica, esquemas, franjas fuera de listar, años escolares, calendario) **sigue
exigiendo la opción 99**, sin excepción de perfil.

`verFranjasHorarias` es compartido con el admin (arma la grilla en Configuración
académica) pero es imprescindible también para el docente: sin él, el sheet de "apartar
horario" en Mi horario no puede listar las franjas disponibles — quedó fuera al construir
esto por primera vez y rompió ese flujo (403 silencioso) hasta que se agregó.

`verMetricasAsistencia` se deja pasar siempre para el docente, pero
`AsistenciaEstudianteService::metricasPorCurso(..., ?int $id_docente_scope)` restringe
ahí mismo los resultados a solo los cursos donde tiene carga académica activa
(`academico_carga_academica` × `academico_docente_asignatura`), ignorando cualquier
`id_curso` ajeno que llegue por query param. Nuevo endpoint de apoyo
`GET /gestion-academica/mis-cursos` (`CargaAcademicaService::obtenerCursosDocente`) da la
lista de sus propios cursos para el filtro del dashboard, en vez de listar todos los
cursos del colegio.

### 2.2 Frontend — router y sidebar

`src/router/index.tsx`: el guard que antes agrupaba `asistencias-clases` +
`configuracion` + `mi-horario` bajo un único `PermissionGate opcion={99}` se separó en
dos:

```tsx
// Asistencia de clases + Mi horario: Docente entra por perfil, sin necesitar la opción 99
<PermissionGate mode="route" opcion={99} allowedRoles={[3]} />

// Configuración académica: SOLO opción 99, sin excepción de perfil — a propósito
<PermissionGate mode="route" opcion={99} />
```

Métricas de asistencia académica: `opciones={[99, 102]} allowedRoles={[3]}` (102 sigue
siendo Vicerrector/Directivo Docente, solo lectura de TODOS los cursos; Docente ve la
misma ruta pero con datos ya restringidos por el backend).

`src/components/layout/sideBar/index.layout.tsx`: mismo criterio en los `subItems` —
"Asistencia | Clases", "Métricas | Asistencias" y "Mi horario" tienen
`allowedRoles: [3]` además de su `optionPermission`; "Llegadas tarde" y "Configuración
académica" **no** lo tienen, a propósito.

### 2.3 Títulos en singular para vistas restringidas

Cuando la vista que ve el usuario está restringida a sus propios datos (Docente en
Métricas académicas; cualquier perfil sin la opción `38` en Métricas de RRHH,
`/gestion-humana/metricas-asistencias`), el título/breadcrumb usa singular
("Métricas | Asistencia") en vez de plural ("Métricas | Asistencias") — patrón a
replicar en pantallas nuevas con la misma dualidad completo/restringido.

## 3. Endpoints nuevos/cambiados

| Método y ruta | Gate | Uso |
|---|---|---|
| `GET\|PUT /gestion-academica/configuracion-calendario` | opción 99 | Leer/cambiar Calendario A o B |
| `POST /gestion-academica/anios-escolares` | opción 99 | Crear año escolar manual |
| `PUT /gestion-academica/anios-escolares/estado` | opción 99 | Habilitar/deshabilitar un año |
| `GET /gestion-academica/mis-cursos` | opción 99 **o** Docente | Cursos propios del docente (filtro de Métricas) |
| `GET /gestion-academica/franjas-horarias` | opción 99 **o** Docente | (ya existía) ahora también en la whitelist del docente |
| `GET /gestion-academica/asistencias-metricas` | opción 99, 102, **o** Docente | Ahora acepta Docente; resultados auto-restringidos a sus cursos si no tiene opción 99 |

## 4. Migraciones/tablas agregadas

- `configuracion_academica` (nueva, ver 1.1).
- `academico_asistencia_clase` y `academico_asistencia_estudiante` — ya se usaban en
  `AsistenciaClaseService`/`AsistenciaEstudianteService` **sin tener migración real en
  este repo** (mismo patrón legacy que `anio_escolar`/`cron_opciones`). Se les agregó
  migración con sus FKs y unique constraints (`(id_horario_clase, fecha)` /
  `(id_asistencia_clase, id_alumno)`). Si un endpoint de Gestión Académica falla con
  "Base table ... doesn't exist", revisar primero si es este mismo patrón.
- Fix de índice único: `academico_franja_horaria.uq_franja_horaria` seguía en
  `(id_anio_escolar, id_dia_semana, orden)` desde antes de que existieran los esquemas de
  horario (franjas por nivel) — impedía que dos niveles del mismo año tuvieran franjas en
  el mismo día+orden, aunque `FranjaHorariaService::añadirFranjaHoraria` ya valida
  duplicados por `id_esquema`, no por año. Corregido con un nuevo índice
  `uq_franja_horaria_esquema` en `(id_esquema, id_dia_semana, orden)`.

## 5. Datos de prueba (seeders)

`DatabaseSeeder` corre, en orden: `AsignaturaSeeder` → `AreaAcademicaSeeder` (backfill de
`id_area`) → `PeriodoAcademicoSeeder` (vía el Service real, respeta el calendario
configurado) → `EsquemaHorarioSeeder` (uno por nivel usado) → `FranjaHorarioSeeder` →
`DocenteAsignaturaSeeder` → `CargaAcademicaSeeder` → `HorarioSeeder` →
`AsistenciaClaseSeeder`.

Los últimos cuatro ya existían con datos reales (docentes, materias, 5 días de horario)
pero estaban rotos: hardcodeaban `id_anio_escolar=1` (un año inactivo) y buscaban al
docente por `CONCAT(nombre,' ',apellido) = 'Nombre Completo'` exacto, que no matchea nada
contra los datos reales de `usuarios` (nombres completos metidos en un solo campo,
`apellido` a veces literalmente `"."`). Arreglado:
- `FranjaHorarioSeeder` resuelve el año activo dinámicamente y crea franjas por esquema
  (nivel), no directo por año.
- El matching de docente pasó a un resolver difuso compartido
  (`database/seeders/Concerns/ResolvesDocentePorNombre.php`) que compara por palabras sin
  importar orden ni palabras de más — reutilizar en cualquier seeder nuevo que necesite
  resolver un `Usuario` por nombre "limpio" contra datos reales sucios.

## 6. Otros fixes de frontend (mismo módulo, sin contraparte de backend)

- **Asistencia de clases** (`src/pages/academic/Attendances/index.tsx`) llamaba al
  endpoint admin de horario (`GET /gestion-academica/horario?id_docente=`, no incluido en
  `METODOS_DOCENTE`) → 403 silencioso para cualquier docente sin la opción 99, con la
  pantalla mostrando el horario vacío sin ningún error visible. Cambiado a `getMiHorario()`
  (self-scoped, `GET /gestion-academica/mi-horario`), y se agregó un toast de error real
  en vez de tragar la excepción en silencio.
- **`DataTable` en modo local** (`Body.parts.tsx` y `MobileList.parts.tsx`) ya tenía el
  overlay+spinner y el ícono de recarga girando/deshabilitado implementados, pero
  dependían de un prop `loading` en `DataTable.Root` que varias páginas nunca pasaban
  (`AniosEscolaresTab`, `PeriodosAcademicosTab`). Corregido ahí, y además se agregó el
  mismo overlay a `MobileList.parts.tsx` (no lo tenía en absoluto — gap general del
  componente compartido, no solo de esas dos páginas).
- Confirmación de eliminar bloque de horario (`AcademicConfig` → Horario) cambiada de
  `window.confirm` a un `StepModal`, igual que el resto de confirmaciones destructivas de
  la app.
- Franjas horarias: colores alternados por bloque dentro de cada día para mejor lectura.

## 7. Cómo probar

- Backend: `php artisan migrate`, luego `php artisan db:seed`. Comando manual del cron:
  `php artisan anio-escolar:cerrar-abrir`.
- Cuenta de prueba Docente (perfil 3, con carga académica real vía los seeders):
  usuario `ddonado` / contraseña `Docente2026!`.
- Frontend: `/gestion-academica/configuracion` (pestaña "Años escolares"),
  `/gestion-academica/mi-horario`, `/gestion-academica/asistencias-clases`,
  `/gestion-academica/metricas-asistencia` — probar tanto con un perfil con opción 99
  como logueado como el docente de prueba, para confirmar el split de acceso.
