# Gestión Académica: franjas no asignables, autoservicio de horario del docente y asistencia de trabajadores

Documento único para backend (`royal_back`) y frontend (`frontend-New_OMNIA`) — resume
todo lo agregado/corregido en la sesión del 2026-08-24 (continuación del trabajo de
`GESTION_ACADEMICA_ANIOS_DOCENTE_FRONTEND.md`).

## 1. Asistencia de trabajadores (RRHH)

### 1.1 Hora mínima de salida (por horario + fallback global)

- `asistencia_horarios_estandar.hora_minima_salida` (nueva columna, default `09:00:00`):
  una marcación de salida antes de esta hora, con entrada ya registrada, se descarta en
  vez de cerrar el día por error del dispositivo. Configurable por horario desde
  Configuración de asistencia (`HorarioModal.parts.tsx`).
- Tabla `configuracion_asistencia` (fila única `id=1`, `hora_minima_salida_defecto`):
  fallback cuando ningún horario configurado aplica al grupo/día del usuario
  (`AsistenciaGestionService::horaMinimaSalidaParaUsuario`). UI:
  `ConfiguracionAsistenciaModal.parts.tsx`.

### 1.2 Revocar llegada tarde

- `asistencia_gestion.revocado` (nueva columna, boolean): el registro se conserva pero
  deja de contar en `topUsuariosLlegadasTarde` — mismo patrón que
  `llegadas_tardes.revocado` para estudiantes.
- Acción gateada a perfiles `[1, 8]` (`AsistenciaGestionController::PERFILES_REVOCAR_LLEGADA_TARDE`).
- Frontend: botón de revocar en `WorkerArrivals/index.tsx`, con parámetro `justificada`
  al registrar una llegada tarde manualmente.

## 2. Franjas horarias "no asignables"

Antes, marcar un receso/almuerzo requería crear un `HorarioClase` de tipo RECESO/ALMUERZO
por cada curso/docente. Ahora una franja se puede marcar directamente como no asignable:

- `academico_franja_horaria.asignable` (boolean, default `true`), `.color`, `.etiqueta`
  (ej. "Receso"): al desmarcar el checkbox "No asignable" en la pestaña **Franjas
  horarias**, se limpian estos tres campos (no se borra la fila).
- `academico_franja_horaria.id_franja_pivote` (self-FK, `nullOnDelete`): al usar "Aplicar
  a todos los días" desde una franja marcada no asignable, las franjas replicadas quedan
  enlazadas a la franja pivote — no se puede volver a aplicar una marcación global
  distinta mientras dependan de un pivote (hay que quitarlas del pivote primero, botón
  "Quitar de los otros N días").
- `FranjaHorariaService::existeCruceHorario()`: helper compartido de cruce por intervalo
  exclusivo (`hora_inicio < fin AND hora_fin > inicio`), reemplaza el `BETWEEN` inclusivo
  anterior que marcaba franjas contiguas (ej. 07:30-08:20 seguida de 08:20-09:10) como
  cruzadas por error.
- `HorarioClaseService::franjaDisponibleParaCarga()`: la validación de disponibilidad
  también se blindó contra franjas no asignables (bloquea asignar clase ahí).

### 2.1 Bug de datos: bloques de Receso borrados

Durante la limpieza de datos huérfanos de esta sesión se habían borrado los 5 `HorarioClase`
(tipo RECESO, lunes a viernes, esquema 3) que le daban visibilidad al Receso en los
calendarios. Se recrearon manualmente (ids 778-782) — ver sección 6 para el detalle.

## 3. Autoservicio de horario del docente ("Mi horario")

### 3.1 Reserva multiselección con descripción por franja

`ReservarHorarioSheet.parts.tsx` — al elegir curso+asignatura, el docente ahora:
- Selecciona **varias** franjas a la vez (antes era una y reservaba al toque), con un
  botón "Guardar" al final.
- Puede ponerle una **descripción a cada franja individualmente** (ícono de lápiz que
  aparece al seleccionarla, abre un input inline) — no una descripción única para todo
  el lote.
- Ve un resumen **"Ya tienes reservado"** arriba del todo (lo que ya apartó en cualquier
  curso/asignatura), como lista compacta con botón "Ver N más" cuando hay más de 4.

Backend: `DocenteHorarioService::reservar()` recibe `descripcion` opcional y la guarda en
el `HorarioClase` creado (`academico_horario_clase.descripcion`, columna nueva).

