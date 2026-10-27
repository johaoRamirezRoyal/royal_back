# Terminales Hikvision en la base de datos — cambios del 2026-10-26

Registro de la estandarización de las terminales Hikvision: por qué, qué cambió, cómo se
verificó y cómo desplegarlo. El resumen operativo para agentes está en
`docs/contexto/modulos/asistencia-hikvision.md`; la pantalla, en
`docs/contexto/modulos/gestion-humana.md` del frontend. Este documento es el historial.

## 1. Punto de partida

Las terminales de control de acceso (DS-K1T321MFWX-B) se configuraban en el `.env` del
servidor, un bloque de variables por colegio:

```
HIKVISION_PROTOCOL / HIKVISION_HOST / HIKVISION_PORT     → la terminal "principal"
HIKVISION_USERNAME / HIKVISION_PASSWORD                  → las mismas credenciales para todas
HIKVISION_HOSTS="Nombre@host[:puerto],host2[:puerto2]"   → las adicionales
HIKVISION_HEBREO_*                                       → lo mismo para Hebreo
```

Problemas:

- **Agregar, cambiar o quitar una terminal exigía editar el `.env` del servidor** y volver a
  cachear la configuración. Nadie fuera de quien administra el servidor podía hacerlo.
- **Todas las terminales de un colegio compartían usuario y contraseña.** Una terminal con
  otra contraseña no se podía configurar.
- El formato de `HIKVISION_HOSTS` (nombre, host y puerto en un solo texto separado por comas)
  era fácil de romper con un error de tipeo y no se validaba.
- Los nombres legibles sí estaban en la BD (`hikvision_dispositivos`, solo `device_id` +
  `nombre`), pero la lista de terminales seguía viniendo del `.env`. Eran dos fuentes para lo
  mismo.
- No había forma de **desactivar** una terminal (en reparación, desconectada) sin borrarla
  del `.env`. Una terminal caída hacía esperar las operaciones en todas (reintentos y
  timeouts).

## 2. Qué cambió

**Cada terminal es una fila de `hikvision_dispositivos`, con su conexión completa, y se
administra desde la pantalla "Terminales de acceso".** El `.env` ya no se lee para las
terminales.

### 2.1 Tabla `hikvision_dispositivos` (una por institución)

| Columna | Tipo | Uso |
|---|---|---|
| `id` | int | id de la fila; el que usan editar y eliminar |
| `device_id` | varchar(100), único | `"host:puerto"`. Se recalcula al guardar. Es el id que usan el frontend (`deviceId` de las capturas), el caché de empleados y los logs |
| `nombre` | varchar(100) | nombre legible ("Garita principal") |
| `protocolo` | varchar(5) | `http` o `https` |
| `host` | varchar(100) | IP o nombre del equipo, sin `http://` ni puerto |
| `puerto` | int unsigned | 1–65535 |
| `usuario` | varchar(100) | usuario administrador del equipo (digest auth) |
| `password` | text | **cifrada** con APP_KEY (cast `encrypted`). Nunca se serializa |
| `principal` | bool | la terminal de las capturas sin terminal elegida. Una sola por institución |
| `activo` | bool | inactiva = OMNIA no la usa (sigue aceptando sus marcaciones) |
| `fechareg`, `fecha_updated` | timestamp | |

Modelo: `App\Models\Hikvision\HikvisionDispositivo`. `password` está en `$hidden` y la
respuesta solo trae `tiene_password`.

### 2.2 Cada institución tiene sus propias terminales

La tabla vive en la base de **cada** colegio (`mysql` = Royal, `sami_hebreo` = Hebreo), no en
una compartida. Todo trabaja sobre `database.default`:

- **Usuario normal**: la base de su institución, por el claim `db_connection` del JWT
  (`JwtFromCookie`).
- **Super Admin con correo autorizado**: la base que eligió en "Bases de datos"
  (`SwitchActiveConnection`).
- **Webhook de las terminales**: la base que resuelve `RestrictToHikvisionDevices` (abajo).

Reglas entre instituciones:

