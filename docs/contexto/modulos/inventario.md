# Inventario

## Reportes y Visto Bueno (`InventariosController`, `InventarioServices`)

Reemplaza al legacy `vistas/modulos/reportes`. Un ítem puede tener varias filas en
`reportes`:
- La original: `id_reporte IS NULL`, con `estado` 2 (reportado) o 6 (mantenimiento).
- La de solución: `estado=3`, con `id_reporte` apuntando a la original.

- **`GET /inventario/reportes`** (`estado_solucion=pendiente|solucionado`):
  - Si llega `estado` (`/reportado` manda 2, Mantenimiento manda 6), exige
    `iv.estado = estado`, igual que el legacy. Sin `estado` usa el filtro amplio
    `iv.estado NOT IN (4,5)`. **Si reaparecen filas que no deberían, revisa que el
    frontend siga mandando `estado`.** `sin_solucion` se acepta pero no se usa.
  - Los joins y filtros van en `consultaReportes()`, compartido con las estadísticas.
    Todo filtro nuevo va ahí.
  - **Campos de la FILA**: `reporte_id`, `fechareg`, `fecha_creacion`, `reportado_por`,
    `descripcion_reporte` (`COALESCE(rp.descripcion, rp.observacion)`) y
    `solucionado_por`.
  - **Campos del ÍTEM**, conservados solo por compatibilidad: `id_reporte`,
    `fecha_reporte` y `nom_usuario`.
  - El año (`id_anio`) es `rp.id_anio` o, si falta (65% del histórico), el que contiene
    `rp.fechareg` (`rangoDeAnioEscolar`).
- **`GET /inventario/reportes/estadisticas`** usa los mismos filtros. Por defecto toma
  todos los estados de solución, pero solo reportes correctivos y preventivos. Agrega en
  PHP (medianas): `resumen`, `por_mes`, `por_categoria`, `por_area`,
  `tiempo_por_categoria`, `top_items`, `top_reportadores`, `top_resolutores` y
  `por_periodo`.
- **`PUT /inventario/reportes/solucionar`**: crea la fila de solución con el texto en
  **`descripcion`**. Los datos del legacy lo tienen en `observacion`: **toda vista de "la
  solución" lee `descripcion` y, si no hay, `observacion`.**
- **`POST /inventario/mantenimiento`** (opciones 12, 108 y 109):
  - Admite cualquier ítem que no esté descontinuado (5) y que no tenga ya un preventivo
    **abierto** en `reportes` (mismo criterio que `mantenimiento_pendiente`). No se mira
    `inventario.estado = 6`.
  - Un ítem **reportado** (estado 2) sí admite un preventivo y **conserva el estado 2**, para
    que su daño siga en la bandeja de reportados. El resto pasa a 6.
- **Visto bueno**:
  - `GET /reportes/visto-bueno` (`estado=3 AND visto_bueno=0`, con `search` y
    `per_page`).
  - `PUT /reportes/{id}/visto-bueno` (una fila) y `PUT /reportes/visto-bueno` (todas).
  - El legacy también tocaba `reportes_zonas`; si ese módulo se migra, replica el cambio
    ahí.
- **Firmas en los PDFs**:
  - Las de los usuarios salen de `descargarFirma`: la `url` de su firma activa, firmada con
    `ArchivosPrivados::firmar()` porque `usuarios/firmas` es privada. Sin `url` (firma legacy
    con solo `nombre`), el PDF sale con la línea en blanco.
  - El V°B° del reporte operativo usa la firma fija `royal/royal-school/firma_administrativa.jpg`
    (`rutaLocalRoyalSchool`), también privada.

## Listados

- `GET /inventario/listado` y `GET /inventario/listado-consolidado` (el que usa
  `/inventario/listado`) ordenan por `inventario.descripcion` cuando no hay un sort
  explícito.
- **Filtro `id_bloque_area[]`** (consolidado y su `/conteo`): ítems cuya área pertenece
  al bloque o que están asignados directo al bloque, en AND con los demás filtros. **No
  es `id_bloque[]`**: ese mira solo `inventario.id_bloque` y se suma con OR a `id_area`
  (lo usa Áreas Comunes). No lo cambies.
- **Fecha de un reporte de daño**: `POST /inventario/reportar` acepta `fechareg` (no futura)
  solo con la opción 140 "Fecha de Reporte" (`Permiso::INVENTARIO_FECHA_REPORTE`); si no,
  403. Se guarda en `reportes.fechareg` con la hora actual; sin el campo, la fecha es ahora.

## Áreas Comunes (`/inventario/areas-comunes`, `routes/api/bloques.php`)

`BloquesController` + `AreasComunesController`. Organiza el inventario en Bloques → Áreas
→ ítems con `categoria.tipo_categoria = 3`. Reutiliza `InventarioServices` para
reportar, solucionar y mover. `obtenerListadoConsolidado` y `conteoPorEstado` ya no
excluyen el tipo 3 cuando no llega `tipo_categoria`: `/inventario/listado` también lo muestra.

