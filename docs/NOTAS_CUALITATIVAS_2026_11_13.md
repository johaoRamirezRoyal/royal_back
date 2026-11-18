# Notas cualitativas: propósitos, referentes y boletín (2026-11-13)

Cambio en `royal_back` y en el frontend (`S.A.M.I_frontend`), en la rama
`feat/notas-cualitativas` de los dos repos (**sin push, sin fusionar en `main`**). Las reglas
vigentes están en [`contexto/modulos/notas.md`](contexto/modulos/notas.md) y, del lado del
frontend, en `docs/contexto/modulos/notas.md`. Este documento explica qué se cambió, por qué, qué
hay que hacer en el deploy y qué falta.

## Por qué

La primera versión de la rama (2026-11-05 a 2026-11-11) evaluaba por **dimensiones** asignadas a
cada grado, con **logros** por grado y periodo, y dos modos por nivel: por dimensión (califica el
director de grupo) o por asignatura (califica el docente de la asignatura). El 2026-11-13 el
colegio definió cómo trabaja de verdad:

- Los boletines son **trimestrales** y quedan registrados para tener un historial.
- En vez de dimensiones son **propósitos**, y los indicadores se llaman **referentes**.
- El propósito contiene sus referentes. Los propósitos son **los mismos para todos** los docentes
  (un catálogo del colegio); los referentes son **por grado** y cambian **por asignatura** y **por
  periodo**.
- Un referente puede tener **subreferentes**, que se califican igual pero **no salen en el
  boletín**.
- El **docente** de cada asignatura califica sus referentes; el desempeño final del referente lo
  elige él. El **coordinador** puede cambiar ese final.
- El **director de grupo** ve todo lo calificado en su curso y escribe las **"Observaciones
  Generales del Director de Grupo"** al final del boletín. No pone un desempeño final por propósito.
- El **coordinador** cierra el trimestre y en ese momento queda la foto del boletín.
- Aplica a **todos los niveles** cualitativos (también preescolar).

## La solución

### Modelo

```
nivel_academico.metodo_calificacion ─ CUALITATIVA | CUANTITATIVA | AMBAS
academico_proposito (catálogo único del colegio)
grado + periodo
  └─ academico_referente_plan (BORRADOR → EN_REVISION → APROBADO | DEVUELTO) + _historial
  └─ academico_referente (propósito + asignatura; id_padre → subreferente, un nivel)
       └─ academico_valoracion_referente (estudiante: desempeño del docente + ajuste del coordinador)
curso + periodo
  ├─ academico_observacion_general (estudiante: observación del director)
  └─ academico_cierre_periodo ──< academico_boletin (estudiante: foto JSON del boletín)
desempeños: academico_escala_valoracion ──< academico_escala_valoracion_nivel
```

- **Propósito**: sin vínculo por grado. No se borra: se desactiva.
- **Referente**: obligatorio el propósito y la asignatura (`id_asignatura` sin FK:
  `academico_asignatura` es MyISAM). Un subreferente copia propósito y asignatura del padre.
- **Asignaturas de un grado**: las que tienen carga académica activa en alguno de sus cursos
  activos. Si una pierde la carga, sus referentes quedan **por reubicar** y no dejan enviar ni
  aprobar el plan.
- **Valoración**: final = ajuste del coordinador ?? desempeño del docente. El ajuste guarda quién y
  cuándo, y el valor del docente se conserva.
- **Boletín**: foto en JSON (curso, grado, periodo, director, fecha del cierre, estudiante,
  propósitos con sus referentes y desempeño final, observación general). No cambia aunque después
  se corrija una nota.

### Quién hace qué

