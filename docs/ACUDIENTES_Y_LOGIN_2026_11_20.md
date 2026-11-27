# Módulo Acudientes, contraseña inicial, correos institucionales y arreglos del login (2026-11-18 al 23)

Cambio en `royal_back` y en el frontend (`S.A.M.I_frontend`). Las reglas vigentes están en
[`contexto/modulos/acudientes.md`](contexto/modulos/acudientes.md) y
[`contexto/autenticacion.md`](contexto/autenticacion.md) («Dominios institucionales»); del lado
del frontend, en `docs/contexto/modulos/acudientes.md`. Este documento explica qué se cambió, por
qué y qué falta hacer en el despliegue.

## Por qué

- Para entrar al portal para padres, un acudiente necesita una cuenta ACUDIENTE vinculada a sus
  hijos. El colegio no tenía dónde crearla: «Acudientes» de Admisiones (opción 112) solo listaba
  a los que se registraban solos y «Vinculación Estudiante - Padre» (131) solo vinculaba.
- Al probar el portal con Play And Learn (`sami_play`) en producción aparecieron fallas del login
  y de los correos institucionales que no dependían del portal.

## Qué cambió

### Módulo Acudientes (opción 175)

- `/gestion-academica/acudientes` (`AcudientesController` + `AcudientesService`,
  `routes/api/acudientes.php`). Reemplaza a los dos módulos anteriores: sus rutas redirigen aquí y
  salen del menú. `AcudientesAdminController` (`/acudientes-admin`) se eliminó.
- Crear y editar (documento, nombres, apellidos opcionales, correo personal, teléfono). **La
  contraseña inicial es el número de documento** (sin puntos ni espacios); el colegio no la
  escribe en ningún formulario.
- **Enviar acceso al portal**: correo propio (`AccesoPortalPadresMail`, vista
  `emails.accesoPortalPadres`) con la marca del colegio y un enlace de 7 días (misma página y tabla que recuperar
  contraseña). Si sigue con la contraseña inicial, el correo se la recuerda y le pide cambiarla;
  si no tiene (cuentas anteriores), le pide crearla. Por fila o en lote.
- Hijos: el modal usa `/vinculacion-acudientes`, que acepta la 131 o la 175.
- Activar / desactivar, por fila o en lote.
- Migración `2026_11_18_120000_seed_opcion_acudientes`: crea la 175 y la otorga a los perfiles
  que tenían la 112 o la 131.

### Creación masiva desde Excel (2026-11-20)

- `POST /acudientes/importar`: A documento, B nombres, C apellidos, D correo, E teléfono,
  F documento del hijo. Crea al acudiente, o actualiza solo las columnas llenas si ya existe, y
  vincula al hijo. Máximo 500 filas; las filas malas se reportan sin detener las demás.
- La «Creación masiva» que un compañero había agregado en Usuarios (`4d85b9e`, pensada para
  personal: perfil, nivel, curso y correo `@royalschool.edu.co`) se quitó del frontend; el
  endpoint `/usuarios/importar` queda sin botón. También se eliminó
  `/vinculacion-acudientes/importar`: lo cubre esta carga.
- Rendimiento: 500 filas tardaban **175 s** (un bcrypt de ~0,3 s por contraseña al azar y dos
  `REGEXP` sobre `usuarios` por fila). Con una consulta por tipo para todo el archivo y la
  contraseña inicial con 10 rondas de bcrypt: **~40 s** (sin contraseña eran 6 s; con 12 rondas,
  ~2 min).
- El frontend muestra una ventana de espera mientras el servidor procesa el archivo.

### Contraseña inicial con el documento (`28da89f`)

- **Por qué**: sin contraseña, el colegio no sabía si el acudiente ya podía entrar y el acudiente
  dependía de que le llegara el correo. Ahora la cuenta sirve desde que se crea.
- Al crear un acudiente (formulario o Excel) su contraseña es el **número de documento sin puntos
  ni espacios** (`AcudientesService::nuevo`). Se guarda con 10 rondas de bcrypt en vez de 12: el
  documento se adivina, así que lo que protege la cuenta es el código por correo de equipo nuevo y
  el límite de intentos del login, no el costo del hash; con 12, el Excel de 500 filas tardaba ~2
  min.
