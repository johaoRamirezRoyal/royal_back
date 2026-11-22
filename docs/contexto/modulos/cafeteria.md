# Cafetería (`/api/cafeteria`, módulo 16)

Módulo de cafetería institucional. Se construye por etapas según el modelo relacional
`modelo_relacional_cafeteria_omnia.md` v1.0. Su validación contra el esquema real, las
decisiones, el plan de las etapas siguientes, el despliegue y los pendientes están en
[`docs/CAFETERIA_2026_11_22.md`](../../CAFETERIA_2026_11_22.md). Frontend:
`docs/contexto/modulos/cafeteria.md` en el repo del frontend (layout y navegación propios en
`/cafeteria`).

Estado (2026-11-22): **etapa 1 hecha**: estructura y personal. Siguen catálogo, presupuesto,
compras e inventario. Ventas, caja y billetera son de una etapa posterior.

## Decisiones que aplican a todo el módulo

- **Tablas con prefijo `caf_`**, InnoDB, PK `id` (bigint unsigned). Así no se confunden con
  Inventario (activos fijos) ni con Compras, que comparten la base.
- **Usuarios como `integer` + índice, sin FK** (`usuarios.id_user` es `int(11)` con signo y es
  legacy). El service valida que el usuario exista, esté activo y sea del tipo correcto.
  `creado_por`, `asignado_por` y similares salen de `$request->user()->id_user`, nunca del body.
- **Sin `id_sede`**: OMNIA no tiene tabla de sedes. Se agrega con su FK cuando exista.
- **Proveedores**: se reusa el maestro de Compras (`usuario` PROVEEDOR + `proveedor_detalle`,
  `id_proveedor = id_user`). No se crean `proveedores` ni `proveedor_usuarios`.
- **Estados y tipos son enums PHP** (`app/Enums/Cafeteria/`) y se cambian solo por endpoints con
  sus transiciones, nunca como un campo libre.
- **Reglas en el service**: los services usan `EjecutaOperacion::operacion()`, que corre la
  operación en una transacción. Una regla incumplida se lanza como `ReglaCafeteria` y sale como
  **422** con su mensaje. Si lo que falta es el registro, usa `ReglaCafeteria::noExiste()`, que
  responde **404**. No uses en esos mensajes palabras que `Controller::apiResponse`
  toma como error de red o de base («conexión», «host», «database»…): las convierte en 503/500.
- **Migraciones**: `php artisan migrate` en **cada** base de colegio
  (`--database=sami_hebreo`, …), y su SQL en `migraciones_sql.sql`.

## Etapa 1: estructura (opción 176) y personal (opción 177)

Tablas: `caf_cafeterias`, `caf_puntos_venta`, `caf_terminales_venta`, `caf_almacenes`,
`caf_puntos_venta_almacenes`, `caf_roles_operativos` (con 4 funciones sembradas),
`caf_asignaciones_personal`, `caf_horarios_cafeteria` y `caf_horarios_punto_venta`.

Código: `EstructuraCafeteriaService` + `EstructuraCafeteriaController` y
`PersonalCafeteriaService` + `PersonalCafeteriaController`. Rutas en `routes/api/cafeteria.php`.

### Reglas

- **Cafetería**:
  - Nace en `BORRADOR`.
  - Transiciones (`EstadoCafeteria::siguientes()`): BORRADOR → ACTIVA o CERRADA;
    ACTIVA → SUSPENDIDA o CERRADA; SUSPENDIDA → ACTIVA o CERRADA. CERRADA es final.
  - Para pasar a ACTIVA necesita al menos un punto de venta ACTIVO.
  - Una cerrada no se edita ni recibe puntos, terminales, horarios, almacenes ni personal.