| Rol | Puede |
|---|---|
| Docente con carga en el grado | Definir los referentes de **sus** asignaturas (en borrador o devuelto) y **enviarlos a revisión de coordinación** y calificar sus referentes y subreferentes en los cursos donde tiene carga |
| Director de grupo | Ver todo lo calificado en su curso y escribir las observaciones generales |
| 153 "Gestionar referentes" (de su nivel) | Editar los referentes de todas las asignaturas |
| 154 "Aprobar referentes" (de su nivel) | Además aprobar (también directo, sin esperar los envíos), devolver y reabrir el plan; ajustar el final de un referente; cerrar y reabrir el periodo de un curso, o cerrarlo en todos los cursos del nivel a la vez (cierre general, 2026-11-17); escribir la asistencia del boletín |
| 152 "Consultar notas" y 99 | Ver las notas de cualquier curso en solo lectura (99 también la configuración) |
| 172 / 173 "Ver registro de boletines" (2026-11-18) | Ver el Registro de boletines y descargar los PDF: 172 los cursos de su nivel, 173 los de todos los niveles. No dan acceso a Notas del curso |

**Evaluación descriptiva (2026-11-17).** Los grados marcados como `DESCRIPTIVA` (Pre-jardín, Stem…) no usan referentes ni escala: por estudiante, cada docente con carga en el curso escribe un texto en los campos de cada propósito (Fortalezas, Por fortalecer, Recomendaciones…, definidos por propósito en Configuración académica). Mismo cierre, sin plan que aprobar; el boletín es el documento `boletinDescriptivo`. Migración `2026_11_05_120300_create_academico_evaluacion_descriptiva_tables`.

Cada docente envía a revisión los referentes de su asignatura (y puede retirar ese envío mientras
los demás no terminen). Cuando todas las asignaturas con carga en el grado enviaron, el plan pasa
solo a revisión y coordinación (154) lo aprueba o lo devuelve; devolver o reabrir obliga a todos a
enviar de nuevo. Migración `2026_11_05_120400_create_academico_referente_envio_table` (2026-11-17;
antes enviaba solo quien gestiona, el plan completo). Coordinación también puede aprobar
directamente un plan en borrador o devuelto, sin esperar los envíos (con confirmación; queda en el
seguimiento qué asignaturas no habían enviado).

### Plazos

- Se califica y observa entre `fecha_inicio` y `fecha_limite_notas ?? fecha_fin` del periodo, con
  el plan **aprobado** y **sin cierre**.
- El coordinador ajusta hasta el cierre **sin mirar el plazo** (corrige después de la fecha límite).
- Los referentes se trabajan hasta esa misma fecha límite, pero el plazo **solo limita a los
  docentes**: coordinación (153/154) edita y reabre los de un **periodo pasado** (2026-11-18). La UI
  lo advierte, cada cambio queda marcado "periodo pasado" y cada guardado deja en el historial una
  entrada `FUERA_DE_PLAZO` (fecha de cierre y cuántos referentes se crearon, editaron o quitaron).
- Después del cierre nada cambia. Reabrir borra el cierre y sus boletines, y al cerrar de nuevo se
  generan otra vez (no queda historial de versiones de un mismo trimestre).

### Asistencia del boletín (2026-11-18)

Inasistencias (días con alguna clase AUSENTE) y llegadas tarde (portería, sin revocadas) se cuentan
al cerrar. En Notas del curso, el director (en plazo) o 154 (sin cierre) pueden **escribirlas a mano
aunque haya registros**: lo escrito reemplaza al conteo en el boletín, la ficha avisa cuántas ya
cuenta el sistema y vacío vuelve al conteo. Tabla `academico_asistencia_manual`.

### Registro de boletines (2026-11-18)

Página Notas → Registro de boletines: por periodo, cada curso cualitativo con su cierre (fecha,
quién, cuántos boletines) o sin cerrar, agrupado por nivel, con descarga del PDF del curso. 99, 152
y 173 ven todos los niveles; 153, 154 y 172, el suyo.

### Backend (`royal_back`)

