# Notas (`/gestion-academica/notas` — `NotasController`, `NotasService`)

Sistema de notas. Hoy está construida la **parte cualitativa**: desempeños (escala) por
indicadores (logros) de cada **grado**, agrupados en **dimensiones**, con un flujo de aprobación de
los logros antes de calificar. **No depende de las asignaturas.** La parte cuantitativa (nota
numérica) está pendiente. Frontend: `frontend/docs/contexto/modulos/notas.md`.

## Conceptos y cómo se relacionan

```
nivel_academico.metodo_calificacion ─ CUALITATIVA | CUANTITATIVA | AMBAS
nivel_academico.tipo_unidad ─ DIMENSION | AREA | ASIGNATURA · calificador ─ DIRECTOR_GRUPO | DOCENTE_ASIGNATURA
academico_grado ──< academico_grado_dimension >── academico_dimension
grado + periodo
  └─ academico_logro_plan (estado) ──< academico_logro (indicador, en una dimensión del grado)
curso (lo califica su director de grupo, curso.id_director):
  academico_valoracion (estudiante + dimensión + periodo: desempeño final + observación)
    └─ academico_valoracion_logro (desempeño por logro de esa dimensión)
desempeños: academico_escala_valoracion ──< academico_escala_valoracion_nivel
```

- **Método por nivel académico** (`nivel_academico.metodo_calificacion`): `CUALITATIVA`
  (desempeños por indicadores), `CUANTITATIVA` (nota numérica; aún no construida) o `AMBAS`. Todo lo
  cualitativo aplica solo a cursos **con grado** de niveles `CUALITATIVA`/`AMBAS`
  (`NivelAcademico::califCualitativa`): `esCursoCualitativo` / `cursosCualitativos` filtran las
  listas y `sinCualitativa` rechaza los endpoints (un curso sin grado, como "Stem A" o "Egresado":
  "Asígnaselo en Cursos"). Todos los niveles arrancaron en `CUALITATIVA`.
