# Notas (`/gestion-academica/notas` — `NotasController`, `NotasService`)

Sistema de notas. Se está construyendo la **parte cualitativa**: desempeños (escala) por
**referentes** de cada **grado**, agrupados en **propósitos** y definidos por **asignatura**, con un
flujo de aprobación antes de calificar y un **boletín trimestral** con historial. La parte
cuantitativa (nota numérica) está pendiente. Frontend: `frontend/docs/contexto/modulos/notas.md`.

> **Estado (2026-11-13, rama `feat/notas-cualitativas`).** Backend de las fases 1 y 2 hecho:
> modelo, propósitos y referentes con su plan; planilla del docente, ajuste del coordinador, vista
> del curso, observaciones del director y cierre del trimestre con la foto del boletín. El frontend
> de la rama aún usa la API anterior hasta la fase 3; el PDF del boletín es la fase 4.

## Diseño acordado (2026-11-13)

- **Propósito** (antes "dimensión"): catálogo único del colegio, el mismo para todos los grados y
  docentes.
- **Referente** (antes "indicador" o "logro"): de un grado, un periodo, un propósito y una
  asignatura. Cambian por periodo y por asignatura.
- **Subreferente**: hijo de un referente (un nivel). Se califica igual que un referente pero **no
  sale en el boletín**.
- **Calificación**: el docente de la asignatura pone a cada estudiante un desempeño por
  subreferente y un **desempeño final por referente** (lo elige él, no se calcula). El
  **coordinador** (154 de su nivel) puede cambiar ese final; queda quién, cuándo y el valor del
  docente. No hay desempeño final por propósito.
- **Director de grupo**: ve en solo lectura todo lo calificado en su curso y escribe las
  **"Observaciones Generales del Director de Grupo"** (una por estudiante y trimestre).
- **Boletín trimestral**: lo **cierra el coordinador** por curso y periodo. El cierre guarda una
  foto de los datos por estudiante (no cambia aunque luego se corrija una nota) y el PDF se genera
  desde esa foto (`src/documentos` del frontend, pdfmake). El ajuste del coordinador vale hasta el
  cierre. Los trimestres son los periodos académicos (`periodo_academico`).
- Aplica a **todos los niveles cualitativos** (también preescolar). Ya no hay modos por nivel
  (`tipo_unidad`, `calificador`), unidades de tipo asignatura ni sincronización.

## Conceptos y cómo se relacionan

```
nivel_academico.metodo_calificacion ─ CUALITATIVA | CUANTITATIVA | AMBAS
academico_proposito (catálogo único)
grado + periodo
  └─ academico_referente_plan (estado) + _historial
  └─ academico_referente (propósito + asignatura; id_padre → subreferente)
       └─ academico_valoracion_referente (estudiante: desempeño del docente + ajuste del coordinador)
desempeños: academico_escala_valoracion ──< academico_escala_valoracion_nivel
```

- **Método por nivel académico** (`nivel_academico.metodo_calificacion`): `CUALITATIVA`,
  `CUANTITATIVA` (aún no construida) o `AMBAS`. Lo cualitativo aplica solo a cursos **con grado** de
  niveles `CUALITATIVA`/`AMBAS` (`NivelAcademico::califCualitativa`): `sinCualitativa` rechaza los
  endpoints de un curso sin grado ("Asígnaselo en Cursos").
- **Propósitos** (`academico_proposito`): nombre (120), descripción, orden (arrastrable), activo.
  No se borran: se desactivan. Uno desactivado sigue mostrándose en el plan si ya tiene referentes,
  pero no deja enviar ni aprobar.
- **Referentes** (`academico_referente`): por **grado + periodo** (Primero A y B comparten los de
  Primero), con `id_proposito` y `id_asignatura` obligatorios (`id_asignatura` sin FK:
  `academico_asignatura` es MyISAM). Un **subreferente** (`id_padre`) hereda grado, periodo,
  propósito y asignatura del padre (el service los copia); borrar el padre borra los suyos (FK en
  cascada). Los endpoints reciben un `id_curso` (cualquiera del grado) y el backend resuelve su grado.
- **Asignaturas del grado** (`asignaturasDelGrado`): las que tienen carga activa
  (`academico_carga_academica` + `academico_docente_asignatura`, ambas activas) en algún curso activo
  del grado. Un referente de una asignatura que perdió su carga queda **por reubicar**: se lista, lo
  puede mover quien gestiona, y no deja enviar ni aprobar.
- **Escala de desempeños** (`academico_escala_valoracion`): nombre, abreviatura, descripción, color,
  orden, activo. Con niveles en `academico_escala_valoracion_nivel` aplica a esos niveles; sin
  niveles es la escala general. No se borran: se desactivan. `PUT /notas/escalas/orden` renumera.