- `NotasService` y `NotasController` reescritos; endpoints con prefijo `/api/gestion-academica/notas`:
  - Configuración (99): `/escalas`, `/propositos` (+ `/orden`), `/metodos-nivel`.
  - Referentes: `GET /plan`, `PUT /referentes`, `POST /plan/estado` (enviar y retirar por
    asignatura, aprobar, devolver, reabrir), `GET /referentes/grados`.
  - Calificación: `GET /mis-cursos`, `GET|PUT /planilla` (por curso **y asignatura**),
    `GET|PUT /planilla-descriptiva` (grados descriptivos).
  - Curso: `GET /cursos`, `GET /curso`, `PUT /observaciones`, `PUT /asistencia-manual`, `PUT /ajuste`,
    `POST|DELETE /cierre`, `POST /cierre/nivel`.
  - Boletines: `GET /boletines` (del curso), `GET /boletines/estudiante` (historial) y
    `GET /boletines/registro` (registro por periodo).
- Modelos nuevos: `Proposito`, `PropositoCampo`, `Referente`, `ReferentePlan`, `ReferentePlanHistorial`,
  `ReferenteEnvio`, `ValoracionReferente`, `ValoracionDescriptiva`, `ObservacionGeneral`,
  `AsistenciaManual`, `CierrePeriodo`, `Boletin`. Se borraron `Dimension`,
  `Logro`, `LogroPlan`, `LogroPlanHistorial`, `Valoracion` y `ValoracionLogro`.
- Se retiraron los modos por nivel (`nivel_academico.tipo_unidad` y `calificador`), las unidades
  de tipo asignatura, la sincronización de asignaturas y `academico_grado_dimension`.
- `PeriodoAcademicoServices::eliminarPeriodoAcademico` no borra un periodo con referentes u
  observaciones.

### Frontend (`S.A.M.I_frontend`)

| Página | Ruta | Quién |
|---|---|---|
| Configuración académica → Calificación: Método, Escala y **Propósitos** | `/gestion-academica/configuracion?tab=propositos` | 99 |
| **Referentes** (antes Logros) | `/gestion-academica/referentes` | 153 / 154 |
| **Notas** (planilla del docente: curso → asignatura → periodo) | `/gestion-academica/notas` | tipo Docente |
| **Notas del curso** (antes Consulta de notas) | `/gestion-academica/notas/curso` | 99, 152, 153, 154 o tipo Docente |
| **Registro de boletines** (2026-11-18) | `/gestion-academica/notas/boletines` | 99, 152, 153, 154, 172, 173 |

En el menú, todo esto va en Gestion Academica → Notas (Calificar, Notas del curso, Referentes,
Registro de boletines).

- La planilla y Notas del curso abren primero la lista del curso y, al elegir un estudiante, su
  formulario: un bloque por propósito con cada referente (su desempeño es el final) y sus
  subreferentes debajo.
- El editor de referentes tiene una sección por asignatura; cada docente edita solo las suyas y ve
  las demás en solo lectura.
- **Boletín en PDF**: documento `boletin` (común en `src/documentos/instituciones/comun/boletin.ts`;
  Royal School tiene el suyo, el "INFORME EVALUATIVO", en `royal-school/boletin.ts`) y
  `boletinDescriptivo` para los grados descriptivos; uno por hoja. **Lo genera el navegador** con
  la foto guardada al cerrar (`helpers/boletinesPdf.ts`), así que no necesita Node en el servidor.
  Se descarga en Notas del curso (el curso y el historial de cada estudiante) y en el Registro de
  boletines. La foto lleva número de lista, usuario, año, inasistencias y llegadas tarde: un cierre
  hecho antes de que existieran (2026-11-17 en la mañana) no los trae; se arregla reabriendo y
  cerrando de nuevo.

## Migraciones

La rama no está en producción, así que sus migraciones se reescribieron en vez de apilar
correcciones. Orden (todas en `migraciones_sql.sql`):

1. `2026_11_05_110000_create_academico_grado_table`: catálogo de grados y `curso.id_grado`.
2. `2026_11_05_120000_create_academico_notas_cualitativas_tables`: método por nivel, fecha límite
   de notas, escala, propósitos, referentes, plan e historial, valoraciones por referente.
