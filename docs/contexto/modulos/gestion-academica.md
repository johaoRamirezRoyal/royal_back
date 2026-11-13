# Gestión Académica (`/gestion-academica` — `GestionAcademicaController`)

Más contexto: `docs/GESTION_ACADEMICA_HORARIOS_DOCENTE_2026_08_24.md`. Registro de actas
está en [`gestion-convivencial.md`](gestion-convivencial.md).

## Permisos y autoservicio del docente

- Todo el controller exige la opción **99**, salvo las rutas de autoservicio.
- Docente (perfiles 3 y 14) sin la 99 entra a los métodos de `METODOS_DOCENTE`: asistencia
  de sus clases, su horario (y su exportación), `obtenerMisCursos`, `verFranjasHorarias`
  y `verMetricasAsistencia` (que además acepta la 102).
  - **Una ruta nueva de autoservicio** se agrega a `METODOS_DOCENTE` **y** lleva
    `perfil:3,perfil:14` en `routes/api/gestionAcademica.php`. `verFranjasHorarias` quedó
    fuera la primera vez y rompió "apartar horario".
  - `id_docente` sale siempre de la sesión. Las métricas se limitan en el service
    (`metricasPorCurso` con `id_docente_scope`): el permiso de la ruta no basta para un
    endpoint que agrega datos de otros.
- **Consulta de asistencias de clases** (opción 132 o 99) vive en su propio controller
  (`ConsultaAsistenciaClaseController`), para que dar la 132 no abra nada más del módulo.
  Es de solo lectura.
  - Endpoints: `GET /consulta-asistencias` (contrato DataTable + `fecha_inicio`,
    `fecha_fin`, `id_curso`, `id_docente`, `id_asignatura`, `estado`), `/{id}` y
    `/filtros`.
  - El docente se resuelve con `COALESCE(ca.id_docente, da.id_docente)`.
  - `total_estudiantes` son los activos **hoy** en el curso, y el % de asistencia solo se
    calcula en clases `DICTADA`.
- La asistencia (`academico_asistencia_estudiante`) solo guarda excepciones (AUSENTE,
  TARDE, PERMISO).

## Horarios

- Las **franjas** cuelgan de un **esquema** (`academico_esquema_horario`, único por nivel
  y año). `id_anio_escolar` en la franja existe solo por compatibilidad; se deriva del
  esquema. `FranjaHorariaService::resolverIdEsquema` encuentra el esquema por
  `curso.id_nivel`.
- El unique es `uq_franja_horaria_esquema (id_esquema, id_dia_semana, orden)`. El índice
  original por año impedía que dos niveles compartieran día y orden.
- La etiqueta y el color de una franja aplican a cualquier franja y no se borran al
  volverla asignable. Desmarcar "No asignable" con "aplicar a todos los días" también
  propaga el cambio (`quitarNoAsignableDeOtrosDias`).
- **Horario suelto** (sin asignatura): `academico_carga_academica.id_docente_asignatura`
  pasó a nullable y se agregó `id_docente` directo. Siempre va uno de los dos, nunca ambos
  (lo asegura el código, no la BD), con `unique(id_docente, id_curso)`.
  - Usa el accessor `CargaAcademica::id_docente_efectivo` (no está en `$appends`).
  - Una consulta que busque el docente debe cubrir ambos caminos (`orWhere`/`leftJoin`,
    no `whereHas`/`join`). Ya lo hacen `HorarioClaseService`, `FranjaHorariaService`,
    `DocenteHorarioService`, `HorarioExcelService` y `AsistenciaEstudianteService`.
  - Solo el admin lo crea (`añadirCargaAcademicaSuelta`). El autoservicio del docente se
    construyó y **se revirtió a pedido**; si se retoma, está en el historial de git.
- Las migraciones que cambian columnas usan `DB::statement('ALTER TABLE ...')`, porque no
  está instalado `doctrine/dbal`.
- El seed `2026_09_11_120000_seed_franjas_horarias_primaria_bachillerato` salta los
  esquemas que ya tienen horarios (antes rompía `migrate` por FK).

## Años escolares y calendario A/B