- **Códigos: los asigna el sistema al crear y no cambian** (decisión del 2026-11-22). Los
  requests no aceptan `codigo`; si llega, se ignora al crear y al editar (`sinCodigo`).
  - Consecutivos con prefijo (`GeneraCodigo::crearConConsecutivo`): cafetería `CAF-001` y
    almacén `ALM-001` en toda la institución; punto `PV-001` dentro de su cafetería; terminal
    `TER-001` dentro de su punto. El siguiente es el mayor `PREFIJO-NNN` existente + 1, así que un
    código que no siga el patrón no cuenta.
  - Función (rol operativo): sale del nombre (`crearConCodigoDeNombre`): mayúsculas, sin tildes,
    palabras con guion bajo (`AUXILIAR_DE_CAJA`). Se recorta a 27 caracteres para dejar sitio al
    sufijo y, si ya existe, se le agrega `_2`, `_3`… Es el mismo estilo de las sembradas
    (`CAJERO`).
  - La garantía es el UNIQUE de la base: si dos peticiones toman el mismo código a la vez, la
    segunda reintenta con el siguiente (hasta 3 veces).
  - `identificador_dispositivo` de la terminal sí lo escribe el usuario: es único y opcional.
  - Al agregar una entidad con código en etapas siguientes (productos, solicitudes, órdenes…),
    usa el mismo trait.
- **Almacenes de un punto** (`PUT puntos-venta/{id}/almacenes`):
  - Reemplaza la lista completa.
  - Con al menos un almacén, exactamente uno es el predeterminado. La base también lo
    garantiza: `predeterminado_clave` es una columna generada con UNIQUE.
  - Cada almacén debe estar ACTIVO y ser central (`id_cafeteria` NULL) o de la misma cafetería
    del punto.
  - Se bloquea el punto (`lockForUpdate`): dos guardados a la vez se esperan en vez de chocar en
    el UNIQUE, que daría un 500.
- **Almacén** (`PUT /almacenes/{id}`):
  - Sin `id_cafeteria` en el cuerpo, conserva la que tiene; con `id_cafeteria` vacío pasa a
    central. `AlmacenRequest` solo normaliza el campo si viene.
  - No cambia de cafetería mientras abastezca puntos de otra.
  - Si es de una cafetería cerrada, no se modifica ni se saca de ella.
  - No pasa a INACTIVO ni a BLOQUEADO mientras sea el predeterminado de un punto ACTIVO.
- **Horarios**:
  - `PUT …/horarios` reemplaza todas las franjas.
  - El día es `dias_semana.id`.
  - La hora de cierre debe ser mayor que la de apertura (`H:i`).
  - Un día puede tener varias franjas, pero no pueden cruzarse.
  - No se valida que el horario del punto quepa en el de su cafetería.
- **Personal**:
  - Solo se asignan usuarios activos de tipo ADMINISTRATIVO, DOCENTE u OPERATIVO
    (`PersonalCafeteriaService::TIPOS_PERSONAL`).
  - La función tiene que estar activa.
  - **Vigencia por fecha, sin comando programado** (decisión del 2026-11-22). Una asignación
    es *vigente* si está ACTIVA y hoy cae entre `fecha_inicio` y `fecha_fin` (sin fin = abierta).
    - Al llegar su fin, nada la cambia de estado: queda ACTIVA pero vencida. Se descartó un
      comando que las cierre porque los programados solo corren en la conexión por defecto.
    - `AsignacionPersonal::scopeVigentes()` y el atributo `vigente` (va en el JSON) lo calculan.
      Úsalos siempre que la pregunta sea «¿trabaja hoy aquí?», nunca `estado = ACTIVA` a secas.
    - `personal_vigente_count` del listado de cafeterías cuenta solo las vigentes.
  - **Duplicado = fechas que se cruzan**: no puede haber dos ACTIVAS de la misma persona y
    función en la misma cafetería con vigencias que se crucen. Una vencida no bloquea una nueva
    que empieza después; con otra función, siempre se puede.
  - `finalizar` (solo sobre una ACTIVA):
    - Sin fecha, termina hoy, o en su fin si ya venció.
    - Nunca antes del inicio, ni después del fin que ya tenía: para ampliarla, otra asignación.
    - Con una fecha futura solo **programa el fin**: sigue ACTIVA y vigente hasta ese día.
    - Con hoy o una fecha pasada queda FINALIZADA.
  - `revocar` (solo sobre una ACTIVA) deja el fin en hoy, pero nunca antes del inicio (si aún
    no empezaba) ni después del fin (si ya venció).
  - **La función no da permisos de la API**: para entrar a la cafetería hace falta la opción.
