# Asistencia y Hikvision

## Servicio Hikvision (`app/Services/Hikvisionattendance/hikvisionattendanceService.php`)

- Usa ISAPI con digest auth y trabaja con varias terminales: `fanOut()` opera en todas y
  `client($deviceId)` memoiza el cliente de cada una. Las capturas biométricas van a una
  terminal puntual.
- **Las terminales están en la BD, no en el `.env`** (desde 2026-10-26). Ver "Terminales"
  abajo.
- **`GET /hikvision/getList`**: cada terminal devuelve máximo 30 usuarios por página.
  - `buscarEmpleadosEnTerminales()` pide la página 0 y después el resto en paralelo
    (`Utils::settle`): bajó de ~16 s a ~4,5 s.
  - El resultado se cachea 300 s por colegio y lista de terminales, y no se guarda si
    alguna terminal falló.
  - La caché se invalida con el middleware `OlvidarEmpleadosHikvision` (toda escritura en
    `/hikvision`) y con `hikvision:wipe-all`; `?fresco=1` la salta. Una escritura que no
    pase por esas rutas debe llamar a `olvidarEmpleadosRegistrados()`.
- Enrolar tarjeta: `CaptureCardInfo` lee del lector físico y bloquea ~10–25 s. Ese
  endpoint no aparece en la lista de capacidades ISAPI del equipo.

## Terminales (`hikvision_dispositivos`, tabla de cada colegio)

Historial completo (por qué, verificación, despliegue y problemas frecuentes):
`docs/HIKVISION_TERMINALES_2026_10_26.md`.

**Cada institución tiene sus propias terminales**: la tabla vive en la base de cada colegio
(`mysql` = Royal, `sami_hebreo` = Hebreo), no en una compartida. El listado, el alta y la
edición trabajan sobre `database.default`: la base del usuario (claim `db_connection` del JWT)
o la que el Super Admin eligió en "Bases de datos". `GET /hikvision/devices` devuelve
`institucion` (`connection` y nombre de `BasesDatosService::nombre()`) para que la pantalla
diga de quién son.

- **Una terminal (host:puerto) pertenece a una sola institución**:
  `DispositivosService` rechaza con 422 registrarla si otra ya la tiene (las instituciones
  sin la tabla migrada se saltan).
- **El webhook no adivina**: si la IP de origen es de terminales de **varias**
  instituciones (detrás de una misma IP pública, con puertos distintos),
  `resolverTenantPorIp` devuelve null y la marcación se rechaza con un warning. Esas
  terminales deben avisar a `/pushNotification/{colegio}`, que es lo recomendado para
  cualquier terminal nueva.

Cada fila es una conexión completa: `nombre`, `protocolo`, `host`, `puerto`, `usuario`,
`password` (cast `encrypted` con APP_KEY, nunca se serializa: el frontend solo recibe
`tiene_password`), `principal` y `activo`. `device_id` ("host:puerto") se recalcula al
guardar y es el id que usan el frontend, el caché de empleados y los logs.

- **El servicio carga las terminales al primer uso** (`devices()`), no en el constructor:
  el controller se crea antes de que `RestrictToHikvisionDevices` elija el colegio del
  webhook. Usa solo las activas, la principal primero. La principal es la de las capturas
  sin terminal elegida (`primaryDeviceId()`).
- **El webhook acepta las IPs de todas las terminales registradas, también las inactivas**
  (`hostsDeTenant`): una terminal desactivada (en reparación) sigue reportando marcaciones
  válidas. Eliminarla sí deja de aceptarlas.
- **APP_KEY**: una contraseña cifrada en otro entorno (un dump de producción en local) no
  se puede descifrar. El servicio la trata como vacía (la terminal sale "Sin respuesta" y
  se registra un warning) en vez de fallar; hay que volver a escribirla.
- Endpoints:
  - `GET /hikvision/devices` (gate del grupo `/hikvision`): solo las activas; con
    `?todas=1`, todas.
  - `POST /hikvision/devices`, `PUT /hikvision/devices/{id}` (contraseña vacía = conservar
    la guardada) y `DELETE /hikvision/devices/{id}` (`HikvisionDispositivosController` +
    `DispositivosService`): **solo Super Admin**, en la ruta y en el controller. Hay una
    sola principal.
- **Migración desde el `.env`** (`2026_10_26_100000_add_conexion_to_hikvision_dispositivos`):
  importa las terminales del bloque `services.hikvision.legado.{connection}` (las variables
  `HIKVISION_*` de siempre) del colegio que se migra. Las filas que solo tenían nombre y no
  están en el `.env` quedan inactivas.
  - **Con la configuración en caché, corre antes `php artisan config:cache`**, o la
    migración no ve el bloque `legado` y no importa nada (pasó en local).
  - En producción se corre solo en Royal (`mysql`): la base de Hebreo no está en
    funcionamiento allá. En local ya se corrió también en `sami_hebreo` (sin terminales).
  - Cuando esté corrida en todos, borra el bloque `legado` y las variables
    `HIKVISION_HOST/PORT/PROTOCOL/USERNAME/PASSWORD/HOSTS`.
- Siguen en el `.env`, porque no son datos de una terminal: `HIKVISION_VERIFY_SSL`,
  `HIKVISION_PASSWORD_NUMERICA` y `HIKVISION_GROUP_IDS`.