- **Unidades evaluables / dimensiones** (`academico_dimension`; solo cualitativa): catálogo, no se
  borran (se desactivan). Cada fila es una **unidad evaluable** (ver "Unidad evaluable y quién
  califica"): hoy todas son `DIMENSION` (cognitiva, comunicativa, corporal…). Se definen **por
  grado** (`academico_grado_dimension`): `dimensionesDe($idGrado)`. Se asignan **desde la unidad**
  (`PUT /notas/dimensiones/{id}/grados`), para aplicar una común a varios grados de una vez.
- **Logros (indicadores)** (`academico_logro`): por **grado + periodo** (no por asignatura), y cada
  uno en una dimensión del grado (`id_dimension`, obligatoria). Todos los cursos del grado (Primero A
  y Primero B) los comparten con su aprobación. Los endpoints reciben un `id_curso` (cualquiera del
  grado) y el backend resuelve su grado. `PUT /notas/logros` reemplaza la lista del grado: los que no
  vienen se borran **con sus valoraciones** (cascada). Si a un grado se le quita una dimensión, sus
  logros quedan "por reubicar" y no dejan **enviar ni aprobar** hasta asignarles otra.
- **Escala de desempeños** (`academico_escala_valoracion`: nombre, abreviatura, descripción
  opcional, color, orden, activo). Con niveles en `academico_escala_valoracion_nivel` aplica a esos
  niveles; sin niveles es la escala general. Un curso usa la escala activa de su nivel y, si su
  nivel no tiene, la general (`escalaDelNivel`). No se borran: se desactivan.
  `PUT /notas/escalas/orden` renumera una escala 1, 2, 3… según el orden arrastrado.
- **Quién califica**: depende de `nivel_academico.calificador` (ver "Unidad evaluable y quién
  califica"). Por defecto (`DIRECTOR_GRUPO`) el **director de grupo** del curso (`curso.id_director`;
  `dirige()`). Un curso sin director no lo califica nadie hasta asignarlo (Cursos).
- **Valoración** (`academico_valoracion`, unique estudiante + dimensión + periodo): **desempeño
  final de la dimensión** (lo elige el director; no se deriva de los logros) + observación.
  `academico_valoracion_logro`: desempeño por logro (de esa dimensión). `id_curso` es la foto del
  curso al calificar (la promoción cambia `usuarios.id_curso`). Una dimensión vacía (sin desempeño,
  observación ni logros) = sin nota: se borra la fila.
- Sin FK a `periodo_academico` ni `curso` (MyISAM en algunas BD): valida el service. Por eso un
  periodo con notas no se puede eliminar (`eliminarPeriodoAcademico`).

## Unidad evaluable y quién califica

Base para llevar lo cualitativo a básica y media sin cambiar la API (la tabla y `id_dimension`
conservan su nombre):

- `academico_dimension.tipo`: `DIMENSION` (preescolar, sin área ni asignatura), `AREA` (`id_area` →
  `academico_area`) o `ASIGNATURA` (`id_asignatura` → `academico_asignatura`). `id_area` e
  `id_asignatura` son únicos (una unidad por área o asignatura) y **sin FK** (`academico_asignatura` es
  MyISAM). La consistencia la exige el CHECK `ck_unidad_tipo` (MariaDB ≥ 10.2 / MySQL ≥ 8.0.16; la
  local es MariaDB 10.4 y lo aplica) y, siempre, `Dimension::errorDeTipo` en `guardarDimension`.
  Constantes en `Dimension::DIMENSION|AREA|ASIGNATURA`.
- `nivel_academico.tipo_unidad` (`DIMENSION` | `AREA` | `ASIGNATURA`, por defecto `DIMENSION`): qué
  unidad usa el nivel. **Hoy solo se guarda**; nada lo lee todavía.
- `nivel_academico.calificador` (`DIRECTOR_GRUPO` | `DOCENTE_ASIGNATURA`, por defecto
  `DIRECTOR_GRUPO`): quién califica en el nivel. Política pura
  `NotasService::calificadorPermite($calificador, $dirige, $tieneCarga)`: con `DOCENTE_ASIGNATURA`,
  quien tenga carga en la unidad; con cualquier otro valor (también null), el director de grupo.
  Test: `CalificadorUnidadTest`.
- "Tiene carga" (`tieneCarga`): carga académica activa en el curso (`academico_carga_academica` +
  `academico_docente_asignatura`, ambas activas) en la asignatura de la unidad (`ASIGNATURA`) o en
  cualquier asignatura activa del área (`AREA`). Una `DIMENSION` nunca tiene carga.
- `guardarPlanilla` aplica la política **por unidad**: si una de las unidades enviadas no le toca a
  quien guarda, rechaza todo con "No te corresponde calificar «…» en este curso".

**Ojo, todavía no funciona `DOCENTE_ASIGNATURA` de punta a punta**: `NotasController::guardarPlanilla`
sigue exigiendo `dirige()` antes de llamar al service (403 "Solo el director de grupo del curso
califica"), y la planilla (`planilla.editable`, `es_director`), `misCursos` y el rol del plan de
logros siguen siendo del director. Pendiente para esa parte:

- Sincronizar unidades desde `academico_area` / `academico_asignatura` (crear la unidad AREA o
  ASIGNATURA de cada una) y usar `tipo_unidad` para elegir qué unidades ofrece cada nivel.
- Abrir el controller y la planilla al docente con carga (lista de cursos, `editable` por unidad),
  y una planilla por unidad.
- Plan de logros por docente de asignatura (hoy: directores del grado + 150/151).
- Editar `tipo_unidad` y `calificador` desde Configuración académica (hoy solo con SQL).

## Plan de logros (estado y seguimiento)

Los logros del grado los definen en conjunto los directores de grupo del grado y el coordinador /
asistente de nivel.

- `academico_logro_plan` (unique grado + periodo): `BORRADOR` → `EN_REVISION` → `APROBADO`, o
  `DEVUELTO` (con comentario; vuelve a editarse). Se crea al primer cambio; sin fila = `BORRADOR`.
  `academico_logro_plan_historial`: cada logro creado / editado / quitado y cada cambio de estado,
  con quién, cuándo y el detalle (texto del logro y su dimensión, o comentario).
- **Solo se califica con el plan `APROBADO`** (`guardarPlanilla` lo exige; `planilla.editable` lo
  refleja). Para cambiar un plan aprobado, quien aprueba lo **reabre** (vuelve a `BORRADOR`; las
  notas ya puestas se conservan, pero no se califica hasta aprobar de nuevo).
- Enviar exige al menos un logro; enviar y aprobar exigen que todos estén en dimensiones del grado;
  devolver exige comentario.
- Quién puede qué (`NotasService::rol` / `acciones`; el `actor` lo arma `NotasController::actor`):
  - Director de grupo de **alguno** de los cursos del grado (`rol['docente']`): edita y envía en
    `BORRADOR` / `DEVUELTO`. Calificar, en cambio, exige dirigir **ese** curso
    (`planilla.es_director` / `editable` y `guardarPlanilla`).
  - Opción **150** "Gestionar logros": lo mismo, en los grados de su nivel.
  - Opción **151** "Aprobar logros": además aprueba / devuelve (en revisión) y reabre (aprobado); en
    revisión es el único que edita.
  - Nivel = `usuarios.id_nivel` / `id_nivel_2` → `nivel_academico` con
    `DocenteHorarioService::idsNivelAcademicoParaNivel` (pública y estática). Con la **99** (y Super
    Admin) vale para todos los niveles. Usuario con 150/151 sin nivel = sin grados (fail-closed).
  - Las acciones disponibles van en `plan.puede`; el frontend no las recalcula.

## Plazos

Años escolares (calendario A/B, año vigente, crons) y periodos académicos (validación de fechas,
cruces): ver `gestion-academica.md`. Lo propio de notas:

- `periodo_academico.fecha_limite_notas` (nullable, ≥ `fecha_fin`, se edita con el periodo). Se
  califica y corrige desde `fecha_inicio` hasta `fecha_limite_notas ?? fecha_fin`
  (`NotasService::periodoEditable`, test en `tests/Unit/GestionAcademica/PeriodoEditableNotasTest.php`).
- Los logros se trabajan hasta esa misma fecha límite, también antes de que empiece el periodo.
- Los periodos se listan por año vigente **sin filtrar `activo`**: el cron
  `periodos:desactivar-vencidos` los apaga al pasar `fecha_fin` y pueden seguir en plazo de
  corrección.

## Endpoints y permisos (`routes/api/gestionAcademica.php`, prefijo `/notas`)

| Endpoint | Quién |
|---|---|
| `GET/POST /escalas`, `PUT /escalas/{id}`, `PUT /escalas/orden` | 99 |
| `GET/POST /dimensiones`, `PUT /dimensiones/{id}`, `PUT /dimensiones/orden` (ruta antes de `{id}`), `PUT /dimensiones/{id}/grados` | 99 |
| `GET /metodos-nivel`, `PUT /metodos-nivel/{id}` | 99 |
| `GET /mis-cursos`, `PUT /planilla` | perfiles docente (3, 14); `PUT /planilla` exige ser director de grupo del curso (`dirige()`) y plan aprobado |
| `GET /planilla`, `GET /plan` (`?id_curso&id_periodo`) | director de grupo del grado, 150/151 de su nivel, 99 o 149 (solo lectura) |
| `PUT /logros`, `POST /plan/estado` | director de grupo del grado o 150/151 de su nivel; el service valida el estado |
| `GET /logros/grados?id_periodo=` | 150 / 151 (grados del nivel con cursos, directores, cantidad de logros, estado y `alcance`) |
| `GET /consulta/filtros`, `GET /consulta` | 99 o 149 (`Permiso::NOTAS_CONSULTAR`); matriz estudiantes × dimensiones |

Cuerpo de `PUT /planilla`: `{ id_curso, id_periodo, estudiantes: [{ id_estudiante, dimensiones: [{
id_dimension, id_escala?, observacion?, logros?: [{ id_logro, id_escala }] }] }] }`. Cada logro debe
ser de la dimensión en que viene; las dimensiones que no vienen no se tocan.

Opciones (módulo 14, no otorgadas a ningún perfil; se asignan en `/permisos`):
**149** "Gestión Académica — Consultar notas", **150** "Gestionar logros", **151** "Aprobar logros".

## Migraciones (en `migraciones_sql.sql`)

1. `2026_11_05_110000_create_academico_grado_table`: catálogo de grados y `curso.id_grado` (ver
   gestion-academica.md). Corre **antes** que las tablas de notas: logros, plan y dimensiones del
   grado tienen FK a `academico_grado`.
2. `2026_11_05_120000_create_academico_notas_cualitativas_tables`: todo el esquema (método por
   nivel, fecha límite de notas, escala y sus niveles, dimensiones y sus grados, logros, plan de
   logros e historial, valoraciones).
3. `2026_11_05_120100_seed_opciones_notas`: opciones 149, 150 y 151.

Se fusionaron antes del primer commit (en desarrollo hubo un `tipo` de asignatura revertido, y
logros y dimensiones por asignatura que pasaron a ser por grado). La cadena se verificó desde cero en
una base temporal (sube y baja, con todas las FKs). En `sami_royal` local la migración de grados
estaba registrada como `2026_11_06_120000` y se renombró en la tabla `migrations`; las tablas de
logros / plan / valoraciones se rehicieron (se perdieron 4 logros de prueba). Estado al 2026-11-06:
**`sami_hebreo` sin correr** (tiene además pendientes las de Cursos y Carnets desde el 2026-11-03).
Al desplegar, migrar en cada base de colegio.

## Tests

`tests/Unit/GestionAcademica/PeriodoEditableNotasTest.php` (ventana para calificar) y
`AccionesPlanLogrosTest.php` (quién puede editar, enviar, aprobar, devolver y reabrir los logros
según estado, rol y plazo: `NotasService::acciones`), `CalificadorUnidadTest.php` (quién califica
según `calificador` y la consistencia de tipo de una unidad) y `FechasPeriodoAcademicoTest.php`.

## Trampas

- `periodo_academico` y (en algunas BD) `curso` son MyISAM: un `rollBack()` no deshace cambios en
  ellas, ni siquiera en una prueba. Si una prueba cambia fechas de un periodo, restáuralas a mano.
- `DB_DATABASE=otra php artisan …` en Git Bash **no** cambia de base (sigue usando la del `.env`):
  para probar migraciones en una base temporal, cambia `config('database.connections.mysql.database')`
  y haz `DB::purge`/`reconnect` dentro de tinker.
- Los logros de un plan aprobado no se tocan sin reabrir: el editor lo bloquea y el backend también.

## Pendiente

Parte cuantitativa (nota numérica, rangos por desempeño para `AMBAS`), boletín PDF, vista del
acudiente / estudiante, y lo de "Unidad evaluable y quién califica" (sincronizar unidades desde
áreas / asignaturas y abrir la calificación al docente de asignatura).
