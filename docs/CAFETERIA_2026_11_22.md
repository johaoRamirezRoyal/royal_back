# Módulo de cafetería: modelo, etapa 1 (estructura y personal) y frontend (2026-11-21 al 22)

Cambio en `royal_back` y en el frontend (`frontend-New_S.A.M.I`), los dos en la rama
`feat/cafeteria`. Las reglas vigentes están en
[`contexto/modulos/cafeteria.md`](contexto/modulos/cafeteria.md); del lado del frontend, en su
`docs/contexto/modulos/cafeteria.md` (arquitectura por secciones, navegación y splashes). Este
documento explica de dónde salió el módulo, qué se decidió, qué se construyó, cómo desplegarlo y
qué falta.

## Por qué

- La institución necesita administrar sus cafeterías: estructura, personal, catálogo,
  abastecimiento, inventario y presupuesto. Más adelante vendrán ventas, caja y la billetera de
  estudiantes con recargas de los acudientes.
- Maneja dinero y datos delicados, así que es un **módulo aislado**:
  - En el frontend tiene layout y navegación propios, fuera del panel general (`AppLayout`) y
    del portal para padres.
  - En el backend tiene tablas `caf_*` y sus propios permisos (módulo 16).
- Se construye **por etapas** a partir del modelo relacional entregado por el equipo
  (`modelo_relacional_cafeteria_omnia.md` v1.0). La primera etapa del modelo es la administración
  institucional; dentro de ella, este cambio implementa la **etapa 1: estructura y personal**.

## Validación del modelo contra el esquema real

El modelo pedía validarlo antes de escribir código (§8). Se revisó `royal_back` y la base local
`sami_royal` con consultas de solo lectura a `information_schema`.

| Punto del modelo | Lo que hay en OMNIA | Consecuencia |
|---|---|---|
| FKs `bigint` a `usuarios` | `usuarios.id_user` es **`int(11)` con signo**, InnoDB | Columnas de usuario como `integer`, no `foreignId()` |
| FK a `usuarios` | Las tablas nuevas del repo no llevan FK a `usuarios` (legacy) | `integer` + índice; el service valida existencia, estado y tipo |
| `perfiles.tipo` / `TipoPerfil` | Existen | Se valida con `Perfil::idsDeTipo()` |
| Tabla de sedes (`id_sede`) | **No existe** ninguna tabla de sedes | Se omite `id_sede` (decisión C2) |
| `proveedores` + `proveedor_usuarios` | **Ya hay un maestro**: un proveedor es un `usuario` PROVEEDOR con `proveedor_detalle`, `proveedor_contactos`, `proveedor_banco` y `proveedor_documento` (`id_proveedor = id_user`), administrado por Compras (opción 61) | No se crean esas tablas (C1) |
| Solicitudes y órdenes de compra | Compras general (`solicitudes_inicial` → `solicitudes`, estados numéricos) no tiene presupuesto, consolidación, recepción parcial ni lotes | Tablas propias (C3) |
| Inventario | El módulo Inventario es de **activos fijos**, no de consumibles por lote | Tablas nuevas, con prefijo para no confundirlas (C4) |
| Choques de nombres | Ninguna de las tablas del modelo existe | Sin choque físico, sí semántico (C4) |
| Motores | `usuarios`, `perfiles`, `proveedor_detalle`, `cron_*`, `estudiantes_padres` y `dias_semana` son InnoDB | Nada de la cafetería toca tablas MyISAM |
| `dia_semana tinyint` | Ya existe el catálogo `dias_semana` (1 = lunes … 7 = domingo), con FK en los horarios académicos | Los horarios usan `id_dia_semana` con FK |
| Permisos | `cron_modulos` llega hasta el 15 y `cron_opciones` hasta el 175 en `sami_royal` | Módulo 16 y opciones 176+ con id explícito |
| Comandos programados | Solo corren en la conexión por defecto | La etapa 1 no tiene ninguno; los que vengan deben recorrer las bases |