### 3.2 Editar la descripción de un bloque ya reservado

Nuevo endpoint `PUT /gestion-academica/mi-horario` (`actualizarDescripcionMiHorario` →
`DocenteHorarioService::actualizarDescripcion`), scoped al docente autenticado (mismo
chequeo de propiedad que `eliminar()`). En la página "Mi horario", cada bloque reservado
tiene su propio ícono de lápiz para editar/crear la descripción inline, sin re-pegarle al
backend si no hubo cambio real (compara contra el valor original antes de guardar).

### 3.3 "Tu horario ya apartado" como acordeón por día

Antes era una grilla de hasta 3 columnas (toda la semana visible a la vez, mucho scroll en
móvil). Ahora cada día es un `<details>` colapsable (mismo patrón que "Horarios
ocupados"): hoy abierto por defecto, el resto cerrado, con chevron.

### 3.4 Observación de la sesión y descripción de la franja en el calendario

`HorarioCalendar.parts.tsx` (calendario semanal de "Asistencia | Clases") ahora muestra,
además del estado (dictada/cancelada/reprogramada):
- La **descripción de la franja** (`HorarioClase.descripcion`, fija semana a semana).
- La **observación de la sesión del día** (`AsistenciaClase.observacion`, distinta fecha a
  fecha) — `useWeekClassStatus` ahora trae ambos campos, no solo el estado.

Aplica tanto a la celda normal como a los chips de `CeldaMultiple` (clases cruzadas en el
mismo bloque+día, ver 3.5).

### 3.5 Clases cruzadas visibles (bug de datos ocultos)

`cellMap` en `HorarioCalendar` era `Map<string, HorarioClaseRow>` (un solo valor por
celda `bloque-día`) — si dos clases de cursos distintos caían en la misma celda, la
segunda sobreescribía a la primera en silencio. Cambiado a
`Map<string, HorarioClaseRow[]>` + componente `CeldaMultiple`: lista todas en chips
apilados de ancho completo, con "+N más" si hay más de 3.

## 4. Bug de seguridad: cruce de horarios no se validaba entre esquemas

`HorarioClaseService::franjaDisponibleParaCarga()` comparaba disponibilidad por
`id_franja_horaria` **exacto**, no por hora real. Un docente que dicta en dos esquemas
distintos (ej. Primaria y Bachillerato, que numeran sus franjas por separado) podía quedar
agendado dos veces a la misma hora real sin que nada lo bloqueara. **Se encontraron 363
cruces reales ya existentes en producción** al momento del fix (no corregidos
automáticamente — quedan para que el colegio decida cuál de cada clase en conflicto
mantener).

Fix: nuevo helper `HorarioClaseService::existeCruceHorarioClase()` — compara por
`id_dia_semana` + intervalo de horas cruzado (mismo patrón exclusivo que
`FranjaHorariaService::existeCruceHorario()`), no por id de franja. Aplica tanto al flujo
del admin (`HorarioTab`) como al autoservicio del docente, porque ambos pasan por el mismo
método.

## 5. Bug de permisos: Docente con acceso completo a Gestión Académica

El perfil Docente (`id_perfil=3`) tenía otorgada la opción `99` ("Gestión Académica") en
`cron_permisos` (fila id=1527, `activo=1`). Esto no solo mostraba "Configuración
académica" y "Llegadas tarde" en su menú (que `GestionAcademicaController::METODOS_DOCENTE`
deja explícito que NO le corresponden), sino que le daba **acceso completo por API a todo
el controller** — `$tieneAccesoCompleto` se volvía `true` en el constructor, saltándose
por completo la restricción a solo sus métodos de autoservicio.

Fix: se desactivó ese permiso (`activo=0`) y se agregó la migración
`2026_08_24_060000_revoke_opcion_gestion_academica_perfil_docente` para que el mismo fix
se aplique en cualquier otro entorno donde se corran las migraciones. El autoservicio
(Mi horario, Asistencia, Métricas) sigue funcionando porque pasa por
`METODOS_DOCENTE`, no por la opción 99.

## 6. Migraciones agregadas (en orden)