- **Valoración** (`academico_valoracion_referente`, unique estudiante + referente): `id_escala_docente`
  y `id_docente`; en un referente (no subreferente), `id_escala_ajuste`, `id_ajustado_por` y
  `ajustado_at`. Final = ajuste ?? docente. `id_periodo_academico` repite el del referente;
  `id_curso` es la foto del curso al calificar (la promoción cambia `usuarios.id_curso`). Sin cascada
  desde el referente: **no se quita un referente con notas** (`guardarReferentes` rechaza todo el
  guardado diciendo cuántas tiene).
- Sin FK a `periodo_academico` ni `curso` (MyISAM): valida el service. Un periodo con referentes no
  se puede eliminar (`PeriodoAcademicoServices::eliminarPeriodoAcademico`).

## Plan de referentes (estado y seguimiento)

- `academico_referente_plan` (unique grado + periodo): `BORRADOR` → `EN_REVISION` → `APROBADO`, o
  `DEVUELTO` (con comentario; vuelve a editarse). Se crea al primer cambio; sin fila = `BORRADOR`.
  `academico_referente_plan_historial`: cada referente creado / editado / quitado (`↳` marca un
  subreferente) y cada cambio de estado, con quién, cuándo y el detalle.
- **Solo se califica con el plan `APROBADO`.** Para cambiar un plan aprobado, quien aprueba lo
  **reabre** (vuelve a `BORRADOR`; las notas se conservan, pero no se califica hasta aprobar).
- Enviar exige al menos un referente; enviar y aprobar exigen que ninguno esté por reubicar;
  devolver exige comentario.
- Quién puede qué (`NotasService::rol` / `acciones`, test `AccionesPlanReferentesTest`; el `actor`
  lo arma `NotasController::actor`):
  - **Docente** (`rol['docente']`: carga activa en algún curso del grado): edita en `BORRADOR` /
    `DEVUELTO` **solo los referentes de sus asignaturas** (`rol['asignaturas']`). No envía.
  - Opción **153** "Gestionar referentes": edita todas las asignaturas y envía, en los grados de su
    nivel.
  - Opción **154** "Aprobar referentes": además aprueba / devuelve (en revisión) y reabre (aprobado);
    en revisión es el único que edita.
  - Nivel = `usuarios.id_nivel` / `id_nivel_2` → `nivel_academico` con
    `DocenteHorarioService::idsNivelAcademicoParaNivel`. Con la **99** (y Super Admin) vale para
    todos los niveles. Usuario con 153/154 sin nivel = sin grados (fail-closed).
  - Las acciones van en `puede`; el frontend no las recalcula.
- **Varios docentes en el mismo plan** (`aplanar` + `cambiosDeReferentes`, puras, test
  `CambiosDeReferentesTest`): `PUT /referentes` recibe la lista completa. Un referente de una
  asignatura no editable que viene sin cambios (propósito, asignatura, padre y texto) se ignora; si
  viene cambiado, movido o nuevo, se rechaza todo con "No puedes editar los referentes de «…»". Se
  eliminan solo los de asignaturas editables que no vienen.

## Calificación, vista del curso y cierre

- **Planilla del docente** (`planilla` / `guardarPlanilla`): por curso **y asignatura**, solo con
  carga activa en esa asignatura en ese curso. Trae los referentes de esa asignatura con sus
  subreferentes y, por estudiante, `notas[id_referente] = { docente, ajuste, final }` (el docente ve
  si el coordinador ajustó). Se guarda en plazo, con el plan `APROBADO` y sin cierre. Cada nota es
  `{ id_referente, id_escala }`: `null` quita la del docente (si hay ajuste, la fila se conserva con
  el ajuste); lo que no viene no se toca. El final de un referente lo elige el docente: no se calcula
  de los subreferentes.
- **Estudiantes** (`estudiantesDe`): activos que hoy están en el curso, más los que ya tienen nota u
  observación en él en ese periodo aunque se hayan movido (`en_curso: false`).
- **Ajuste del coordinador** (`ajustar`, 154 del nivel del curso): cambia el final de un
  **referente** (no de un subreferente); guarda quién y cuándo, y el valor del docente queda en
  `id_escala_docente`. `id_escala: null` quita el ajuste. Con el plan aprobado y hasta el cierre, **sin
  mirar el plazo** (el coordinador corrige después de la fecha límite).
- **Vista del curso** (`vistaCurso`): para el director de grupo del curso, 153/154 de su nivel, 99 y
  152. Todos los referentes con subreferentes, `notas` con `ajustado_por` / `ajustado_at`, la
  `observacion` de cada estudiante, `cierre` (`{ fecha, usuario }` o null) y `puede`: `observar`
  (director, en plazo, sin cierre), `ajustar` y `cerrar` (154 del nivel, plan aprobado, sin cierre),
  `reabrir_cierre` (154 del nivel, cerrado). La escala va completa (también inactiva).
