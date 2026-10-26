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
- Endpoints con la 99: `GET|PUT /configuracion-calendario`, `POST /anios-escolares` y
  `PUT /anios-escolares/estado`.
- `academico_asistencia_clase` y `academico_asistencia_estudiante` se usaban sin
  migración propia; ya la tienen.

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
