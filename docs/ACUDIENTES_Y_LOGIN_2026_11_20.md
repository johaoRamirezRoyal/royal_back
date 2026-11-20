# Módulo Acudientes, correos institucionales y arreglos del login (2026-11-18 al 20)

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
- Crear y editar (documento, nombres, apellidos opcionales, correo personal, teléfono). **El
  colegio nunca escribe ni ve la contraseña**: la cuenta nace sin contraseña (`pass` null, no se
  puede entrar) hasta que el acudiente la crea.
- **Enviar acceso al portal**: correo propio (`AccesoPortalPadresMail`, vista
  `emails.accesoPortalPadres`) con la marca del colegio y un enlace de 7 días a crear contraseña
  (misma página y tabla que recuperar contraseña). Por fila o en lote.
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
  `REGEXP` sobre `usuarios` por fila). Sin contraseña y con una consulta por tipo para todo el
  archivo: **~6 s**.
- El frontend muestra una ventana de espera mientras el servidor procesa el archivo.

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

## Despliegue

1. En el servidor: `git pull` de `produccion` (último: `aab3406`). Si `git` avisa
   `dubious ownership`, `git config --global --add safe.directory /home/omnia/app`.
2. Migración `2026_11_18_120000_seed_opcion_acudientes` en **cada base de colegio**. Falla si el
   id 175 ya está ocupado. SQL puro al final de `migraciones_sql.sql`.
3. Cada colegio necesita su fila activa en `/colegios` apuntando a su base y a una marca activa:
   de ahí salen el enlace del portal y el logo, color e imagen del correo de acceso.
4. Google: la app de OAuth (`GOOGLE_CLIENT_ID`) debe aceptar cuentas de `playandlearn.edu.co` y
   `colegiohebreounion.edu.co`; si está limitada al dominio de Royal, Google las rechaza antes de
   llegar a OMNIA.
5. Frontend: se despliega con el push a `main` (último: `c51f339`).

## Pendientes

- **Límite real de correos por hora**: no está en el código. Revisarlo en el servidor
  (`grep maxemailsperhour /var/cpanel/cpanel.config` y
  `grep -H MAX_EMAIL_PER_HOUR /var/cpanel/users/*`) y ajustar `MAXIMO_POR_ENVIO` (50) en
  `src/pages/Acudientes/parts/EnvioAcceso.parts.tsx`. Para cientos de envíos, el siguiente paso es
  una cola en el servidor que respete ese límite.
- **SAMI y los otros colegios**: el login silencioso en SAMI usa siempre
  `sami.royalschool.edu.co`, así que el personal de Play And Learn (`sami_play`) manda su usuario
  y contraseña al SAMI de Royal (por eso el aviso «login rechazado por SAMI» en el log).
  Restringirlo a la base `mysql`.
- `/usuarios/importar` (creación masiva de personal) quedó sin botón y exige
  `@royalschool.edu.co`; decidir si se borra o se adapta a los dominios por colegio.