`sami_hebreo` y `sami_play` no existen en local: hay que repetir estas comprobaciones en cada
base antes de migrar (ver Despliegue).

## Decisiones

| # | Tema | Decisión | Estado |
|---|---|---|---|
| C1 | Proveedores | Reusar el maestro de Compras; solo se creará `caf_proveedor_productos` (etapa 2). En OMNIA un proveedor tiene una sola cuenta; sus contactos están en `proveedor_contactos` | Decidida |
| C2 | Sedes | Omitir `id_sede` hasta que exista una tabla de sedes | Decidida |
| C3 | Compras de cafetería | Flujo propio (`caf_solicitudes_compra`…); Compras general no encaja | Recomendada |
| C4 | Nombres | Prefijo **`caf_`** en todas las tablas | Decidida |
| C5 | FK a `usuarios` | Sin FK, `integer` + índice | Aplicada en la etapa 1 |
| C6 | Quién aprueba compras | El modelo no lo define. Propuesta: un paso, aprobador = quien tenga la opción de aprobar; umbrales por monto después | **Abierta: decidir antes de la etapa 4** |
| C7 | Granularidad de permisos | Una opción por capacidad (separar pedir de aprobar, operar de ver) | Recomendada |
| C8 | Rama del backend | `feat/cafeteria` (el `AGENTS.md` dice «siempre en `main`»: se acordó una rama aparte) | Decidida |
| C9 | Inconsistencias del modelo | Usar `aprobado_at` (no `approved_at`); `subtotal` se calcula y se guarda como foto al aprobar | Recomendada |
| C10 | Unicidad con NULL | Columnas generadas en los UNIQUE, más validación transaccional | Aplicada en la etapa 1 (almacén predeterminado) |
| — | Códigos (`codigo`) | **Los asigna el sistema** al crear y no cambian (pedido del 2026-11-22) | Aplicada |
| — | Asignaciones vencidas | **Por fecha, sin comando programado** (pedido del 2026-11-22) | Aplicada |

## Qué cambió en el backend

### Etapa 1: estructura (176) y personal (177)

- **Migraciones** (`2026_11_22_1000xx`): `caf_cafeterias`, `caf_puntos_venta`,
  `caf_terminales_venta`, `caf_almacenes`, `caf_puntos_venta_almacenes` (columna generada
  `predeterminado_clave` + UNIQUE), `caf_roles_operativos` (siembra COORDINADOR, CAJERO,
  ALMACENISTA y COCINA), `caf_asignaciones_personal`, `caf_horarios_cafeteria` y
  `caf_horarios_punto_venta` (FK a `dias_semana`).
- **`2026_11_22_100500_seed_modulo_y_opciones_cafeteria`**: módulo 16 «Cafetería» y opciones
  176 «Cafetería — Estructura» y 177 «Cafetería — Personal».
  - Usan id explícito y fallan si el id está ocupado por otra cosa.
  - **No se otorgan**: el Super Admin las tiene implícitas y cada colegio las asigna en
    `/permisos`.
  - Sus constantes son `Permiso::CAFETERIA_ESTRUCTURA` y `Permiso::CAFETERIA_PERSONAL`.
- **Código:**
  - Enums: `app/Enums/Cafeteria/*` (estados y tipos).
  - Modelos: `app/Models/Cafeteria/*`.
  - Services: `EstructuraCafeteriaService` y `PersonalCafeteriaService`, más los traits
    `EjecutaOperacion` (transacción + `ReglaCafeteria` → 422/404) y `GeneraCodigo`.
  - Form Requests: `app/Http/Requests/Cafeteria/*`.
  - Controllers: `EstructuraCafeteriaController` y `PersonalCafeteriaController`.
  - Rutas: `routes/api/cafeteria.php`, bajo `/api/cafeteria`, con 23 endpoints.
