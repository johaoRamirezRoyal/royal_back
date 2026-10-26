# Evaluaciones (`/evaluaciones` — `EvaluacionesController`)

Evaluaciones de calidad y desempeño (Gestor de Calidad, no académico): un coordinador
evalúa periódicamente, una vez por `periodo` institucional, a los usuarios de ciertos
perfiles y niveles a su cargo.

Jerarquía: `Evaluacion` → `EvaluacionServicio` → `EvaluacionSeccion` (con `porcentaje`)
→ `EvaluacionPregunta` (con `EvaluacionTipoPregunta`) → `EvaluacionOpcionPregunta`
(con `valor`). Cada respuesta es un `EvaluacionRespuestaEvaluacion` con sus filas de
`EvaluacionRespuestaPregunta`.

## Permisos

Las constantes están en `Permiso`: **122** `EVALUACIONES_ADMIN` (CRUD), **123**
`EVALUACIONES_RESULTADOS` (lectura) y **124** `EVALUACIONES_REALIZAR` (mis evaluaciones,
evaluables, responder, reenviar, PDF). Casi toda lectura acepta el OR de las tres.

Además del gate hay un alcance por perfil dentro del service:
- **Coordinador (26)**: en `obtenerEvaluables`/`enviarRespuesta`, solo usuarios de **su**
  nivel. En `mis-evaluaciones`, solo las que creó o que incluyen su nivel. Las respuestas
  (listar, ver, editar, reenviar, PDF) solo las suyas.
- **Super Admin**: sin restricciones.
- **`PERFILES_SIN_NIVEL = [17]`** (proveedores): se ignora el filtro de nivel.

## Datos y reglas

- La mayoría de las tablas son legacy, sin migración; `evaluaciones_perfil` sí la tiene.
- **Evaluar dos veces lo impide el unique `uq_resp_eval_evaluado_periodo`**
  (evaluación + evaluado + periodo). Por eso "Evaluar" siempre está disponible en otro
  periodo. `evaluado`/`id_respuesta` de `GET /{id}/evaluables` apuntan a la respuesta
  **más reciente** (`MAX(completada_en)`), sin importar el periodo.
- `id_periodo` apunta a `periodos` (el institucional, no `periodo_academico`). **Es
  obligatorio al responder** y debe ser un periodo activo (si no, 422). No tiene valor
  por defecto.
- **El año de una respuesta es `id_anio_escolar`**, resuelto con
  `obtenerUltimoAnioEscolar()` al guardar. **No** uses `periodo.anioEscolar`: es un dato
  del catálogo legacy y puede no coincidir. Para leerlo, usa la relación
  `respuesta.anioEscolar`.
- **`PeriodoServices`** (dominio año académico): `listar()`, `resolverActivo()` (por
  `en_curso = 1`, la única fuente confiable; los periodos se marcan a mano en la BD) y
  `periodoActivo()`.
- **Pregunta ya respondida**: no se puede cambiar su tipo ni crear, editar o borrar sus
  opciones (422, `preguntaTieneRespuestas()`), porque cambiaría puntajes ya calculados.
  El texto y `permite_comentario` siguen editables. `GET /{id}` trae `respuestas_count`
  por pregunta.
- Secciones, preguntas y opciones se borran de verdad (**hard delete**) y no tienen FK
  reales (son MyISAM); `eliminarPregunta`/`eliminarSeccion` todavía no tienen la
  protección anterior. La evaluación usa `SoftDeletes` y el servicio se desactiva con
  `activo=0`.
- La validación va inline con `Validator::make()`; no hay FormRequests.
- Se serializa `tipo`, no `tipo_pregunta`.
- `id_nivel` de la respuesta usa `?:` (no `??`) para descartar el 0 de los proveedores.
- **Tipos de pregunta**: solo hay alta (`POST /tipos-pregunta`, slug derivado del
  nombre) y CRUD de sus opciones por defecto (122). Las opciones por defecto se copian al
  crear una pregunta, así que editarlas no afecta las preguntas existentes. Los slugs con
  comportamiento especial son `texto_libre` (`valor_texto`) y `seleccion_multiple` (una
  fila por opción marcada); cualquier otro funciona como selección única.
- `PUT /{id}/preguntas/orden` (`{ secciones: [{ id, preguntas: [ids] }] }`): cada lista
  debe traer exactamente las preguntas actuales de esa sección.
- `POST /{id}/duplicar` (122) crea una copia completa **inactiva** titulada
  `"<título> (Copia n)"`, reusando `crear()`.
- La creación es anidada en un solo `POST /` (transacción): niveles, perfiles y
  secciones con sus preguntas y opciones. Pregunta y opción usan `texto`, no `titulo`.

## Puntaje (`calcularResultados`)

- Es la suma de los `valor` sobre el máximo posible (el mayor valor de cada pregunta),
  ponderada por el `porcentaje` de cada sección. El texto libre no puntúa.
- **"No aplica"** se habilita por pregunta o por sección (`permite_no_aplica`; la regla
  es OR, en `validarNoAplica`).
  - Se guarda como una fila con `no_aplica = 1`.
  - La pregunta sale del máximo de su sección.
  - Si toda la sección es N/A, no pondera y su porcentaje se reparte entre las demás.
  - Sin N/A, el factor es 1 y las notas anteriores no cambian.
  - El PDF muestra "No aplica / No pondera".
- `GET /{id}/resultados` todavía no tiene consumidor en el frontend.

## Correo y PDF

`enviarCorreoRespuesta` (TCPDF, `EvaluacionRespuestaPdfService`) corre fuera de la
transacción; un fallo solo se registra en el log. El mismo PDF sirve para
`GET /respuestas/{id}/pdf`, que devuelve el binario o, ante un error de negocio, un 200
con JSON (el frontend distingue por `content-type`), y para `reenviar-correo`.

# Encuestas (`/encuestas` — `EncuestasController`, opciones 129 y 130)

Encuestas de satisfacción **anónimas** por salón: un QR en el salón abre
`/api/encuestas/publica/{token}`.

- Tablas propias (InnoDB): `encuestas`, `encuestas_preguntas`,
  `encuestas_opciones_pregunta`, `encuestas_salon` (encuesta activa y token por salón),
  `encuestas_respuestas` (sin `id_user`) y `encuestas_respuestas_pregunta`. Reusa
  `evaluaciones_tipos_pregunta`, `salones` y `reservas`.
- `descripcion` es TEXT (máximo 5000) y el texto de pregunta VARCHAR(500).
- `PUT /{id}/preguntas/orden` (`{ preguntas: [ids] }`, todas) y `POST /{id}/duplicar`
  (no copia salones ni respuestas).
- En el flujo público se ocultan las preguntas de opciones que no tienen opciones
  (`esRespondible()`), y no se exigen aunque sean obligatorias.
