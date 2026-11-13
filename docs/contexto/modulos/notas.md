# Notas (`/gestion-academica/notas` — `NotasController`, `NotasService`)

Sistema de notas. Se está construyendo la **parte cualitativa**: desempeños (escala) por
**referentes** de cada **grado**, agrupados en **propósitos** y definidos por **asignatura**, con un
flujo de aprobación antes de calificar y un **boletín trimestral** con historial. La parte
cuantitativa (nota numérica) está pendiente. Frontend: `frontend/docs/contexto/modulos/notas.md`.

> **Estado (2026-11-13, rama `feat/notas-cualitativas`).** Fase 1 hecha: modelo, propósitos y
> referentes con su plan. **La planilla, la consulta y "mis cursos" se quitaron** porque dependían
> del modelo anterior (dimensiones, modos por nivel) y vuelven reescritos en la fase 2 (ver
> "Pendiente"). El frontend de la rama aún usa la API anterior hasta la fase 3.

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

- `PUT /referentes`: `{ id_curso, id_periodo, referentes: [{ id?, id_proposito, id_asignatura,
  descripcion, subreferentes?: [{ id?, descripcion }] }] }` (hasta 100 referentes y 30
  subreferentes cada uno). Responde los referentes guardados.
- `GET /plan` → `{ curso, periodo, estado, grado, compartido, propositos[] (activos y los ya usados),
  asignaturas[] { id, nombre, con_carga, editable }, referentes[] { id, id_proposito, id_asignatura,
  descripcion, orden, subreferentes[] { id, descripcion, orden } }, historial[], puede }`.
  `asignaturas[].editable` = puede editar sus referentes (aparte del estado; ese lo dice
  `puede.editar`). `con_carga: false` = asignatura por reubicar.

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
`CambiosDeReferentesTest` (aplanar y varios docentes en el mismo plan), `PeriodoEditableNotasTest` y
`FechasPeriodoAcademicoTest`. Lo que toca la base (guardar referentes por docente, rechazo en
asignatura ajena, enviar solo quien gestiona, no quitar un referente con notas) no tiene tests
automáticos: `phpunit.xml` usa SQLite en memoria y el esquema legacy es de MySQL. Se probó en
`sami_royal` dentro de una transacción deshecha (2026-11-13, Sexto, Español y Literatura + otra
asignatura con otro docente).

## Trampas

- `periodo_academico` y (en algunas BD) `curso` son MyISAM: un `rollBack()` no deshace cambios en
  ellas, ni siquiera en una prueba. Si una prueba cambia fechas de un periodo, restáuralas a mano.
- `DB_DATABASE=otra php artisan …` en Git Bash **no** cambia de base (sigue usando la del `.env`):
  para probar migraciones en una base temporal, cambia `config('database.connections.mysql.database')`
  y haz `DB::purge`/`reconnect` dentro de tinker.
- Los referentes de un plan aprobado no se tocan sin reabrir: el backend lo bloquea.

## Pendiente

- **Fase 2 (backend)**: "mis cursos" (cursos donde el docente tiene carga, y los que dirige),
  planilla del docente por sus referentes y subreferentes (en plazo y con el plan aprobado), ajuste
  del coordinador al final de un referente, vista del director de su curso y sus "Observaciones
  Generales del Director de Grupo" (tabla nueva), cierre del trimestre por curso con la foto del
  boletín (tabla nueva), consulta (152).
- **Fase 3 (frontend)**: renombrar a propósitos y referentes; páginas de Referentes, planilla del
  docente y vista del director; Configuración académica (propósitos, método por nivel).
- **Fase 4**: documento `boletin` en `src/documentos` (pdfmake), PDF en el servidor desde la foto e
  historial de boletines.
- Parte cuantitativa (nota numérica, rangos por desempeño para `AMBAS`) y vista del acudiente /
  estudiante.
