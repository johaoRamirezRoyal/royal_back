# Proceso de compra (`/proveedores` + `/solicitudes`)

Son dos tablas paralelas, cada una con su propio `estado` (los números se parecen pero no
significan lo mismo):
- `solicitudes_inicial` (`SolicitudInicial`): la pide el empleado y la decide su
  coordinador.
- `solicitudes` (`Solicitud`): la gestiona Compras hasta cerrarla.

**Opciones** (módulo 9):
- 59 Cotizaciones.
- 60 Listado de solicitudes (legacy: ver, verificar, aplazar, rechazar y agregar al
  inventario).
- 61 Proveedores.
- 104 Gestión de compras.
- 105 Ventas (verificar entrega).
- 114 Generar solicitudes.

La bandeja de aprobación no usa opción: la gatea el perfil
(`noPuedeGestionarAprobaciones()`). El coordinador (26) solo ve su `id_nivel`; los
perfiles 1 y 2 ven todo. No confundas estas opciones con la 109/110, que son de Áreas
Comunes.

## Proveedores

Un proveedor es un `Usuario` con `perfil=17`; `proveedor_detalle`, `proveedor_documento`,
`proveedor_contactos` y `proveedor_banco` usan `id_proveedor = id_user`.

- Sin gate: `GET /proveedores`, `/select` (solo activos) y `/tipos-documento`.
- Opción 61: el detalle, el CRUD (crea el usuario con perfil 17 más su detalle), el
  estado, los documentos (FileStorage, no Cloudinary), los contactos y los bancos.
- `fecha_ingreso` no admite `'0000-00-00'` (MySQL en modo estricto): fuérzala a null.

## Estados

| `solicitudes_inicial.estado` | | `solicitudes.estado` | |
|---|---|---|---|
| 0 | pendiente | 0 | pendiente de gestión |
| 1 | aprobada | 1 | formalizada (con proveedor) |
| 2 | devuelta | 2 | cerrada (entrega conforme) |
| 3 | rechazada | 3 | devolución |
| 4 | convertida (ya tiene fila final) | 4 | disponible en stock |
| 5 | cancelada por el solicitante | | |

`solicitudes` tiene además:
- `activo`: 1 normal, 10 aplazada (con `fecha_aplazado`) y 0 rechazada (con `motivo`).
- `anulada`: 0 o 1. Oculta la solicitud del seguimiento sin borrarla.

```
inicial 0 ─ aprobar (coordinador/admin) → 1 + crea `solicitudes` en 0
            │   ├─ disponible-stock → 4
            │   └─ asignar-proveedor → 1 ─ aplazar (activo 10) | rechazar (activo 0) | anular
            │                             └─ verificar-entrega → 2 cerrada (→ agregar-inventario) | 3 devolución
            ├─ rechazar-inicial → 3
            └─ cancelar (dueño, solo en 0) → 5
```

El `verificar` legacy (60) guarda un checklist en `solicitud_verificacion_inicial` y no
crea la fila final; `asignar-proveedor` la crea si falta
(`crearSolicitudFinalDesdeInicial`).

## Endpoints

| Gate | Endpoints |
|---|---|
| Sesión | `POST /solicitudes`, `GET /mias`, `POST /{id}/cancelar` |
| Perfil (bandeja) | `GET /solicitudes`, `POST /{id}/aprobar`, `POST /{id}/rechazar-inicial` |
| 60 | `GET /{id}`, `POST /{id}/verificar`, `PUT /{id}/aplazar`, `PUT /{id}/rechazar`, `POST /{id}/agregar-inventario` |
| 104 | `GET /seguimiento` (filtros de nivel y perfil solo para 1/2), `POST /{id}/asignar-proveedor` (multipart con `cotizacion_doc`), `POST /{id}/disponible-stock`, `POST /{id}/anular` |
| 105 | `POST /{id}/verificar-entrega` (multipart con `factura_doc`) |

- Los archivos van a `solicitudes/cotizaciones` y `solicitudes/facturas`; la URL de la
  cotización sale en la respuesta como `url_cotizacion`.
- Las notificaciones por correo no bloquean: un fallo nunca impide gestionar la
  solicitud.

## Agregar al inventario (`POST /solicitudes/{id}/agregar-inventario`)

El inventario cuenta **por filas**: una unidad es una fila; no hay columna `cantidad`.

- Lo ya ingresado se cuenta por `inventario.id_compra` y
  `inventario.detalles = "<id_producto>-<descripcion>-<id_compra>"`. Las filas antiguas
  tienen solo `"<id_producto>"`. **Para comparar, usa `Inventario::detalleEsDeProducto`**
  (en SQL: `detalles = id OR detalles LIKE 'id-%'`). Comparar contra el id solo deja de
  contar las filas nuevas y permite ingresar de más.
- Si lo ya ingresado más lo pedido supera lo solicitado, responde 422. Todo va en una
  transacción con `inventario_log`.
- Por cada artículo pide `id_producto`, `cantidad`, `id_area`, `id_usuario` e
  `id_categoria`, todos obligatorios.
- Responde `articulos_creados` y un `resumen` con solicitado, ingresado y restante.