- **Tipos**: `TipoPuntoVenta` (MOSTRADOR, AUTOSERVICIO, KIOSCO, MOVIL) y `TipoAlmacen`
  (GENERAL, REFRIGERADO, CONGELADO, COCINA). El modelo los dejaba libres; aquí son un catálogo
  cerrado. Para agregar uno, se suma un caso al enum.

### Endpoints (`/api/cafeteria`)

| Método y ruta | Opción | Qué hace |
|---|---|---|
| `GET /catalogos` | 176 o 177 | Cafeterías, funciones, `dias_semana`, estados y tipos |
| `GET/POST /cafeterias`, `GET/PUT /cafeterias/{id}` | 176 | Listar (con conteos), crear, ver el detalle (puntos, terminales, horarios, almacenes) y editar |
| `PUT /cafeterias/{id}/estado` `{estado}` | 176 | Cambiar el estado según las transiciones |
| `PUT /cafeterias/{id}/horarios`, `PUT /puntos-venta/{id}/horarios` | 176 | `{horarios: [{id_dia_semana, hora_apertura, hora_cierre}]}` |
| `POST /cafeterias/{id}/puntos-venta`, `PUT /puntos-venta/{id}` | 176 | Crear y editar puntos |
| `PUT /puntos-venta/{id}/almacenes` | 176 | `{almacenes: [{id_almacen, es_predeterminado}]}` |
| `POST /puntos-venta/{id}/terminales`, `PUT /terminales/{id}` | 176 | Crear y editar terminales |
| `GET/POST /almacenes`, `PUT /almacenes/{id}` | 176 | `?id_cafeteria={id\|central}` |
| `POST /roles-operativos`, `PUT /roles-operativos/{id}` | 176 | Funciones del personal (la lista sale en `/catalogos`) |
| `GET/POST /personal` | 177 | Listar (`?id_cafeteria=&estado=&id_user=`) y asignar |
| `GET /personal/candidatos?s=` | 177 | Personal activo que se puede asignar (máx. 20) |
| `PUT /personal/{id}/finalizar` `{fecha_fin?}`, `PUT /personal/{id}/revocar` | 177 | Terminar una asignación |

### Pruebas

`tests/Feature/Cafeteria/` (20 pruebas) corre contra la base del `.env` dentro de una
transacción y se salta si faltan las migraciones. **Un usuario autenticado por prueba**: el guard
JWT conserva al primer usuario que se autentica en la prueba, aunque cambie el token; por eso
la prueba de acceso y la del Super Admin van separadas.

## Pendientes

- Repetir en `sami_hebreo` y `sami_play` las comprobaciones de la validación (ids 16/176/177
  libres, `dias_semana` InnoDB) y migrar ahí.
- El frontend ya consume la etapa 1 (rama `feat/cafeteria` del frontend; ver su
  `docs/contexto/modulos/cafeteria.md`), verificado en el navegador con Super Admin.
- Revisión del 2026-11-22, menores sin corregir:
  - Una cafetería ACTIVA puede quedar sin puntos activos si se desactiva el último.
  - `crearPuntoVenta`/`crearTerminal` no bloquean la cafetería frente a un cierre simultáneo
    (muy improbable).
  - El buscador de `candidatos` no escapa `%` ni `_`.
- Etapas siguientes: catálogo (2), presupuesto (3), compras y recepción (4), inventario (5).
  Antes de la 4 hay que decidir quién aprueba (C6 en la validación).