3. `2026_11_05_120100_seed_opciones_notas`: opciones **152** "Gestión Académica — Consultar
   notas", **153** "Gestionar referentes", **154** "Aprobar referentes" (módulo 14). Falla si un id
   ya está ocupado por otra opción.
4. `2026_11_05_120200_create_academico_boletin_tables`: observaciones generales, cierres y boletines.
5. `2026_11_05_120300_create_academico_evaluacion_descriptiva_tables`: `academico_grado.tipo_evaluacion`,
   campos por propósito y textos descriptivos.
6. `2026_11_05_120400_create_academico_referente_envio_table`: envío a revisión por asignatura.
7. `2026_11_18_100000_create_academico_asistencia_manual_table`: inasistencias y llegadas tarde del
   boletín escritas a mano.
8. `2026_11_18_110000_seed_opciones_registro_boletines`: opciones **172** "Ver registro de boletines
   (su nivel)" y **173** "(todos los niveles)", módulo 14. Falla si un id ya está ocupado.

**SQL a mano y MyISAM.** El servidor crea las tablas MyISAM si el `create table` no dice
`engine = InnoDB`, y MyISAM descarta las llaves foráneas en silencio; después una tabla InnoDB no
puede apuntar a ellas (error 150). Por eso todas las tablas de notas en `migraciones_sql.sql` llevan
`engine = InnoDB`. En producción las migraciones 1–6 se corrieron a mano el 2026-11-17 con un script
repetible (crea solo lo que falta, pasa a InnoDB y agrega cada llave con `IF NOT EXISTS`).

**Ids de opción.** La primera versión usaba 149–151, pero en producción el 149 es "Mis áreas
comunes — Ver áreas del bloque" y el 150 "Seguridad social de trabajadores". Por eso pasaron a
152–154 (el 151 queda libre). Del 155 al 171 son de la fase 3 de permisos.

**Base local `sami_royal`.** No se usó `migrate:rollback`: el `down()` del seed viejo borraba los
permisos de las opciones 149 y 150, que ya son de otros módulos. Se borraron a mano las tablas
viejas y las columnas `tipo_unidad`/`calificador`, se corrió `down()` + `up()` de la 120000 nueva y
se restauraron la escala (3 desempeños), el método de cada nivel y las fechas límite. Las 3
dimensiones de preescolar pasaron a ser los propósitos iniciales y las opciones 153 y 154 se
renombraron. Se perdieron 2 logros y 1 nota de prueba de otra sesión (respaldo con mysqldump del
2026-11-13 en el scratchpad de la sesión). La 120200 se corrió con `migrate`.

## Deploy

1. `feat/notas-cualitativas` se fusionó en `main` (los dos repos) y en `produccion` el 2026-11-17;
   lo posterior se trabaja directo en `main`. Backend y frontend se suben juntos; el backend se
   despliega fusionando `main` en `produccion`.
2. Correr las migraciones en **cada base de colegio** (`sami_hebreo` no tiene ninguna). Antes,
   confirmar que los ids 152–154 y 172–173 siguen libres en esa base. Si se corren a mano, usar el
   SQL de `migraciones_sql.sql` (con `engine = InnoDB`) y registrarlas en `migrations`.
3. En `/permisos`, otorgar 152, 153, 154, 172 y 173 a los perfiles que correspondan (las
   migraciones no las otorgan a nadie).
4. En Configuración académica: método de calificación de cada nivel, escala de valoración y
   propósitos. Cada curso necesita su **grado** (Cursos) y las asignaturas su **carga académica**:
   sin carga, un grado no tiene asignaturas para definir referentes.
5. El boletín no necesita Node en el servidor (lo arma el navegador).

## Pruebas

- Tests unitarios (`tests/Unit/GestionAcademica/`): `AccionesPlanReferentesTest`,
  `CambiosDeReferentesTest`, `FotoBoletinTest`, `PeriodoEditableNotasTest`,
  `FechasPeriodoAcademicoTest`. Con `main` fusionado pasan los 113 tests unitarios del backend.