- `anio_escolar` es legacy: solo se le agregan filas.
- `configuracion_academica` (fila única) guarda `tipo_calendario`: A (1 feb–30 nov, mismo
  año) o B (1 ago–30 jun, año siguiente). El corte vive **solo** en
  `AnioEscolarServices::rangoParaAnioInicio()`/`anioInicioParaFecha()`/
  `rangoDeAnioEscolar()`; no lo reimplementes.
- `obtenerUltimoAnioEscolar()` (`GET /compartido/anio-academico/ultimo`, también en
  Admisiones) **prioriza `activo=1`** y solo calcula por fecha si no hay ningún año
  activo.
- El cron diario `anio-escolar:cerrar-abrir` cierra el año que ya no corresponde y abre el
  que sí. No reactiva un año que se desactivó a mano.
- **Solo un año habilitado.** Habilitar un año (a mano desde la 99 o la 107, o al crearlo
  cuando es el vigente) deshabilita los demás (`deshabilitarOtros`). Las pantallas no dejan
  habilitar varios seleccionados a la vez.
- Endpoints con la 99: `GET|PUT /configuracion-calendario`, `POST /anios-escolares` y
  `PUT /anios-escolares/estado`.
- **Transición de año/periodo administrativo (opción 107)**. Los periodos de la tabla
  `periodos` (administrativos, con `en_curso` global único) no son los de
  `periodo_academico`.
  - El cron diario `periodos-administrativos:sincronizar-en-curso` (00:05, después de
    `anio-escolar:cerrar-abrir`) marca en curso el periodo activo cuyas fechas cubren
    hoy. Si ninguno cubre hoy, no toca nada. Pisa un "Marcar en curso" manual al día
    siguiente si las fechas dicen otra cosa: las fechas son la fuente de verdad.
  - `PUT /administrativo/periodos/avanzar` (`PeriodoServices::avanzar`): pasa al
    siguiente periodo del mismo año; error si es el último.
  - `POST /administrativo/anios-escolares/siguiente` `{ copiar_periodos }`
    (`AnioEscolarServices::iniciarSiguienteAnio`): crea/activa el año siguiente al
    habilitado, copia sus periodos con las fechas +1 año si el año nuevo no tiene, y deja
    en curso el primero. Test: `tests/Feature/AnioEscolar/TransicionAnioPeriodoTest.php`.
- `academico_asistencia_clase` y `academico_asistencia_estudiante` se usaban sin
  migración propia; ya la tienen.

## Periodos académicos (`periodo_academico`)

- **No confundir** con `periodos` (`Periodo`, `PeriodoServices`): ese es el catálogo
  institucional/administrativo (opción 107, bandera manual `en_curso`, lo usan Inventario y
  Evaluaciones). `periodo_academico` es el académico: notas, actas, observaciones, llegadas tarde.
- Columnas: `id_anio_escolar`, `nombre`, `fecha_inicio`, `fecha_fin`, `activo` y
  `fecha_limite_notas` (opcional, ≥ `fecha_fin`: plazo para calificar y corregir, ver notas.md).
  MyISAM en algunas BD: sin FK hacia él, y un `rollBack()` no deshace cambios.
- CRUD en `/compartido/anio-academico/periodo-academico` (escritura con la 99). El año escolar
  **no se elige**: se resuelve por `fecha_inicio` (`obtenerAnioEscolarPorFecha`), al crear y al
  editar (un periodo movido de año cambia de `id_anio_escolar`).
- **Validación de fechas** (`PeriodoAcademicoServices::validarFechas` → `errorDeFechas`, pura,
  test en `tests/Unit/GestionAcademica/FechasPeriodoAcademicoTest.php`), al crear y al editar
  fechas: fin posterior al inicio, todo dentro del rango del año según el calendario
  (`rangoDeAnioEscolar`: A 1 feb–30 nov, B 1 ago–30 jun) y **sin cruzarse** con los demás
  periodos del año (los extremos cuentan: dos periodos no comparten día).
  Antes se exigía `fecha_fin ≤ 30 de junio` fijo, lo que en calendario A rechazaba todo periodo
  que terminara después de junio; editar no revalidaba nada ni recalculaba el año.
- El cron diario `periodos:desactivar-vencidos` pone `activo = 0` cuando `fecha_fin` ya pasó (el
  último día sigue activo). Por eso lo que depende de un plazo propio (notas) **no** filtra por
  `activo`, sino por fechas.