| Opción | Perfiles | Alcance |
|---|---|---|
| 108 Administrador | 1, 2 | todo el módulo |
| 109 Uso | 11, 26 | todo el módulo, pero `obtenerTodosLosBloques` lo limita a su `id_nivel` si no tiene la 108 |
| 119 Mis áreas comunes | 11, 26 | solo `/mis-bloques` (los bloques donde es responsable en `bloque_usuario`) y, de ellos, solo lo asignado directo al bloque. Con solo la 119 (sin 12/17/108/109), `listadoConsolidado` limita los filtros en el servidor (`InventariosController::limitarAMisBloques`) |
| 149 Mis áreas comunes — Ver áreas del bloque | ninguno | complemento de la 119 y de la 141: suma las áreas de sus bloques y su inventario (sin ella, solo lo asignado directo al bloque) |
| 141 Mis áreas — de mi nivel | ninguno (se asigna en `/permisos`) | `/inventario/mis-areas` del frontend: ítems tipo 3 asignados directo a los bloques activos de su `id_nivel`; los de sus áreas solo con la 149. `misAreas` devuelve además `bloques` (catálogo del filtro "Bloque") y acepta `?id_bloque=` |
| 142 Mis áreas — de mis áreas a cargo | ninguno | lo mismo, pero de las áreas donde tiene ítems con `inventario.id_user` = él |

- Exigen la **108**: crear, editar y desactivar bloques, asignar responsables, asignar
  áreas, reclasificar y mover.
- Aceptan la **108 o la 109**: listar, registrar un check e historial/indicador de
  checks.
- **141/142** solo usan `GET /bloques/inventario/mis-areas` (`AreasComunesController::misAreas`,
  que arma el alcance en el servidor; con las dos, la unión), `POST /inventario/reportar`,
  `GET /inventario/{id}/historial` y `GET /categorias`. Solo áreas activas (`areas.activo = 1`):
  el filtro `solo_areas_activas` del consolidado saca también lo que entra por `id_bloque`
  estando en un área inactiva. Reportar no valida que el ítem
  esté dentro del alcance (igual que con 16/109/119).
- El módulo 6 "Zonas" se reusa solo como agrupador; sus tablas legacy no se tocan.

**Trampas**
- **Check semestral**: dentro del lote se omite un ítem con reporte pendiente (`estado`
  2/6 sin solución) o que ya tiene un check en el mismo `id_anio` **y** `periodo`.
- El **bloque de un ítem** puede venir de su área: un ítem reclasificado solo tiene
  `id_area`. Toda consulta que filtre por bloque debe caer al `id_bloque` del área (como
  `historialChecks`/`indicadorChecks`).
- Un área pertenece a un solo bloque: `asignarAreasBloque` responde 400 en vez de
  moverla.
- `reclasificar` solo cambia `id_categoria`; `mover` cambia la ubicación.
- **Correo** (`notificarAreaComun($entradas, $movimiento, $idActor)`):
  - Se envía al reportar un daño, programar un preventivo o registrar un check, y es
    adicional al correo genérico.
  - Lo reciben los responsables del bloque, el responsable del ítem (`inventario.id_user`,
    que **no** es necesariamente el mismo) y `DIRECCION_ADMINISTRATIVA`.
  - Ignora en silencio los ítems que no son de Área Común, así que se le puede pasar la
    misma lista de ítems.
  - Un fallo se registra en el log y no interrumpe la operación.

## Programación de mantenimientos (`ProgramacionMantController`, `ProgramacionMantServices`)

- Rutas `inventario/mantenimiento/programacion` (opción `Permiso::INVENTARIO`). Recrea
  `/mantenimientos/programacion` del legacy sobre sus mismas tablas:
  - `programacion_mant`: `estado` 0 Pendiente, 1 Terminado, 2 Completado parcial, 3 Aplazado,
    4 Cancelado; `tipo` 1 Sistemas, 2 Operativos; `periodo` = `periodos.id` (antes ordinal
    1/2, migrado en `2026_10_30_120000`; los años sin periodos registrados quedaron NULL);
    `fecha_fin` = último día de la franja (NULL = un solo día; `2026_10_30_130000`). Los
    filtros `desde`/`hasta` traen las programaciones que se cruzan con ese rango.
  - `programacion_apro`: avances (estado, observación, `archivo`). `archivo` guarda la ruta
    de FileStorage; los del legacy son solo el nombre y resuelven con el mismo `url()`.
- `anio` ('2025/2026') y `mes` ('Enero') siguen siendo texto por compatibilidad con el
  legacy: se derivan siempre del periodo y de `fecha_registro`, nunca del body. El filtro
  `id_anio` se traduce a ese texto.