- Contra `sami_royal`, en transacciones deshechas (Sexto A, dos docentes reales con asignaturas
  distintas): referentes por docente y rechazo en asignatura ajena, enviar solo quien gestiona (la
  regla de entonces; hoy envía cada docente su asignatura), no
  quitar un referente con notas, planilla en plazo y con el plan aprobado, ajuste (no en
  subreferentes, solo 154), observaciones solo del director, cierre con boletines, nada cambia
  cerrado, reabrir, quitar la nota del docente conservando el ajuste.
- En el navegador, con el Super Admin: Propósitos; Referentes (crear con subreferente, enviar,
  aprobar); Notas del curso (ajuste, cerrar y reabrir); boletín del curso (28 estudiantes) y de un
  estudiante desde su historial. Los datos de prueba se borraron.
- `tsc` del frontend: sin errores nuevos (62 previos en la rama).

## Trampas encontradas

- `periodo_academico` y (en algunas bases) `curso` son **MyISAM**: un `rollBack()` no deshace
  cambios en ellas, ni siquiera en una prueba. Al probar con fechas de periodo, restaurarlas a mano.
- El "Tercer periodo" de prueba aún no empezaba: todo lo que depende del plazo se rechazaba, como
  debía. Para probar hubo que mover `fecha_inicio` y `fecha_limite_notas`.
- Al fusionar `main`, la fase 3 de permisos había quitado `Permiso::PERFILES_DOCENTE` y
  `CursosServices::PERFIL_ESTUDIANTE`: notas usa ahora `[TipoPerfil::DOCENTE]` en las rutas y
  `Perfil::idsDeTipo(TipoPerfil::ESTUDIANTE)`; en el frontend, `allowedTipos: ["DOCENTE"]`.
- Algunos nombres de estudiantes vienen con espacios de más y apellido ".": el boletín los limpia.
- La foto del boletín es la del momento del cierre: un campo que se agregue después no aparece en
  los boletines ya cerrados hasta reabrir y cerrar de nuevo (pasó con número de lista, usuario y año).

## Decisiones para revisar con el colegio

- **Reabrir borra los boletines** de ese curso y periodo; no hay historial de versiones del mismo
  trimestre.
- Un estudiante que se cambia de curso a mitad del trimestre recibe el boletín del **primer curso
  que se cierre** (unique estudiante + periodo); el otro lo salta.
- Cerrar el periodo **avisa** cuántos referentes quedaron sin desempeño, pero no lo impide.
- Royal School usa su propio boletín ("INFORME EVALUATIVO", solo referentes y propósitos, sin
  asignatura ni subreferentes, con la firma del director). Los demás colegios, el formato común.
- La asistencia escrita a mano reemplaza al conteo de los registros en el boletín (no los cambia).

## Pendiente

- Probar la planilla del docente (`/gestion-academica/notas`) con un docente con carga académica.
- Revisar las páginas a 360 px.
- Subir los cambios del 2026-11-18 (periodo pasado, asistencia manual, registro de boletines) y
  correr las migraciones 7 y 8 en el servidor.
- Decidir si hace falta una opción para ver los referentes de todos los niveles (hoy solo con la 99).
- PDF del boletín en el servidor, si se quiere enviar por correo o al acudiente.
- Parte cuantitativa (nota numérica) y vista del acudiente / estudiante.

## Otros cambios de la misma sesión (en `main`, fuera de esta rama)

- Frontend `9119496` (en `main`): `exceljs` y `pdfjs-dist` importados de forma estática en
  `ImportarExcel.parts.tsx` y `pdfAFirmaPng.utils.ts`; Vite avisaba en el build de producción que
  esos `import()` dinámicos no separaban nada.
- Backend `25eee16` (en `main` y en `produccion`): el error de `DocumentoPdfService` incluye el
  código de salida de Node (127 = no está, 137 = lo mató el sistema). La carta de entrega fallaba
  en producción con un error vacío porque el servidor no tenía Node; se instaló Node 22 y se
  recomendó `NODE_BINARY` en el `.env` del backend.