- Un periodo con notas no se elimina (las notas lo referencian sin FK): se desactiva.

## Vinculación Estudiante - Padre (`/vinculacion-acudientes`, opción 131)

No es la 73 ("Estudiantes" legacy, otorgada al Acudiente). Trabaja sobre
`estudiantes_padres`, tabla legacy sin índice único.

- Endpoints: `GET /` (acudientes, perfil 6), `GET /estudiantes-buscar`,
  `GET /{idAcudiente}/estudiantes` y `POST`/`DELETE /`.
- Desvincular es **soft** (`activo=0`); volver a vincular reactiva la fila existente.
  `vincular` exige exactamente los perfiles 6 y 16.
- `POST /importar` (xlsx/xls/csv, máximo 5 MB y 3000 filas; columna A = documento del
  acudiente, columna B = documento del estudiante):
  - Compara los documentos **normalizados** (`REGEXP_REPLACE`), porque hay documentos con
    `\t`, `\r\n` o puntos.
  - Un documento ambiguo se reporta, nunca se adivina.
  - Las filas válidas se aplican aunque otras fallen.

## Cursos y estudiantes (`/cursos`, opción 145)

- **Grados** (`academico_grado`, modelo `Grado`: nombre, nivel académico opcional, orden, activo):
  catálogo editable en Configuración académica → Grados (`GET /cursos/grados` con 145 o 99;
  `POST`/`PUT /cursos/grados[/{id}]` con 99). No se borran: se desactivan. La migración
  `2026_11_06_120000_create_academico_grado_table` precargó Párvulos … Undécimo y asignó a cada
  curso el grado cuya palabra encabeza su nombre; los demás quedaron sin grado.
- **`curso.id_grado`** (nullable, sin FK: curso es MyISAM en algunas BD). Se edita en el modal.
  **El nivel del curso sale del grado**: si el grado tiene `id_nivel_academico`,
  `CursoRequest::prepareForValidation` lo pone como `id_nivel` (manda sobre el que se envíe); solo
  un curso sin grado, o con un grado de todos los niveles, necesita `id_nivel` explícito. Al
  crear sin `id_grado`, `CursosServices::crear` propone `Grado::paraNombreDeCurso` (el grado activo
  cuyo nombre encabeza el del curso: "Primero A" → Primero; renombrar un grado cambia qué cursos
  nuevos lo reciben). Al actualizar sin `id_grado` se conserva: renombrar un curso no lo mueve de
  grado. `ordenarPorGrado` ordena por nivel y luego por `academico_grado.orden` (la consulta debe
  traer `id_grado`); los cursos del mismo grado comparten referentes en Notas.

- `GET /cursos/all` sigue abierto (selects de otros módulos). Trae `id_nivel` y `nivel`
  (`{id, nombre}`) y sale en orden académico (`Cursos::ordenarPorGrado`): el frontend agrupa
  los selects por nivel. Todo lo demás pide la 145:
  listado paginado (`?s=&estado=activo|inactivo`), `GET`/`PUT /{id}`, `POST /`,
  `GET /{id}/estudiantes`, `GET /estudiantes-buscar`, `POST /{id}/estudiantes`
  (`{ estudiantes: [id_user] }`) y `DELETE /{id}/estudiantes/{idEstudiante}`.
- Un estudiante (perfil 16) tiene un solo curso: `usuarios.id_curso`. Asignar a un curso
  **mueve** al estudiante si ya tenía otro; quitar lo deja en `NULL`. Las actas y
  observaciones guardan su propia foto del curso, así que no se reescriben.
- No hay borrado de cursos (las actas tienen FK `restrictOnDelete`): se desactivan con
  `activo = 0`, y a un curso inactivo no se le asignan estudiantes.
- `curso_proximo` legacy usa `0` como "sin curso próximo"; al guardar se manda `null`.
- `POST /{id}/estudiantes/nuevo` crea el usuario con las reglas de `RegistrarUsuarioRequest`
  (`CrearEstudianteRequest`): perfil 16, el curso de la ruta, `user` = documento y `id_nivel`
  sacado del nivel académico del curso (Básica secundaria no tiene fila en `nivel`: va a
  "Secundaria"). Un curso sin nivel no admite estudiantes nuevos.