- **Observaciones Generales del Director de Grupo** (`academico_observacion_general`, unique
  estudiante + periodo): solo el director del curso, en plazo y sin cierre. Vacía = se borra.
- **Cierre** (`cerrar`, 154 del nivel, plan aprobado): crea `academico_cierre_periodo` (curso +
  periodo) y un `academico_boletin` por estudiante con la foto (`fotoBoletin`, pura, test
  `FotoBoletinTest`): curso, grado, periodo, director, fecha del cierre, estudiante, propósitos con
  sus referentes (**sin subreferentes**; propósitos sin referentes no salen) con asignatura y
  desempeño final `{ nombre, abreviatura, color }` (o null), y la observación general. Avisa cuántos
  referentes quedaron sin desempeño pero no lo impide. Cerrado, no se califica, ajusta ni observa.
  Un estudiante que ya tiene boletín en ese periodo (de otro curso) se salta.
- **Reabrir el cierre** (`reabrirCierre`, 154 del nivel): borra el cierre y sus boletines (en
  cascada); al cerrar de nuevo se generan otra vez.

## Plazos

Años escolares y periodos académicos (validación de fechas, cruces): ver `gestion-academica.md`.

- `periodo_academico.fecha_limite_notas` (nullable, ≥ `fecha_fin`). Se califica desde `fecha_inicio`
  hasta `fecha_limite_notas ?? fecha_fin` (`NotasService::periodoEditable`, test
  `PeriodoEditableNotasTest`).
- Los referentes se trabajan hasta esa misma fecha límite, también antes de que empiece el periodo.
- Los periodos se listan por año vigente **sin filtrar `activo`** (el cron
  `periodos:desactivar-vencidos` los apaga al pasar `fecha_fin`).

## Endpoints y permisos (`routes/api/gestionAcademica.php`, prefijo `/notas`)

| Endpoint | Quién |
|---|---|
| `GET/POST /escalas`, `PUT /escalas/{id}`, `PUT /escalas/orden` | 99 |
| `GET/POST /propositos`, `PUT /propositos/{id}`, `PUT /propositos/orden` (ruta antes de `{id}`) | 99 |
| `GET /metodos-nivel`, `PUT /metodos-nivel/{id}` `{ metodo }` | 99 |
| `GET /plan?id_curso&id_periodo` | docente con carga en el grado, 153/154 de su nivel, 99 o 152 (solo lectura) |
| `PUT /referentes`, `POST /plan/estado` | docente con carga en el grado o 153/154 de su nivel; el service valida estado y asignaturas |
| `GET /referentes/grados?id_periodo=` | 153 / 154 (grados del nivel con cursos, directores, cantidad de referentes, estado y `alcance`) |
| `GET /mis-cursos` | perfiles docente (3, 14): `cursos[] { id_curso, curso, grado, es_director, asignaturas[] { id, nombre } }` y `periodos` |
| `GET /planilla?id_curso&id_periodo&id_asignatura`, `PUT /planilla` | perfiles docente; el service exige carga en esa asignatura y curso |
| `GET /cursos` | 99, 152 (todos los cursos cualitativos) o 153/154 (los de su nivel), con `periodos` |
| `GET /curso?id_curso&id_periodo` | director del curso, 153/154 de su nivel, 99 o 152 (403 si no) |
| `PUT /observaciones` | perfiles docente; el service exige ser el director del curso |
| `PUT /ajuste`, `POST /cierre`, `DELETE /cierre` | 154; el service exige el nivel del curso |

- `PUT /referentes`: `{ id_curso, id_periodo, referentes: [{ id?, id_proposito, id_asignatura,
  descripcion, subreferentes?: [{ id?, descripcion }] }] }` (hasta 100 referentes y 30
  subreferentes cada uno). Responde los referentes guardados.
- `GET /plan` → `{ curso, periodo, estado, grado, compartido, propositos[] (activos y los ya usados),
  asignaturas[] { id, nombre, con_carga, editable }, referentes[] { id, id_proposito, id_asignatura,
  descripcion, orden, subreferentes[] { id, descripcion, orden } }, historial[], puede }`.
  `asignaturas[].editable` = puede editar sus referentes (aparte del estado; ese lo dice
  `puede.editar`). `con_carga: false` = asignatura por reubicar.
- `PUT /planilla`: `{ id_curso, id_periodo, id_asignatura, estudiantes: [{ id_estudiante, notas: [{
  id_referente, id_escala|null }] }] }`.
