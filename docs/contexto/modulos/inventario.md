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
- **Visto bueno**:
  - `GET /reportes/visto-bueno` (`estado=3 AND visto_bueno=0`, con `search` y
    `per_page`).
  - `PUT /reportes/{id}/visto-bueno` (una fila) y `PUT /reportes/visto-bueno` (todas).
  - El legacy también tocaba `reportes_zonas`; si ese módulo se migra, replica el cambio
    ahí.

## Listados

- `GET /inventario/listado` y `GET /inventario/listado-consolidado` (el que usa
  `/inventario/listado`) ordenan por `inventario.descripcion` cuando no hay un sort
  explícito.
- **Filtro `id_bloque_area[]`** (consolidado y su `/conteo`): ítems cuya área pertenece
  al bloque o que están asignados directo al bloque, en AND con los demás filtros. **No
  es `id_bloque[]`**: ese mira solo `inventario.id_bloque` y se suma con OR a `id_area`
  (lo usa Áreas Comunes). No lo cambies.

## Áreas Comunes (`/inventario/areas-comunes`, `routes/api/bloques.php`)

`BloquesController` + `AreasComunesController`. Organiza el inventario en Bloques → Áreas
→ ítems con `categoria.tipo_categoria = 3`. Reutiliza `InventarioServices` para
reportar, solucionar y mover.

| Opción | Perfiles | Alcance |
|---|---|---|
| 108 Administrador | 1, 2 | todo el módulo |
| 109 Uso | 11, 26 | todo el módulo, pero `obtenerTodosLosBloques` lo limita a su `id_nivel` si no tiene la 108 |
| 119 Mis áreas comunes | 11, 26 | solo `/mis-bloques` (los bloques donde es responsable en `bloque_usuario`) |

- Exigen la **108**: crear, editar y desactivar bloques, asignar responsables, asignar
  áreas, reclasificar y mover.
- Aceptan la **108 o la 109**: listar, registrar un check e historial/indicador de
  checks.
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