- **Reglas principales:**
  - Una cafetería nace en borrador; su estado cambia solo por transiciones válidas y solo se
    activa con un punto de venta activo.
  - Una cafetería cerrada no se modifica.
  - Cada punto tiene exactamente un almacén predeterminado, que debe ser central o propio.
  - Las franjas de un mismo día no se cruzan.
  - Solo se asigna personal activo de tipo administrativo, docente u operativo.
  - Detalle completo en [`contexto/modulos/cafeteria.md`](contexto/modulos/cafeteria.md).

### Revisión con `laravel-reviewer` y correcciones

- **`PUT /almacenes/{id}` sin `id_cafeteria` volvía central al almacén.** Ahora conserva su
  cafetería (`AlmacenRequest` solo normaliza el campo si viene). Tiene prueba.
- **`guardarAlmacenesPunto`:** bloquea el punto (`lockForUpdate`). Antes, dos guardados a la vez
  daban un 500 en el UNIQUE.
- **Almacén predeterminado:** no se desactiva ni se bloquea mientras sea el de un punto activo.
- **Cafetería cerrada:** un almacén suyo no se modifica ni se puede sacar de ella.
- **«No existe»** responde 404 (`ReglaCafeteria::noExiste()`); antes respondía 422.

### Asignaciones vencidas por fecha

- **Vigente** = ACTIVA y hoy dentro de su vigencia. Lo calculan `scopeVigentes()` y el atributo
  `vigente`.
- Al llegar su fin, nada cambia el estado: no hay comando programado, porque esos solo corren en
  la conexión por defecto.
- **Duplicado** = otra ACTIVA de la misma persona y función con fechas que se cruzan; una vencida
  no bloquea.
- **`finalizar`:** con una fecha futura solo programa el fin. Nunca amplía el fin que ya tenía.
- **`revocar`:** en una asignación vencida conserva su fecha de fin.
- El listado de cafeterías trae `personal_vigente_count` (reemplazó a `personal_activo_count`).

### Códigos automáticos

- **Cafeterías, almacenes, puntos y terminales:** `CAF-001` y `ALM-001` son únicos en la
  institución; `PV-001` va por cafetería y `TER-001` por punto.
- **Funciones:** el código sale del nombre (`AUXILIAR_DE_CAJA`, `_2`…).
- **Edición:** los requests ya no aceptan `codigo`, y si llega se ignora al crear y al editar.
- **Choques:** el UNIQUE de la base es la garantía; si dos peticiones toman el mismo código, se
  reintenta con el siguiente.
- Las etapas siguientes deben usar el mismo trait.

### Pruebas

`tests/Feature/Cafeteria/` tiene **20 pruebas**, contra la base del `.env` dentro de una
transacción. Todas pasan. La suite completa tiene 37 fallos **previos**, idénticos sin este cambio
(comparado con `git stash`):
- falta el driver de SQLite;
- la columna `terminos` de otra rama;
- `sami_hebreo` y `sami_play` no existen en local.

## Qué cambió en el frontend

Detalle en `docs/contexto/modulos/cafeteria.md` del frontend y en su `DESIGN.md`.

- **Ruta `/cafeteria`:** fuera de `AppLayout`, con layout y navegación propios. Repite el candado
  del QR de portería. La entrada «Cafetería» del sidebar general pide la 176 o la 177.
- **Arquitectura por secciones** (`src/pages/Cafeteria/`):
  - `nucleo/`: lo del módulo (registro de secciones, rutas generadas con carga perezosa, layout,
    splashes y catálogos).
  - `compartido/`: piezas que usan varias secciones.
  - `secciones/<grupo>/<seccion>/`: cada sección se declara con un `seccion.ts`. Agregar una no
    toca el router ni el layout.
- **Etapa 1 con pantallas:** Cafeterías y su detalle (puntos, terminales, horarios y almacenes
  del punto), Almacenes, Personal y Configuración (funciones).
- **Secciones reservadas:** catálogo, abastecimiento, inventario y presupuesto. Solo las ve el
  Super Admin, como «Sección en preparación».