- El correo de acceso elige su texto al enviarse (`estadoContrasena`, compara la contraseña
  guardada con el documento):
  - `documento`: le dice que su contraseña inicial es su documento y le pide cambiarla
    («Cambiar mi contraseña»).
  - `sin_contrasena`: le pide crearla («Crear mi contraseña»).
  - `propia`: ya eligió una; el botón sirve si la olvidó («Crear una nueva contraseña»).
- Probado: el acudiente nuevo entra con su documento (200) y no con otra clave (401).

### Límite de correos del hosting

- El correo sale por el SMTP del hosting (cPanel/Exim), que limita los envíos **por hora** y ese
  límite lo comparten todos los correos del sistema, códigos de inicio de sesión incluidos (ver el
  incidente del 2026-09-16 en `contexto/modulos/noticias.md`: se cortó tras ~180 correos).
- `POST /acudientes/{id}/acceso-portal` responde **429** cuando el hosting avisa el límite
  (`MailRateLimitException`); antes se tragaba como error genérico y el lote seguía enviando.
- El envío en lote del frontend va de uno en uno con avance real («3 de 20»), botón «Detener»,
  máximo 50 por envío, y se detiene solo con el 429 diciendo cuántos faltan.

### Arreglos del login

- **Acudiente sin usuario de SAMI → 500** (`919d8df`). Desde un equipo ya verificado, el login
  abría una sesión silenciosa en SAMI con `usuarios.user`; los acudientes creados en el módulo no
  lo tienen y `SamiSsoService` lanzaba un TypeError. Ahora los acudientes no pasan por SAMI (no se
  le manda su contraseña) y el servicio no hace nada sin usuario. Test: `LoginSinSamiTest`.
- **Token nuevo con claims del anterior → 401 sin salida** (`9a87f89`). tymon guarda en su
  factory compartida los claims de todo token que lee; `JwtFromCookie` leía la cookie anterior y
  el login emitía el token nuevo encima, heredando `via_llave_maestra` de una sesión con llave
  maestra ya cerrada. Cada petición respondía 401 y cada login volvía a heredarlo. Se vacía la
  factory antes de emitir (`JwtService` y `TokenExchangeController`). Test:
  `TokenSinClaimsHeredadosTest`.

### Correos institucionales por colegio (`1432343`)

- Mapa único dominio → base: `BasesDatosService::BASE_POR_DOMINIO` (`royalschool.edu.co` →
  `mysql`, `playandlearn.edu.co` → `sami_play`, `colegiohebreounion.edu.co` → `sami_hebreo`).
- Lo usan entrar con Google (antes solo `@royalschool.edu.co` y todo iba a la base de Royal),
  crear/editar usuarios (cada colegio acepta su dominio) y recuperar contraseña sin slug (el
  personal de los otros colegios no podía recuperarla). Test: `DominiosInstitucionalesTest`.
- `sami_play` solo existía en la rama `produccion`; `main` se puso al día con ella.

### Saber a quién se le envió el acceso (2026-11-20)

- Columna nueva `usuarios.acceso_portal_enviado_at`: se guarda al salir el correo de acceso. La
  lista de Acudientes la muestra («Enviado · fecha» / «Sin enviar») y la filtra.
- La migración rellena los envíos ya hechos con los enlaces que siguen en
  `password_reset_tokens` (los de acceso duran 7 días; los de recuperar contraseña, 60 minutos).
  Si después del envío se pidió recuperar la contraseña, ese envío no se recupera.
- Por qué se enviaron 114 con el tope de 50: el 50 es por envío (se enviaron lotes de 30) y el
  tope de 100 por hora del backend es del commit `206cb61`: depende de si ya estaba desplegado y
  de que los envíos fueran en la misma hora y por el mismo usuario.

### Auditoría de seguridad (2026-11-20)

- **SAMI solo para Royal**: el login silencioso en SAMI (`sami.royalschool.edu.co`) mandaba el
  usuario y la contraseña del personal de Play And Learn (`sami_play`) al SAMI de Royal. Ahora
  solo corre con la base `mysql`.
- **Google solo deja entrar a quien ya existe**: cualquier cuenta de los dominios del colegio sin
  usuario se creaba sola con perfil Operativo (12 opciones de personal); al sumar los dominios de
  Play And Learn y Hebreo Unión, el riesgo crecía. Ahora responde `?error=usuario_no_registrado`.
- **Vínculos revisables en la creación masiva**: el resultado lista «acudiente → hijo · curso»
  de cada vínculo nuevo; un documento de hijo mal escrito podía mostrarle a un acudiente los
  datos de un niño ajeno sin que nadie lo notara.