| Migración | Qué hace |
|---|---|
| `2026_08_24_000000_add_hora_minima_salida_to_asistencia_horarios_estandar_table` | Columna `hora_minima_salida` (ver 1.1) |
| `2026_08_24_010000_create_configuracion_asistencia_table` | Tabla fallback global de hora mínima de salida (ver 1.1) |
| `2026_08_24_020000_add_asignable_color_to_academico_franja_horaria_table` | `asignable`/`color`/`etiqueta` (ver 2) |
| `2026_08_24_030000_add_revocado_to_asistencia_gestion_table` | Columna `revocado` (ver 1.2) |
| `2026_08_24_040000_add_id_franja_pivote_to_academico_franja_horaria_table` | Self-FK `id_franja_pivote` (ver 2) |
| `2026_08_24_050000_add_descripcion_to_academico_horario_clase_table` | Columna `descripcion` (ver 3.1) |
| `2026_08_24_060000_revoke_opcion_gestion_academica_perfil_docente` | Revoca opción 99 para Docente (ver 5) |

El SQL puro (MySQL) de estas 7 migraciones queda al final de este documento (sección 8).

Además, se recrearon manualmente (no vía migración, son datos, no esquema) 5 filas en
`academico_horario_clase` — ver sección 2.1: `tipo=RECESO`, `id_carga_academica=NULL`,
para las franjas `197, 209, 221, 233, 348` (lunes a viernes, esquema 3).

## 7. Cómo probar

- Backend: `php artisan migrate`.
- Cuenta de prueba Docente: usuario `docente.prueba` / contraseña `Prueba123!`
  (perfil 3, `id_user=11474`).
- Verificar que el docente de prueba **ya no ve** "Llegadas tarde" ni "Configuración
  académica" en el sidebar, y que sigue pudiendo usar "Mi horario" / "Asistencia | Clases"
  / "Métricas | Asistencias" con normalidad.
- `/gestion-academica/mi-horario`: reservar varias franjas a la vez con descripciones
  distintas, editar la descripción de un bloque ya reservado, revisar el acordeón por día.
- `/gestion-academica/asistencias-clases` (vista "Horario"): confirmar que un bloque con
  dos clases cruzadas muestra ambas en chips, y que la descripción/observación aparecen.

## 8. SQL puro (MySQL) de las migraciones de esta sesión

```sql
-- 2026_08_24_000000_add_hora_minima_salida_to_asistencia_horarios_estandar_table
ALTER TABLE `asistencia_horarios_estandar`
  ADD COLUMN `hora_minima_salida` TIME NOT NULL DEFAULT '09:00:00' AFTER `hora_llegada_esperada`;

-- 2026_08_24_010000_create_configuracion_asistencia_table
CREATE TABLE `configuracion_asistencia` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `hora_minima_salida_defecto` TIME NOT NULL,
  `fechareg` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_updated` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `configuracion_asistencia` (`id`, `hora_minima_salida_defecto`)
VALUES (1, '09:00:00');

-- 2026_08_24_020000_add_asignable_color_to_academico_franja_horaria_table
ALTER TABLE `academico_franja_horaria`
  ADD COLUMN `asignable` TINYINT(1) NOT NULL DEFAULT 1 AFTER `orden`,
  ADD COLUMN `color` VARCHAR(20) NULL AFTER `asignable`,
  ADD COLUMN `etiqueta` VARCHAR(100) NULL AFTER `color`;

-- 2026_08_24_030000_add_revocado_to_asistencia_gestion_table
ALTER TABLE `asistencia_gestion`
  ADD COLUMN `revocado` TINYINT(1) NOT NULL DEFAULT 0 AFTER `observacion`;

-- 2026_08_24_040000_add_id_franja_pivote_to_academico_franja_horaria_table
ALTER TABLE `academico_franja_horaria`
  ADD COLUMN `id_franja_pivote` INT NULL AFTER `etiqueta`;

ALTER TABLE `academico_franja_horaria`
  ADD CONSTRAINT `academico_franja_horaria_id_franja_pivote_foreign`
  FOREIGN KEY (`id_franja_pivote`) REFERENCES `academico_franja_horaria` (`id`)
  ON DELETE SET NULL;

-- 2026_08_24_050000_add_descripcion_to_academico_horario_clase_table
ALTER TABLE `academico_horario_clase`
  ADD COLUMN `descripcion` VARCHAR(255) NULL AFTER `tipo`;

-- 2026_08_24_060000_revoke_opcion_gestion_academica_perfil_docente
UPDATE `cron_permisos`
SET `activo` = 0
WHERE `id_opcion` = 99 AND `id_perfil` = 3;
```