- Test: `tests/Feature/Hikvision/DispositivosServiceTest.php`. Córrelo con `composer run
  test`: con la configuración en caché, `php artisan test` apunta a la BD real (el test
  ahora lo detecta y falla).

## Webhook de marcaciones (`POST /pushNotification/{tenant?}`)

`HikvisionController::testNotificationHikvision`. No usa JWT: `RestrictToHikvisionDevices`
solo deja pasar las IPs de las terminales y cambia `database.default` al colegio.

- Los eventos sin `employeeNoString` se ignoran; ese número es el `id_user`.
- Siempre se usa la hora **del servidor**, no la del dispositivo.
- **Estudiantes** (perfil 16, `registrarLlegadaTardeSiAplica`):
  - Se registra solo si pasa `configuracion_llegadas_tarde.hora_limite` (07:15 por
    defecto); a tiempo no se guarda nada.
  - Es idempotente por alumno y día. Se asume que el estudiante marca una sola vez.
  - Corre en `app()->terminating()`, después de responder a la terminal: el correo y el
    WhatsApp al acudiente la hacían esperar, y la terminal reenviaba el evento.
- **Trabajadores** (perfiles fuera de `PERFILES_EXCLUIDOS_ASISTENCIA = [16, 17, 28, 6]`,
  `AsistenciaGestionService::registrarMarcacion`), con un debounce de 15 s por usuario:
  - La primera marcación del día es la entrada y dispara el aviso de tardanza.
  - Toda marcación posterior se guarda en `asistencia_gestion_marcaciones` (no hay
    endpoint que la muestre, a propósito).
  - **La salida es la ÚLTIMA marcación** desde `hora_minima_salida`. El `UPDATE` solo
    reemplaza una salida anterior, así que un evento desordenado no la retrocede.
  - Si una marcación real reemplaza la salida automática de `asistencia:cerrar-vencidas`,
    se borra su observación (`OBSERVACION_SALIDA_AUTOMATICA`).
- Las trazas quedan en `laravel.log` con el prefijo `[hikvision-notification]`.

## `GET /asistencia-gestion`

- Filtros: `fecha`, `fecha_desde`/`fecha_hasta`, `id_nivel` (por `usuarios.id_nivel`),
  `id_usuario`, `hora_desde`/`hora_hasta` y `per_page`.
- **Solo con `fecha` exacta** devuelve `faltantes` (con `ultima_llegada`) y `resumen`.
- Cada fila trae `estado`, `puntualidad`, `es_tardanza` y `usuario.grupo`
  (`GROUP_ID_POR_PERFIL`).
- Otros endpoints: `POST /manual`, `DELETE /`, `GET /grafica*`, `GET /qr/token` y
  `POST /qr/marcar`. `POST /`, `/resumen`, `/ultimos-registros` y
  `/grafica/promedio-por-usuario` se eliminaron el 2026-10-22 por no tener consumidor;
  están en git.

## Aviso de llegada tarde de trabajadores

`notificarLlegadaTarde()` corre en `app()->terminating()`. Su configuración está en la
fila única `configuracion_asistencia`:
- `notificar_llegada_tarde` es el interruptor maestro (apagado por defecto).
- `notificar_llegada_tarde_trabajador` avisa también al propio trabajador.
- `notificar_recursos_humanos` avisa al perfil 8 (todo el colegio).
- `notificar_coordinador_nivel` avisa al perfil 26 del mismo `id_nivel`.

Los dos últimos reemplazaron un selector libre de perfiles. Hay un tope de
`MAX_DESTINATARIOS_LLEGADA_TARDE = 30`.

## QR de portería

- `GET /asistencia-gestion/qr/token` (opción 63): token `"{ventana}.{firma}"` que cambia
  cada 30 s, con HMAC sobre `app.key` y `database.default` (el QR de un colegio no sirve
  en otro). No guarda estado.
- `POST /asistencia-gestion/qr/marcar` (`{token}`, cualquier usuario con sesión,
  `throttle:asistencia`):
  - El token vale 3 ventanas (~90 s).
  - El usuario sale de la sesión y la hora de `now()`.
  - Tiene un debounce de 60 s por usuario y pasa por `registrarMarcacion` con `QR = 1`.
- `ASISTENCIA_QR_REDES` (lista de CIDR) limita las redes desde donde se puede marcar. Usa
  `$request->ip()`: detrás de un proxy, configura los proxies de confianza. Sin esa
  restricción, el QR se puede reenviar en vivo durante ~90 s.

## Escaneo de carnets (`POST /llegadas-tarde/escanear`)

- Opción 99 o 101. El código de barras CODE 128 del carnet es el **documento** del
  estudiante.
- Aplica la misma regla que el webhook y responde `a_tiempo`, `registrada` (con el total
  del periodo y `limite_alcanzado`) o `ya_registrada`. Responde 404 si no hay estudiante
  y 422 si está inactivo.
- Los correos salen en `terminating()`, así la estación no espera entre un carnet y otro.
- El registro manual `POST /llegadas-tarde` también exige la 99 o la 101 desde el
  2026-10-22; `LlegadaTardeRequest::authorize` devuelve `true`, así que el permiso lo da
  la ruta.
- WhatsApp: `docs/whatsapp-llegadas-tarde.md`.