- **Máximo de 100 accesos por hora por usuario en el backend**: el tope de 50 era solo del
  frontend; con la sesión de alguien con la 175 se podía gastar el cupo del hosting y dejar a todos
  sin códigos de inicio de sesión.
- Quedó bien: permisos de la 175 en ruta y controller, editar/estado/acceso solo sobre tipo
  ACUDIENTE (no se puede tomar una cuenta del personal), las fórmulas del Excel no hacen
  peticiones a internet (lista de dominios vacía en PhpSpreadsheet), el correo escapa los datos y
  todo cambio queda en el registro de actividad.

### Última conexión y filtro de conexión (2026-11-23)

- **«Última conexión» siempre decía «Nunca»** (`3a3eab6`): `usuarios.ultima_conexion` y
  `ultima_ip` solo se guardaban al entrar por Admisiones; el login del sistema general (el del
  portal para padres y el del personal) nunca los actualizaba. Ahora se guardan en
  `JwtService::generateToken`, por donde pasan todos esos logins (contraseña, código de equipo
  nuevo, Google), salvo con llave maestra (es soporte, no el usuario). Test: `LoginSinSamiTest`.
  Los que entraron antes siguen en «Nunca» hasta su próximo ingreso: ese dato no se guardó.
- **Filtro de conexión** (`d1ade9e`, frontend `0d19753`): «Se han conectado» / «Nunca se han
  conectado» en la lista de Acudientes (`conexion=conectados|nunca` en `GET /acudientes`).
- Con este filtro y el de acceso se ve el embudo completo: a quién no se le ha enviado el acceso,
  a quién se le envió y quién ya entró.

## Despliegue

1. En el servidor: `git pull` de `produccion` (último: `01cd552`). Si `git` avisa
   `dubious ownership`, `git config --global --add safe.directory /home/omnia/app`.
2. Migración `2026_11_18_120000_seed_opcion_acudientes` en **cada base de colegio**. Falla si el
   id 175 ya está ocupado. SQL puro al final de `migraciones_sql.sql`.
3. Migración `2026_11_20_100000_add_acceso_portal_enviado_to_usuarios_table` (recorre todas las
   bases de colegio; SQL puro al final de `migraciones_sql.sql`).
4. Cada colegio necesita su fila activa en `/colegios` apuntando a su base y a una marca activa:
   de ahí salen el enlace del portal y el logo, color e imagen del correo de acceso.
5. Google: la app de OAuth (`GOOGLE_CLIENT_ID`) debe aceptar cuentas de `playandlearn.edu.co` y
   `colegiohebreounion.edu.co`; si está limitada al dominio de Royal, Google las rechaza antes de
   llegar a OMNIA.
6. Frontend: se despliega con el push a `main` (último: `0d19753`).

## Pendientes

- **Límite real de correos por hora**: no está en el código. Revisarlo en el servidor
  (`grep maxemailsperhour /var/cpanel/cpanel.config` y
  `grep -H MAX_EMAIL_PER_HOUR /var/cpanel/users/*`) y ajustar `MAXIMO_POR_ENVIO` (50) en
  `src/pages/Acudientes/parts/EnvioAcceso.parts.tsx`. Para cientos de envíos, el siguiente paso es
  una cola en el servidor que respete ese límite.
- **Riesgos menores de la auditoría** (sin aplicar): avisar al correo anterior cuando se cambia el
  correo de un acudiente (quien tiene la 175 podría quedarse con la cuenta), activar el `state` de
  Socialite en el login con Google (hoy `stateless()`), y el consumo de memoria de un .xlsx muy
  comprimido.
- **Cambio de contraseña obligatorio**: el correo pide cambiar la contraseña inicial, pero nada lo
  obliga. Si se quiere, el portal puede exigirlo en el primer ingreso (comparar con el documento,
  como `estadoContrasena`).
- **Acudientes creados en el módulo entre el 18 y el 20 de noviembre** (antes de `28da89f`): no
  tienen el documento como contraseña; tienen una al azar (primeros días) o ninguna. El correo les
  pide crearla («propia» o «sin contraseña»). Si se quiere uniformar, una migración puede ponerles
  el documento a los que nunca han entrado (`ultima_conexion` null) y no se registraron en
  Admisiones.
- `/usuarios/importar` (creación masiva de personal) quedó sin botón y exige
  `@royalschool.edu.co`; decidir si se borra o se adapta a los dominios por colegio.