- Cumplimiento = (Terminado + Completado parcial) / total, igual que el legacy.
- `GET .../calendario` (cronograma) devuelve solo programaciones, sin paginar.
- `programarMantenimientoPreventivo` crea una programación por lote (misma descripción,
  periodo y usuario; `fecha_registro`/`fecha_fin` = primer y último día realmente asignado
  a los equipos, no el rango elegido, porque cada equipo cae en un día aleatorio; `tipo` según la categoría del primer ítem, Áreas
  Comunes cuenta como Operativos; estado 1 si se generó con solución, si no 0).

## Trabajo en alturas (`CursosAlturaController`, `routes/api/cursosAltura.php`)

Gestión Humana registra los cursos de altura de los operativos en `cursos_altura`
(certificado vía `FileStorageService`, `fecha_expedicion` y `fecha_finalizacion`
opcional). Para el CRUD se usa la opción **143**.


- **Operativos**: los usuarios activos con perfil en
  `PermisosLicenciasServices::PERFILES_ASIGNABLES` (10, 23, 27, 32).
- **Curso vigente**: `CursoAltura::scopeVigentes($fecha)`. Exige `activo = 1` y
  `fecha_expedicion <= fecha`, y además `fecha_finalizacion` NULL o `>= fecha` (hoy si no
  se da la fecha).
- **Trabajos en altura** (`trabajos_altura` 1 a 1 con el reporte padre, con `fecha_trabajo`
  propia, + `trabajos_altura_operativos`; modelo `TrabajoAltura`): un reporte o mantenimiento
  puede tener **varios** operativos. Se crean con `CursosAlturaServices::asignarTrabajo()`,
  dentro de la transacción de:
  - reportar un daño (`POST /inventario/reportar`);
  - programar un preventivo (`POST /inventario/mantenimiento`).

  Los dos reciben `id_operativos` (arreglo) y lo validan con `CursoAltura::reglasOperativos()`:
  si alguno no tiene curso vigente hoy, responde 422 con su nombre. `consultaReportes`
  devuelve `id_trabajo_altura` y `nom_operativo` (nombres separados por coma).
- **`reportes.id_operativo`** (antes `evidencia`) quedó **sin uso**: la migración
  `2026_11_09_120000_create_trabajos_altura_tables` copió sus datos. **Pendiente**: borrar la
  columna en otra migración cuando producción ya corra este código.
- **Gestión Humana > Trabajos en altura** (opción 143): `GET /cursos-altura/trabajos` (paginado,
  `s` busca por ítem, área o trabajador) y `PUT /cursos-altura/trabajos/{id}`
  (`fecha_trabajo` + `id_operativos`). Al editar, el curso debe cubrir la **fecha del
  trabajo**, no hoy. Con `id_operativos` vacío se borra el trabajo (deja de ser en altura).
  Cambiar `fecha_trabajo` no toca las fechas de `reportes`.
- `GET /cursos-altura/operativos-vigentes?fecha=` y `GET /cursos-altura/permiso/{idReporte}/pdf`
  aceptan las opciones de Inventario, Áreas Comunes y Cursos de altura (12, 108, 109, 119,
  141, 142, 143).
- **El PDF** lo genera `PermisoAlturasPdfService` y sigue el formato de la Resolución
  4272/2021: datos del trabajo (con `fecha_trabajo`), una fila por trabajador con el curso que
  cubre esa fecha (o el más reciente), lista de verificación SÍ/NO, observaciones y una firma
  por trabajador más coordinador y quien autoriza. Lo demás se llena a mano en sitio. Si el
  reporte no tiene trabajo en altura, el endpoint responde 404.

## Carta de entrega (`/inventario/listado`)

Botón "Carta de entrega": `GET /api/inventario/listado-consolidado/carta-entrega/pdf` (permiso INVENTARIO) con los mismos filtros del listado (`buildConsolidadoParams`). Recreación de `cartaEntregaOficial.php` (no de `cartaEntrega.php`, que usaba tabla temporal): una fila por grupo (usuario, área, descripción) con cantidad; marca/observación del ítem más reciente del grupo. Backend: `InventarioServices::generarCartaEntregaPdf` + `CartaEntregaPdfService`. Nombre de institución: marca del dominio del usuario, si no "Royal School" (no se usa `super_empresa`).

## Indicadores de gestión (`/indicadores-gestion`)

`GET /api/indicadores-gestion` pagina en el servidor (DataTable remoto: `page`, `per-page`, `s`, `sort`, `dir` + `tipo_categoria`, `id_anio`, `periodo`, `id_categoria`) y devuelve `resumen` (`total`, `promedio`, `bajo_meta`) de todos los registros filtrados, no de la página. La página arranca sin año filtrado (histórico completo); la gráfica sigue por `/grafica`.