- **Una terminal (`host:puerto`) pertenece a una sola institución.** `DispositivosService`
  rechaza con 422 registrar una que otra institución ya tiene ("Esa dirección y puerto ya
  están registrados en otra institución (…)"). Una institución sin la tabla migrada se salta.
- **El webhook no adivina.** Si llega sin el colegio en la URL, `resolverTenantPorIp` busca la
  IP de origen en las terminales de todas las instituciones. Si coincide con terminales de
  **más de una** (varias instituciones detrás de una misma IP pública), devuelve null: la
  marcación se rechaza y queda un warning en el log. Antes se quedaba con la primera
  coincidencia y podía guardar la marcación en el colegio equivocado.
- `GET /hikvision/devices` devuelve `institucion` (`connection` + nombre de
  `BasesDatosService::nombre()`, el editado en "Bases de datos") para que la pantalla diga de
  quién son las terminales.

### 2.3 Servicio (`hikvisionattendanceService`)

- **Las terminales se cargan al primer uso** (`devices()`), no en el constructor. Laravel crea
  el controller antes de correr el middleware de la ruta: en el webhook, el constructor corría
  antes de que `RestrictToHikvisionDevices` eligiera el colegio. Toda lectura de la lista pasa
  por `devices()`.
- Solo usa las **activas**, la principal primero (`primaryDeviceId()` = la primera).
- **Cada terminal usa sus credenciales** en el cliente Guzzle (digest auth). Se eliminaron las
  propiedades `$username`/`$password` compartidas.
- **Contraseña que no se puede descifrar** (se cifró con otra APP_KEY, p. ej. un dump de
  producción en local): se trata como vacía y queda un warning en el log. La terminal sale
  "Sin respuesta" hasta que se vuelva a escribir, en vez de tumbar todo el servicio.
- **El webhook acepta las IPs de todas las terminales registradas, también las inactivas**
  (`hostsDeTenant`): una terminal desactivada en OMNIA sigue reportando marcaciones válidas.
  Eliminarla sí deja de aceptarlas.
- Se eliminaron `configParaTenant`, `parseDispositivos`, `normalizarDispositivo` y
  `guardarNombreDispositivo`.

### 2.4 API

| Método y ruta | Quién | Qué hace |
|---|---|---|
| `GET /api/hikvision/devices` | gate del grupo `/hikvision` (opción 63, Usuarios o Super Admin) | terminales **activas** de la institución + `institucion`. Lo usan los selectores de enrolamiento |
| `GET /api/hikvision/devices?todas=1` | ídem | también las inactivas (pantalla "Terminales de acceso") |
| `POST /api/hikvision/devices` | **solo Super Admin** | alta |
| `PUT /api/hikvision/devices/{id}` | **solo Super Admin** | edición; `password` vacía = conservar la guardada |
| `DELETE /api/hikvision/devices/{id}` | **solo Super Admin** | baja |

- El alta, la edición y la baja están en `HikvisionDispositivosController` +
  `DispositivosService`. El permiso va en la ruta **y** en el controller (`HasMiddleware`),
  como pide `docs/PERMISOS_MIDDLEWARE_2026_10_20.md`: guardan credenciales de equipos.
- Se eliminó `PUT /api/hikvision/devices` (solo renombraba).
- Cuerpo de alta y edición:

  ```json
  { "nombre": "Garita principal", "protocolo": "http", "host": "190.242.98.76",
    "puerto": 8002, "usuario": "admin", "password": "…", "principal": true, "activo": true }
  ```

- Validación (422 con el primer error): `host` solo letras, números, puntos y guiones (sin
  `http://` ni puerto); `puerto` entre 1 y 65535; `password` obligatoria al crear.
- Cada elemento del listado trae `idRegistro`, `id` (`"host:puerto"`), `name`, `protocolo`,
  `host`, `port`, `usuario`, `principal`, `activo`, `tiene_password` e `isConnected` (se
  prueba solo en las activas).
- Los mensajes de error dicen "dirección" y no "host": `apiResponse()` responde 503 si el
  texto menciona "host" o "conexión".

### 2.5 Frontend ("Terminales de acceso", `/gestion-humana/terminales-acceso`, solo perfil 1)

- Tarjeta por terminal: nombre, `protocolo://host:puerto · usuario`, y etiquetas Conectada /
  Sin respuesta / Inactiva, Principal y Sin contraseña.
- "Nueva terminal" y "Editar" abren `DeviceFormModal`: nombre, protocolo, dirección IP,
  puerto, usuario, contraseña (al editar, vacía = sin cambios), y los interruptores principal y
  activa. La primera terminal que se agrega queda como principal.
- "Eliminar" pide confirmación y sugiere desactivar si la terminal solo está en reparación.
- La descripción dice de qué institución son las terminales.
- `useHikvisionDevices(true)` trae todas; sin argumento, solo las activas, que es lo que usan
  los modales de enrolamiento (huella, tarjeta, rostro).
- Se eliminó `RenameDeviceModal` (el renombrado ahora es parte de la edición).

### 2.6 Lo que sigue en el `.env`

No son datos de una terminal, sino del comportamiento de todas:

- `HIKVISION_VERIFY_SSL`: verificar el certificado en `https`.
- `HIKVISION_PASSWORD_NUMERICA`: la contraseña de asistencia solo con dígitos del documento.
- `HIKVISION_GROUP_IDS`: override del mapa perfil → Person Group.

## 3. Migración desde el `.env`

`database/migrations/2026_10_26_100000_add_conexion_to_hikvision_dispositivos_table.php`:

1. Agrega las columnas de conexión (el DDL está en `migraciones_sql.sql`).
2. Las filas que ya existían (solo `device_id` + `nombre`) reciben `host` y `puerto` sacados
   del `device_id`. Quedan **inactivas** salvo que estén en el `.env`, porque hasta hoy no se
   usaban.
3. Importa las terminales del `.env` de la institución que se migra (bloque
   `services.hikvision.legado.{connection}` de `config/services.php`, que lee las variables
   `HIKVISION_*` de siempre): una fila por terminal, todas con el usuario y la contraseña
   compartidos (cifrada), conservando el nombre que ya tuvieran. La de `HIKVISION_HOST` queda
   como principal.

Es idempotente por `device_id`. Ese paso de datos no tiene SQL fijo: depende del `.env` de cada
servidor.

**Hebreo no está en funcionamiento en producción**: allí la migración se corre solo en Royal.
Se quitaron el bloque `legado.sami_hebreo` y las variables `HIKVISION_HEBREO_*`. En local se
corrió también en `sami_hebreo`, que queda sin terminales; si algún día se activa, se agregan
desde la pantalla.

## 4. Cómo se verificó

- **Local, contra las terminales reales**: la migración importó las 3 terminales del `.env`
  (`20.20.20.16`, `.13`, `.93`, puerto 80), la primera como principal. Las 4 filas que solo
  tenían nombre (copia de producción, `190.242.98.x`) quedaron inactivas.
- La contraseña descifrada coincide con la del `.env` (comparada con `hash_equals`, sin
  imprimirla), y `password` no aparece en `toArray()` ni en la respuesta.
- `devicesInfo(true)` con las credenciales de la tabla: `.16` y `.13` conectadas; `.93` sin
  respuesta, igual que con el `.env` (~23 s por los reintentos contra la terminal caída).
- `resolverTenantPorIp('190.242.98.76')` → `mysql`; una IP desconocida → null.
- **Tests** (`tests/Feature/Hikvision/DispositivosServiceTest.php`, 8 casos): contraseña
  cifrada y `device_id` calculado; alta sin contraseña y dirección repetida rechazadas; editar
  sin contraseña conserva la guardada; una sola principal; eliminar; cada institución con sus
  terminales; una terminal no puede estar en dos instituciones; el webhook no adivina si la IP
  es de dos instituciones. Las dos instituciones se simulan con bases sqlite en memoria
  separadas. Suite completa: 38/38.
- Frontend: `tsc --noEmit` con los mismos 62 errores previos que antes del cambio.

**Lección de la verificación**: con la configuración en caché, `php artisan test` usa la
config cacheada (MySQL real) en vez de sqlite en memoria. Una corrida del test insertó una fila
en la base local y le quitó la marca de principal a la real; se borró y se restauró. Los tests
se corren con **`composer run test`**, que hace `config:clear` antes, y este test ahora falla de
inmediato si no está en sqlite.

## 5. Despliegue en producción

1. Subir el código del backend y del frontend.
2. **`php artisan config:cache`** antes de migrar. Con la caché vieja, la migración no ve el
   bloque `legado` y no importa ninguna terminal (pasó en local).
3. `php artisan migrate`, **solo en Royal** (`mysql`).
4. Entrar a "Terminales de acceso" con un Super Admin y revisar que estén todas, con su nombre,
   que la principal sea la correcta y que respondan.
5. Si alguna terminal tiene otra contraseña, escribirla desde "Editar".
6. Cuando todo funcione: borrar del `.env` `HIKVISION_HOST`, `HIKVISION_PORT`,
   `HIKVISION_PROTOCOL`, `HIKVISION_USERNAME`, `HIKVISION_PASSWORD` y `HIKVISION_HOSTS`, borrar
   el bloque `legado` de `config/services.php` y volver a correr `config:cache`.

**APP_KEY**: las contraseñas se cifran con la APP_KEY de cada servidor. Si se copia la base de
producción a otro entorno, ahí las terminales salen "Sin respuesta" hasta que se escriban de
nuevo; nada se cae.

## 6. Problemas frecuentes

| Síntoma | Causa probable |
|---|---|
| "No hay terminales Hikvision activas para esta institución" al enrolar | ninguna terminal activa en esa institución, o la migración no importó nada (config en caché vieja) |
| Una terminal sale "Sin respuesta" | equipo apagado o fuera de red, dirección o puerto mal escritos, credenciales incorrectas, o contraseña cifrada con otra APP_KEY (hay un warning en `laravel.log`) |
| Las marcaciones de una terminal no llegan | la terminal no está registrada en esa institución, o comparte IP pública con otra institución y avisa a `/pushNotification` sin el colegio: configurarla para avisar a `/pushNotification/{colegio}` |
| 422 "ya están registrados en otra institución" | esa `host:puerto` ya es de otra institución; cada terminal pertenece a una sola |
| Terminales de acceso da error en una institución | esa base no tiene la migración |

## 7. Pendiente

- **Probar conexión** antes de guardar: hoy el estado se ve en la lista después de guardar.
- Borrar el bloque `legado` y las variables `HIKVISION_*` de conexión cuando la migración esté
  corrida en producción (paso 6 del despliegue).
- Configurar las terminales de producción para avisar a `/pushNotification/{colegio}` en vez
  de depender de la IP de origen.