- `PUT /observaciones`: `{ id_curso, id_periodo, observaciones: [{ id_estudiante, observacion|null }] }`
  (hasta 3000 caracteres).
- `PUT /ajuste`: `{ id_curso, id_periodo, id_estudiante, id_referente, id_escala|null }`.
- `POST /cierre` y `DELETE /cierre`: `{ id_curso, id_periodo }`.

Opciones (módulo 14, no otorgadas a ningún perfil; se asignan en `/permisos`):
**152** "Gestión Académica — Consultar notas", **153** "Gestionar referentes", **154** "Aprobar
referentes". En producción el 149 y el 150 ya son de otros módulos: por eso no se usan.

## Migraciones (en `migraciones_sql.sql`)

1. `2026_11_05_110000_create_academico_grado_table`: catálogo de grados y `curso.id_grado` (ver
   gestion-academica.md). Corre **antes** que las tablas de notas (FK a `academico_grado`).
2. `2026_11_05_120000_create_academico_notas_cualitativas_tables`: todo el esquema (método por
   nivel, fecha límite de notas, escala y sus niveles, propósitos, referentes, plan e historial,
   valoraciones por referente).
3. `2026_11_05_120100_seed_opciones_notas`: opciones 152, 153 y 154.
4. `2026_11_05_120200_create_academico_boletin_tables`: observaciones generales del director,
   cierre del periodo por curso y boletines (foto JSON). En `sami_royal` se corrió con `migrate`.

La rama no está en producción, así que las migraciones se reescriben en vez de apilar correcciones.
El 2026-11-13 la 120000 pasó de dimensiones/logros a propósitos/referentes. En `sami_royal` local se
aplicó a mano (sin `migrate:rollback`, porque el `down()` del seed viejo borraba los permisos de las
opciones 149 y 150, que ya son de otros módulos): se borraron las tablas viejas y las columnas
`tipo_unidad`/`calificador`, se corrió `down()` + `up()` de la nueva y se restauraron la escala, el
método por nivel y las fechas límite; las dimensiones de tipo DIMENSION pasaron a ser los propósitos
iniciales; las opciones 153 y 154 se renombraron. Se perdieron 2 logros y 1 nota de prueba (hay
respaldo con mysqldump del 2026-11-13). **`sami_hebreo` sin correr.** Al desplegar, migrar en cada
base de colegio.

## Tests

`tests/Unit/GestionAcademica/`: `AccionesPlanReferentesTest` (acciones por estado, rol y plazo),
`CambiosDeReferentesTest` (aplanar y varios docentes en el mismo plan), `FotoBoletinTest` (qué guarda
el boletín), `PeriodoEditableNotasTest` y `FechasPeriodoAcademicoTest`. Lo que toca la base no tiene
tests automáticos: `phpunit.xml` usa SQLite en memoria y el esquema legacy es de MySQL. Se probó en
`sami_royal` dentro de transacciones deshechas (2026-11-13, Sexto A, Español y Literatura + otra
asignatura con otro docente): referentes por docente y rechazo en asignatura ajena, enviar solo
quien gestiona, no quitar un referente con notas, planilla en plazo y con el plan aprobado, ajuste
(no en subreferentes, solo 154), observaciones solo del director, vista del curso con `puede`,
cierre con boletines, nada se cambia cerrado, reabrir el cierre y quitar la nota del docente
conservando el ajuste. El periodo de prueba no había empezado: se movieron `fecha_inicio` y
`fecha_limite_notas` y se restauraron a mano (MyISAM).

## Trampas

- `periodo_academico` y (en algunas BD) `curso` son MyISAM: un `rollBack()` no deshace cambios en
  ellas, ni siquiera en una prueba. Si una prueba cambia fechas de un periodo, restáuralas a mano.
- `DB_DATABASE=otra php artisan …` en Git Bash **no** cambia de base (sigue usando la del `.env`):
  para probar migraciones en una base temporal, cambia `config('database.connections.mysql.database')`
  y haz `DB::purge`/`reconnect` dentro de tinker.
- Los referentes de un plan aprobado no se tocan sin reabrir: el backend lo bloquea.

## Pendiente

- **Fase 3 (frontend)**: renombrar a propósitos y referentes; Configuración académica (propósitos,
  método por nivel) y opciones 152–154; página de Referentes (por asignatura, con subreferentes);
  planilla del docente (`mis-cursos` → curso → asignatura); vista del curso para el director
  (observaciones) y el coordinador (ajuste, cierre, reabrir); consulta (152).
- **Fase 4**: documento `boletin` en `src/documentos` (pdfmake), PDF en el servidor desde
  `academico_boletin.datos` e historial de boletines por estudiante.
- Parte cuantitativa (nota numérica, rangos por desempeño para `AMBAS`) y vista del acudiente /
  estudiante.