- **Navegación:** componente propio sobre los estilos del sidebar de OMNIA (`--sb-*`,
  `.sb-bnav`). Tiene buscador, grupos plegables y la barra inferior del celular.
- **Persiana:** al entrar, la cafetería abre (persiana cerrada mientras cargan los catálogos, que
  sube); al salir, cierra (baja diciendo qué página se deja y sube ya sobre OMNIA).
- **Probado** en escritorio y a 360px con Super Admin. `tsc` sin errores nuevos (61, los de
  siempre) y el build de producción pasa.

## Despliegue

1. **Antes de migrar, en cada base de colegio** (`mysql`, `sami_hebreo`, `sami_play`…):
   - Confirma que el módulo 16 y las opciones 176 y 177 estén libres o ya tengan esos nombres:
     `SELECT id, nombre FROM cron_modulos WHERE id = 16;` y
     `SELECT id, nombre FROM cron_opciones WHERE id IN (176, 177);`.
   - Confirma que `dias_semana` exista y sea InnoDB.
2. **Migraciones `2026_11_22_100000` … `2026_11_22_100500` en cada base:**
   `php artisan migrate --database=<connection>`.
   - Las de tablas no recorren las bases solas: se corren por base, como pide
     `contexto/permisos.md`.
   - El SQL puro está al final de `migraciones_sql.sql`.
3. **Permisos:** en cada colegio, otorga la 176 y/o la 177 en `/permisos` a los perfiles que
   administrarán la cafetería. Sin eso, solo el Super Admin la ve (fail-closed).
4. **Frontend:** se despliega con la rama `feat/cafeteria` fusionada. No requiere variables de
   entorno nuevas.

## Pendientes

- **Commit y fusión** de las dos ramas `feat/cafeteria` (aún sin commits).
- **Probar con un perfil** que tenga solo la 176 o solo la 177, para ver el menú y las rutas
  filtradas.
- **Etapas siguientes del backend:**
  - catálogo (2): categorías, unidades, conversiones, productos, precios y productos por proveedor;
  - presupuesto (3);
  - compras y recepción (4), que necesita decidir antes **C6**;
  - inventario (5).
  Cada etapa crea sus opciones (propuesta: 178 Catálogo, 179 Proveedores y productos, 180
  Solicitar compras, 181 Aprobar solicitudes y órdenes, 182 Órdenes y recepciones, 183 Ver
  inventario, 184 Transferencias y ajustes, 185 Aprobar ajustes, 186 Presupuesto) y la sección
  correspondiente del frontend con su receta.
- **Menores de la revisión, sin corregir:**
  - una cafetería ACTIVA puede quedar sin puntos activos si se desactiva el último;
  - `crearPuntoVenta`/`crearTerminal` no bloquean la cafetería frente a un cierre simultáneo
    (muy improbable);
  - el buscador de `candidatos` no escapa `%` ni `_`.
- **Vistos de paso, fuera de la cafetería:**
  - `FieldInput` no asocia su etiqueta al campo (los lectores de pantalla no leen el nombre);
  - el logo de la marca no carga en local;
  - la etiqueta «DESARROLLO» tapa «Cerrar sesión» en el pie de los menús;
  - el `design.json` de impeccable está atrasado frente a `DESIGN.md`.
- **Etapa posterior del modelo:** ventas, caja, billetera, recargas y límites de consumo de los
  acudientes. La billetera usará `estudiantes_padres` con `activo = 1`.

## Documentos de trabajo (fuera de los repos)

En la carpeta `new-sami`, junto a los repos y sin versionar:
- `contexto-omnia-para-cafeterias.md`: contexto de OMNIA preparado para la IA que diseñó el modelo.
- `cafeteria-validacion-modelo.md`: el informe de validación original. Su contenido vigente
  quedó en este documento.

El modelo relacional (`modelo_relacional_cafeteria_omnia.md`) lo entregó el equipo; conviene
guardarlo junto a este documento si se quiere versionar.
