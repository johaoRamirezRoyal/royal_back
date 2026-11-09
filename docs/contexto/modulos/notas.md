# Notas (`/gestion-academica/notas` — `NotasController`, `NotasService`)

Sistema de notas. Hoy está construida la **parte cualitativa**: desempeños (escala) por
indicadores (logros) de cada **grado**, agrupados en **dimensiones**, con un flujo de aprobación de
los logros antes de calificar. **No depende de las asignaturas.** La parte cuantitativa (nota
numérica) está pendiente. Frontend: `frontend/docs/contexto/modulos/notas.md`.

## Conceptos y cómo se relacionan

```
nivel_academico.metodo_calificacion ─ CUALITATIVA | CUANTITATIVA | AMBAS
nivel_academico.tipo_unidad ─ DIMENSION | ASIGNATURA (AREA aún no) · calificador ─ DIRECTOR_GRUPO | DOCENTE_ASIGNATURA
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
  califica"): `DIMENSION` (cognitiva, comunicativa, corporal… en preescolar) o `ASIGNATURA` (básica
  y media). Se definen **por grado** (`academico_grado_dimension`): `dimensionesDe($idGrado, $tipo)`,
  siempre filtrado por el `tipo_unidad` del nivel del curso (un plan no mezcla tipos). Las
  dimensiones se asignan **desde la unidad** (`PUT /notas/dimensiones/{id}/grados`); las asignaturas,
  con la sincronización.
- **Logros (indicadores)** (`academico_logro`): por **grado + periodo** (no por asignatura), y cada
  uno en una dimensión del grado (`id_dimension`, obligatoria). Todos los cursos del grado (Primero A
  y Primero B) los comparten con su aprobación. Los endpoints reciben un `id_curso` (cualquiera del
  grado) y el backend resuelve su grado. `PUT /notas/logros` reemplaza los logros **de las unidades
  que quien guarda puede editar** (`cambiosDeLogros`): los de otras unidades se conservan. **No se
  quita un logro que ya tiene notas** (rechaza todo el guardado diciendo cuántas tiene; antes la
  cascada las borraba). Si a un grado se le quita una unidad, sus logros quedan "por reubicar" y no
  dejan **enviar ni aprobar** hasta asignarles otra.
- **Escala de desempeños** (`academico_escala_valoracion`: nombre, abreviatura, descripción
  opcional, color, orden, activo). Con niveles en `academico_escala_valoracion_nivel` aplica a esos
  niveles; sin niveles es la escala general. Un curso usa la escala activa de su nivel y, si su
  nivel no tiene, la general (`escalaDelNivel`). No se borran: se desactivan.
  `PUT /notas/escalas/orden` renumera una escala 1, 2, 3… según el orden arrastrado.
- **Quién califica**: depende de `nivel_academico.calificador` (ver "Unidad evaluable y quién
  califica"). Con `DIRECTOR_GRUPO` (por defecto) el **director de grupo** del curso
  (`curso.id_director`; `dirige()`): un curso sin director no lo califica nadie hasta asignarlo
  (Cursos). Con `DOCENTE_ASIGNATURA`, el docente con carga en esa asignatura y en ese curso.
- **Valoración** (`academico_valoracion`, unique estudiante + dimensión + periodo): **desempeño
  final de la dimensión** (lo elige el director; no se deriva de los logros) + observación.
  `academico_valoracion_logro`: desempeño por logro (de esa dimensión). `id_curso` es la foto del
  curso al calificar (la promoción cambia `usuarios.id_curso`). Una dimensión vacía (sin desempeño,
  observación ni logros) = sin nota: se borra la fila.
- Sin FK a `periodo_academico` ni `curso` (MyISAM en algunas BD): valida el service. Por eso un
  periodo con notas no se puede eliminar (`eliminarPeriodoAcademico`).

## Unidad evaluable y quién califica

Royal califica preescolar por **dimensiones** (director de grupo) y básica y media **por
asignatura** (docente de cada asignatura). La API no cambia de forma: la tabla y `id_dimension`
conservan su nombre.

- `academico_dimension.tipo`: `DIMENSION` (sin área ni asignatura), `AREA` (`id_area` →
  `academico_area`) o `ASIGNATURA` (`id_asignatura` → `academico_asignatura`). `id_area` e
  `id_asignatura` son únicos y **sin FK** (`academico_asignatura` es MyISAM). Consistencia: CHECK
  `ck_unidad_tipo` (MariaDB ≥ 10.2 / MySQL ≥ 8.0.16; la local es MariaDB 10.4 y lo aplica) y, siempre,
  `Dimension::errorDeTipo` en `guardarDimension`. Constantes `Dimension::DIMENSION|AREA|ASIGNATURA`.
  **AREA queda en el esquema pero no se implementa ni se puede configurar.**
- `nivel_academico.tipo_unidad` (`DIMENSION` | `ASIGNATURA`) y `nivel_academico.calificador`
  (`DIRECTOR_GRUPO` | `DOCENTE_ASIGNATURA`), por defecto `DIMENSION` / `DIRECTOR_GRUPO`
  (`modoDelNivel`). Combinaciones (`errorDeModo`, test en `CalificadorUnidadTest`): DIMENSION exige
  DIRECTOR_GRUPO (una dimensión no tiene carga); ASIGNATURA admite los dos; AREA "aún no disponible".
  Se cambian en `PUT /metodos-nivel/{id}`. **No se cambia `tipo_unidad`** si el nivel ya tiene logros
  (en los grados de sus cursos) o notas (en sus cursos). Al pasar a ASIGNATURA se sincroniza.
- **Sincronización** (`sincronizarUnidadesAsignatura`, `POST /notas/unidades/sincronizar`, 99):
  idempotente y en transacción. Crea o actualiza una unidad ASIGNATURA por cada fila de
  `academico_asignatura` (nombre recortado a 80 y `activo`; el orden de una existente no se toca, las
  nuevas al final en orden alfabético) y vincula a cada grado (`insertOrIgnore`) las unidades de las
  asignaturas con carga activa en alguno de sus cursos activos. No quita vínculos. No filtra por
  nivel: en grados DIMENSION el vínculo queda pero no se usa (`dimensionesDe` filtra por tipo).
- **Política** `calificadorPermite($calificador, $dirige, $tieneCarga)` (pura): con
  `DOCENTE_ASIGNATURA`, quien tiene carga; con cualquier otro valor (también null), el director.
  "Tiene carga" = `cubre($unidad, asignaturasConCarga($user, $cursos))`: carga activa
  (`academico_carga_academica` + `academico_docente_asignatura`, ambas activas) en la asignatura
  de la unidad (o en una asignatura activa del área). Una DIMENSION nunca tiene carga.
- **Calificar** (`unidadesCalificables($user, $curso)`): las unidades del grado que el usuario
  califica en **ese** curso. La usan `planilla` (`dimensiones[].editable`), `misCursos`
  (`unidades_calificables`), `guardarPlanilla` (rechaza "No te corresponde calificar «…» en este
  curso") y el controller (403 "No te corresponde calificar en este curso" si no califica ninguna).
- **Logros en modo ASIGNATURA**: el plan sigue siendo uno por grado + periodo.
  - `rol()['docente']`: con DOCENTE_ASIGNATURA, carga activa en algún curso del grado; con
    DIRECTOR_GRUPO, director de alguno. `rol()['modo_unidad']`: DIMENSION / ASIGNATURA.
  - `unidadesEditables()`: quien gestiona (150/151 del nivel), todas; en modo DIMENSION, todas para el
    docente del grado; en modo ASIGNATURA con DOCENTE_ASIGNATURA, el docente solo las de sus
    asignaturas con carga en algún curso del grado (con DIRECTOR_GRUPO, el director edita todas).
    Va en `plan.dimensiones[].editable` (verPlan) y en `planilla.plan.unidades_editables`.
  - `cambiosDeLogros` (pura, test `CambiosDeLogrosTest`): en unidades no editables, un logro que viene
    sin cambios se ignora (el frontend manda la lista completa) y crear, cambiar o mover se rechaza;
    solo se eliminan los de unidades editables que no vienen. Los "por reubicar" los mueve quien
    gestiona (o, en DIMENSION, el docente).
  - `acciones()`: con `modo_unidad = ASIGNATURA`, **solo quien gestiona envía** a revisión (así un
    docente no cierra el plan antes de que los demás carguen los suyos); aprobar, devolver y reabrir
    siguen con la 151. Sin la clave o con DIMENSION, como antes.

## Plan de logros (estado y seguimiento)

Los logros del grado los definen en conjunto los docentes del grado (directores de grupo, o en modo
ASIGNATURA los docentes con carga, cada uno en sus asignaturas) y el coordinador / asistente de nivel.

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
  - Docente del grado (`rol['docente']`: director de grupo de alguno de sus cursos, o con
    DOCENTE_ASIGNATURA, carga en alguno): edita en `BORRADOR` / `DEVUELTO` (en ASIGNATURA, solo sus
    unidades) y envía (en ASIGNATURA, no). Calificar exige que le toque **ese** curso
    (`unidadesCalificables`).
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
| `GET /dimensiones?tipo=` (por defecto DIMENSION), `POST /dimensiones`, `PUT /dimensiones/{id}`, `PUT /dimensiones/orden` (ruta antes de `{id}`), `PUT /dimensiones/{id}/grados` | 99 |
| `POST /unidades/sincronizar` | 99; devuelve `{ creadas, actualizadas, vinculos_nuevos }` |
| `GET /metodos-nivel` (con `tipo_unidad` y `calificador`), `PUT /metodos-nivel/{id}` `{ metodo?, tipo_unidad?, calificador? }` (al menos uno) | 99 |
| `GET /mis-cursos`, `PUT /planilla` | perfiles docente (3, 14); `PUT /planilla`: 403 si no califica ninguna unidad del curso, y el service valida cada unidad y que el plan esté aprobado |
| `GET /planilla`, `GET /plan` (`?id_curso&id_periodo`) | docente del grado, 150/151 de su nivel, 99 o 149 (solo lectura) |
| `PUT /logros`, `POST /plan/estado` | docente del grado o 150/151 de su nivel; el service valida el estado y las unidades |
| `GET /logros/grados?id_periodo=` | 150 / 151 (grados del nivel con cursos, directores, cantidad de logros, estado y `alcance`) |
| `GET /consulta/filtros`, `GET /consulta` | 99 o 149 (`Permiso::NOTAS_CONSULTAR`); matriz estudiantes × dimensiones |

Cuerpo de `PUT /planilla`: `{ id_curso, id_periodo, estudiantes: [{ id_estudiante, dimensiones: [{
id_dimension, id_escala?, observacion?, logros?: [{ id_logro, id_escala }] }] }] }`. Cada logro debe
ser de la dimensión en que viene; las dimensiones que no vienen no se tocan; una unidad que no le toca
a quien guarda rechaza todo (el frontend debe mandar solo las `editable`).

Campos nuevos en las respuestas (en modo DIMENSION, todo lo demás es idéntico a antes; comparado):

- `GET /mis-cursos` → `cursos[]`: `{ id_curso, curso, grado, calificador, unidades_calificables: [ids] }`.
- `GET /planilla` → `tipo_unidad`, `calificador`, `dimensiones[]` con `tipo`, `id_area`,
  `id_asignatura` y `editable` (= puede calificarla en este curso, en plazo y con el plan aprobado),
  `editable` global (con DIRECTOR_GRUPO como siempre; con DOCENTE_ASIGNATURA, alguna unidad
  editable) y `plan.unidades_editables` (ids cuyos logros puede editar).
- `GET /plan` → `dimensiones[]` con `tipo`, `id_area`, `id_asignatura` y `editable` (= puede editar
  sus logros, aparte del estado; ese lo dice `puede.editar`). `puede.enviar` es false para el docente
  en modo ASIGNATURA.

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
según `calificador`, combinaciones de `tipo_unidad`/`calificador` y la consistencia de tipo de una
unidad), `CambiosDeLogrosTest.php` (logros con varios docentes en el mismo plan) y
`FechasPeriodoAcademicoTest.php`. Lo que toca la base (sincronización idempotente, filtro por tipo,
quitar un logro con notas, cambio de `tipo_unidad` bloqueado, planilla y regresión de DIMENSION) no
tiene tests automáticos: `phpunit.xml` usa SQLite en memoria y el esquema legacy es de MySQL. Se
probó a mano en `sami_royal` dentro de una transacción deshecha (2026-11-09).

## Trampas

- `periodo_academico` y (en algunas BD) `curso` son MyISAM: un `rollBack()` no deshace cambios en
  ellas, ni siquiera en una prueba. Si una prueba cambia fechas de un periodo, restáuralas a mano.
- `DB_DATABASE=otra php artisan …` en Git Bash **no** cambia de base (sigue usando la del `.env`):
  para probar migraciones en una base temporal, cambia `config('database.connections.mysql.database')`
  y haz `DB::purge`/`reconnect` dentro de tinker.
- Los logros de un plan aprobado no se tocan sin reabrir: el editor lo bloquea y el backend también.

## Pendiente

- **Frontend del modo ASIGNATURA** (el backend ya está):
  - Configuración académica: elegir `tipo_unidad` y `calificador` por nivel (hoy la pestaña
    Método de calificación solo cambia el método) y un botón "Sincronizar asignaturas".
  - Logros: el plan por asignatura (las unidades son asignaturas), editando solo las
    `dimensiones[].editable` y sin "Enviar" para el docente (`puede.enviar`). `LogrosSheet` debe
    dejar de decir que quitar un logro borra sus desempeños: ahora se rechaza si tiene notas.
  - Planilla: selector con los cursos de `mis-cursos` (también los de carga), columnas por unidad
    según `dimensiones[].editable` y guardar **solo** las unidades editables (hoy manda todas y en
    ASIGNATURA el backend lo rechazaría).
- AREA como unidad (sincronizar desde `academico_area`).
- Parte cuantitativa (nota numérica, rangos por desempeño para `AMBAS`), boletín PDF y vista del
  acudiente / estudiante.