- Promoción: `GET /promocion` (vista previa), `GET /promocion/estudiantes?cursos[]=` (los que
  pasarían, para revisarlos) y `POST /promocion` (`{ cursos: [id], excluir: [id_user] }`; los
  excluidos se quedan en su curso).
  La vista previa omite los cursos sin nivel ("Egresado", "Estudiantes Graduados"): no son grados que se promuevan. Es un
  solo `UPDATE ... JOIN curso` para que Quinto→Sexto no se encadene con Sexto→Séptimo; solo
  mueve estudiantes activos de cursos activos cuyo curso próximo existe y está activo. No hay
  deshacer: cada promoción deja en el log `promocion de cursos` con `id_user`, `de` y `a`.
- `usuarios.curso_proximo_user` es legacy y está desactualizado (no coincide con
  `curso.curso_proximo` en la mayoría): la promoción no lo usa.
- Cierre de año (`AnioEscolarServices::estadoCierreAnio`): un año termina con el fin de su
  último `periodo_academico` (sin periodos, con el fin de su rango A/B). La ventana va desde ahí
  hasta que empieza el primer periodo del año siguiente. `GET /cursos/avisos-cierre` (145 o 99)
  dice en esa ventana si falta promover (`cursos_promociones` sin fila para el año cerrado) y si
  el año siguiente no tiene periodos académicos; fuera de ella devuelve `data: null`.
- Cada promoción guarda una fila en `cursos_promociones` con el año que cerró (el terminado si
  hay ventana; si se promueve antes, el vigente). Si esa escritura falla solo se registra en el
  log: los estudiantes ya se movieron.
- Director de grupo: `curso.id_director` (opcional, sin FK) apunta a un docente activo
  (`Permiso::PERFILES_DOCENTE`). Un docente dirige un solo curso: si se le asigna a otro, se
  reasigna (`CursosServices::reasignarDirector` deja el anterior sin director y lo avisa) y
  `GET /cursos/docentes` (145) trae cada docente con el `curso_dirigido` para el select.
- **"Los cursos del docente" tienen una sola fuente:** `CargaAcademicaService::obtenerCursosDocente()`
  (carga activa por asignatura o suelta, más el curso activo que dirige). La usan Observaciones,
  Actas (138), Métricas de asistencia (`metricasPorCurso`) y Mis llegadas tarde. No vuelvas a
  escribir esa consulta en otro lado: las copias ya divergieron una vez (la de llegadas tarde
  ignoraba la carga suelta). Prueba: `tests/Unit/GestionAcademica/CursosDelDocenteTest.php`.
- Director de grupo sin opciones: en Observaciones ve también las de todos los profesores a
  los estudiantes que hoy están en su curso (`AccesoObservaciones::$idsCursosDirigidos`; editar
  sigue siendo solo de quien la registró) y en Actas, las de esos estudiantes.
- **Plan (sin implementar):** funciones reales del director de grupo, con los puntos de
  integración del backend, en `docs/plan-director-de-grupo.md` del frontend.
- Director de grupo, contacto de acudientes: `GET /vinculacion-acudientes/estudiante/{id}/acudientes`
  deja pasar a los perfiles 3/14; sin la 131 solo responde si el estudiante está hoy en el curso
  que dirige, y sin el documento del acudiente.
- "Mi curso" (`GET /cursos/mi-curso`, `PUT /cursos/mi-curso/repiten`, perfiles 3/14): el
  director sugiere quién repite; queda en `promocion_sugerencias` (una por estudiante y año) y la
  promoción la ve como `sugerido_repetir`. No obliga: promover sigue siendo de la 145. Cada
  estudiante trae además `foto_carnet`, `carnet_estado` (último carnet) y `solicitud_carnet`
  (tipo de la pendiente).
- Director de grupo, carnets (opción 146): `PUT /cursos/mi-curso/carnets/entregar` entrega con
  `CarnetService::entregar` solo los carnets de los estudiantes de su curso. Ver `carnets.md`.
- Aviso de actas: al registrar actas, `ActasEstudianteService::avisarDirectores` manda un correo
  al director del curso actual de cada estudiante (después de responder; si falla, solo log).
