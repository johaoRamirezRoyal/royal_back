# Infraestructura

## Mapa de rutas (`routes/api.php`)

| Grupo | Prefijos |
|---|---|
| Sin autenticación | `/` (welcome), `POST /pushNotification/{tenant?}` (Hikvision), `POST /migracion/cloudinary` (token propio), `/auth` (`api/auth.php`), `/admissions`, `/institucion` |
| `auth:api` (ambos sistemas) | `/public/{ruta}`, `GET /biblioteca/imagen/...` (antes sin sesión), `/documentos`, `/compartido` (+ `anioAcademico.php`, `historiaClinica.php`) |
| `auth:api` + `system:admissions` | `/admisiones`, `/admisiones/tipos-documentos` |
| `auth:api` + `system:general` | todos los módulos del sistema general (`/usuarios`, `/inventario`, `/gestion-academica`, `/noticias`, `/admin-management`, ...) |

Cada prefijo carga su archivo de `routes/api/` (`Biblioteca.php` es el único con
mayúscula). La lista completa y actual de prefijos está en `routes/api.php`; no la copies
aquí, porque se desactualiza (la tabla anterior no tenía la mitad).

## Archivos

- **`GET /api/public/{ruta}?w=`** (`DocumentoController::verPublico`, con sesión, caché
  `private` de 7 días) sirve archivos del disco de uploads.
  - Con `w=64|128|256|512` y una imagen, devuelve una miniatura WebP con la orientación
    EXIF corregida, guardada en `storage/app/miniaturas/{ancho}/{sha1(ruta)}`.
  - Con otro ancho, un archivo que no es imagen o uno que GD no lee, devuelve el
    original.
  - Cada imagen sigue siendo una petición a Laravel (arranque + JWT), y eso domina el
    tiempo.
- **`FileStorageService`** guarda archivos locales: `uploadFile` (nombre UUID), `eliminar`
  y `reemplazar`.
  - El disco es `config('filesystems.uploads_disk')`: **`cloudinary` en producción**
    (`APP_ENV=production`) y **`public` en cualquier otro entorno** (`storage/app/public`,
    servido en `public/storage`; requiere `php artisan storage:link`). `UPLOADS_DISK` lo
    sobrescribe. El disco `sami` (`public/upload` del SAMI legado) se quitó: si un `.env`
    todavía tiene `UPLOADS_DISK=sami`, las subidas fallan.
  - Las firmas legacy (fila de `firmas` con `nombre` y sin `url`) ya no se leen: el usuario
    tiene que volver a subir su firma.
  - En la BD se guarda la **`ruta`**; la `url` es solo para exponerla.
  - `rutaLocal($ruta)` da un path real para lo que lo necesita (GD, adjuntos). En un disco
    remoto, descarga a `storage/app/archivos-remotos/` y lo reusa. **No uses
    `$disk->path()`**: en Cloudinary devuelve la ruta relativa, no un archivo.
  - `existentes($rutas)` comprueba muchas rutas a la vez (HEAD en paralelo). Úsalo en
    listados en vez de `exists()` por fila.
- **Carpeta raíz por colegio en Cloudinary** (`BasesDatosService::carpeta($connection)`):
  cada colegio (connection) guarda sus archivos en `<carpeta>/...`. Se edita en el panel
  "Bases de datos" (`PUT /api/admin-management/bases-datos/carpeta`, columna
  `bases_datos_nombres.carpeta_cloudinary`). Sin valor, la de fábrica: `mysql` usa `royal`
  y las demás el nombre de la connection. Ya no hay variable de entorno para esto
  (`CLOUDINARY_UPLOADS_PREFIJO` se quitó).
  - La usan el disco `cloudinary` (resuelve la carpeta por archivo, según
    `database.default`), la carpeta de Admisiones y las subidas de módulos de un colegio con
    `CloudinaryService::carpetaColegio()` (cartas de Instituciones, revistas, capacitaciones,
    firmas). Lo transversal (banners de administración, logos de marcas, la migración) no.
  - Viaja al frontend como `carpeta_archivos` en `UsuarioResource`.
  - Cambiarla no mueve los archivos ya subidos: hay que moverlos en Cloudinary. Para
    privatizar la raíz anterior: `cloudinary:privatizar --carpeta=<anterior>`.
- **Disco `cloudinary`** (driver `cloudinary_uploads`,
  `App\Services\Cloudinary\CloudinaryUploadsAdapter`):
  - Usa el mismo direccionamiento que la migración: `<carpeta del colegio, royal en
    Royal>/<ruta>`. Así los archivos migrados y los nuevos se ven igual, y el frontend
    arma la URL solo (`src/utils/fileUrl.ts`, que sigue la misma regla, con test en
    `tests/Unit/Cloudinary`).
  - Imágenes y PDF son `image` (public_id sin extensión) y el resto `raw` (con
    extensión). El nombre se sanea como en `CloudinaryService::uploadFile`.
  - `url()`, `exists()` y la lectura usan la URL de entrega (CDN), no la Admin API, que
    tiene cuota por hora.
  - El disco `cloudinary` del paquete (`CloudinaryStorageAdapter`) no sirve aquí: solo
    pone prefijo a los `raw`, manda los PDF a `raw` y gasta Admin API en cada `url()`.
- **`CloudinaryService`**: `uploadFile($file, $folder, ..., $extensionesPermitidas?)`,
  `getFileUrl` y `deleteFile`.
  - Los PDF se suben como `image` y los documentos de Office como `raw` (sin extensión en
    el `public_id`).
  - Límite de 10 MB; extensiones jpg, jpeg, png, webp y pdf.
  - Para borrar, `deleteFile` necesita el **mismo** `resource_type` de la subida (un PDF es
    `image`). Si no coincide, Cloudinary responde "not found", el servicio lo toma como
    éxito y el archivo se queda.
- **Archivos de admisiones** (`AdmissionsController`):
  - Se suben a `<carpeta del colegio>/Admisiones/documentos/{id_inscripcion}` (y
    `.../CitasPsicologia/{id_cita}`) con un `public_id` único (nombre + sufijo aleatorio).
    Antes iban todos a `Admisiones/documentos` con el nombre original y `overwrite`: dos
    familias con `cedula.pdf` se pisaban el archivo.
  - "Limpiar campos" (`eliminarDatoInscripcion`) y eliminar el aspirante
    (`eliminarInscripcion`) borran también sus archivos en Cloudinary, después de borrar
    las filas. Si Cloudinary falla, se registra en el log y la acción sigue.
  - Los archivos que ya estaban sueltos en Cloudinary antes de este cambio no se limpian
    solos. Test: `tests/Unit/Cloudinary/AdmisionesArchivosTest.php`.
- **`POST /api/migracion/cloudinary`** (`MigracionCloudinaryController`) no usa JWT, sino
  el header `X-Migracion-Token` contra `MIGRACION_API_TOKEN`.
  - Si la variable está vacía, responde 404 (queda apagado). Después de cambiarla, corre
    `config:cache`.
  - Sube hasta 20 archivos de 10 MB a `royal/<directorio>` y sobrescribe por nombre.
  - Lo usa el script externo `script_liberacion`, que **no** actualiza la BD.

## Archivos privados en Cloudinary

Qué se cambió, por qué, el deploy y los pendientes:
[`ARCHIVOS_PRIVADOS_CLOUDINARY_2026_11_03.md`](../ARCHIVOS_PRIVADOS_CLOUDINARY_2026_11_03.md).

Los archivos sensibles se suben con `type: authenticated`: sin la firma `s--...--` en la URL,
Cloudinary responde 401. La regla está en `App\Services\Cloudinary\ArchivosPrivados`
(test en `tests/Unit/Cloudinary/ArchivosPrivadosTest.php`):

- Carpetas privadas: las de `ArchivosPrivados::PATRON`, en cualquier nivel del public_id (con o
  sin la carpeta del colegio delante). Qué guarda cada una: tabla «Qué es privado» del doc de
  arriba. Incluye `royal-school/firma*` y `royal-school/paz-salvo/sello*`, que el backend baja con
  `rutaLocalRoyalSchool` (firma la URL); el frontend no los pide.
- **Una carpeta nueva con datos personales se agrega en `PATRON` y en `esPrivado` de
  `src/utils/fileUrl.ts` (frontend)**, y después se corre `cloudinary:privatizar`.
- El disco `cloudinary` además trata como privado todo lo que está en la **raíz** del colegio,
  porque ahí se guardan fotos de perfil y las cotizaciones y facturas de compras.

Quién pone la firma (nunca el frontend). **Una URL firmada no vence**: quien la tenga la abre,
con sesión o sin ella. Por eso al navegador solo llegan firmadas las **fotos**
(`ArchivosPrivados::esFoto`: `carnets/<archivo>`; en `/api/public`, también las imágenes de la
raíz, que son fotos de perfil y carnets de SAMI), que se ven de a decenas en listas. Los
**documentos** (todo lo demás privado: Admisiones, firmas, certificados, cotizaciones…) pasan
por Laravel con sesión, sin caché: al cerrar sesión dejan de abrir.

- `CloudinaryService::uploadFile`/`deleteFile` y el adapter eligen el `type` con
  `ArchivosPrivados::tipoEntrega()`. El adapter devuelve URLs firmadas, así que `url()`,
  `exists()`, `rutaLocal()` y `existentes()` siguen funcionando.
- **`FirmarArchivosPrivados`** (middleware del grupo `api`): en toda respuesta JSON con sesión,
  reemplaza las URLs de Cloudinary de archivos privados (`ArchivosPrivados::paraCliente`): una
  foto por la firmada, un documento por **`GET /api/privado/{token}/{nombre}`** (token cifrado
  con `APP_KEY`, así no se puede armar a mano; el nombre con su extensión va al final porque
  el frontend decide imagen o PDF por cómo termina la URL). La BD sigue guardando la URL sin
  firma (con `/upload/` si es de antes). Así el permiso para ver el archivo es el del endpoint
  que lo lista. Sin sesión no toca nada (fail-closed). Solo reconoce strings que son
  exactamente la URL, sin transformaciones.
- **`GET /api/public/{ruta}?w=&descargar=`** (disco `cloudinary`): un documento lo transmite
  (`DocumentoController::transmitir`, con la miniatura de Cloudinary si es imagen); una foto,
  redirige (302) a la URL firmada con la miniatura y `fl_attachment` hechos por Cloudinary.
  **El 302 va con `no-store`**: guardado (antes, 7 días), seguía abriendo la foto después de
  cerrar sesión. El frontend usa esta ruta para los privados del disco (`fileUrl.ts`).
- Lo que descarga el propio backend desde una URL guardada pasa por
  `ArchivosPrivados::firmar()` (p. ej. `InventarioServices::descargarFirma`), que sí firma
  también los documentos. **Si agregas otro `Http::get($url)` sobre una URL de la BD, fírmala**,
  o recibirá 401.
- En el frontend, un `fetch` de una URL que llegó en el JSON va con `fetchArchivo`
  (`src/utils/fileUrl.ts`): manda la cookie solo a `/api/privado`, porque Cloudinary responde
  `Access-Control-Allow-Origin: *` y no admite credenciales.

Lo que sigue abierto: una foto firmada que alguien copió sigue abriendo. Para que venza haría
falta `type: private` + `private_download_url` (sin miniaturas) o token auth (plan pago).

**Migración** de lo ya subido: `php artisan cloudinary:privatizar --dry-run` lista lo que pasaría
a privado y sin `--dry-run` lo hace (`rename` con `to_type: authenticated` e `invalidate`). Usa
Admin API para listar (1 llamada por cada 500 archivos). Se puede repetir. Correrlo justo
después del deploy: hasta entonces `deleteFile` busca como `authenticated` los archivos que
siguen en `upload` y no los borra.

Varios colegios:

- Las carpetas privadas son patrón: valen para cualquier colegio sin configurar nada. Solo la
  regla de la raíz depende de la carpeta de "Bases de datos". El comando toma la de cada
  connection; para una carpeta **anterior** (al cambiarla, los archivos no se mueven) pásala
  con `--carpeta=vieja` (repetible), o su raíz queda pública.
- "Bases de datos" rechaza una carpeta que contenga el nombre de una carpeta privada
  (`ArchivosPrivados::chocaConCarpetaPrivada`, p. ej. `certificados`): volvería privado todo
  ese colegio.
- Las firmas de historia clínica van a `<carpeta del colegio>/historia-clinica/firmas`. Las de
  antes siguen mezcladas en `historia-clinica/firmas` (sin colegio), privadas igual.

En la consola de Cloudinary conviene activar *Security → Strict transformations* y verificar
que *Resource list* esté restringido.

## Firmas manuales en citas de psicología

`PUT /compartido/citaPsicologia/estado` acepta `firma` (imagen, multipart con `_method=PUT`)
al pasar a ATENDIDA, **solo del Super Admin** (`Permiso::PERFIL_SUPER_ADMIN`; si no, 403 antes
de subir nada). Se sube a `Admisiones/CitasPsicologia/<id>/firma` y se crea un `Firma`
con `activo = 0` a nombre de la psicóloga de la cita, enlazado a la historia
(`hce_observaciones_firmas.id_firma_psicologa`). Sin firma manual se usa la activa de la
psicóloga; sin ninguna, 422 antes de cambiar el estado. Por eso
`UsuariosServices::subirFirmaUsuario` solo toca la fila activa del usuario (nunca una firma
manual), y si al reemplazarla una historia ya apunta a ella (`id_firma_psicologa`), la deja
inactiva y crea otra: la historia conserva la firma con la que se firmó.
`listarCitasPsicologia` devuelve `psicologa_tiene_firma` por cita.

**`CloudinaryService` en local:** el disco local SOLO se usa en `APP_ENV` `local` y `testing`
(`CloudinaryService::usaCloudinary`); cualquier otro entorno (producción) sube y borra siempre en
Cloudinary, sin importar `UPLOADS_DISK`. En local `uploadFile` guarda en el disco `public` y devuelve
`/api/public/{ruta}` (con sesión), y `deleteFile`/`deleteFileByUrl` solo borran en ese disco: una
URL de Cloudinary (datos copiados de producción) nunca se borra desde local. El `.env` local trae
las credenciales reales, por eso la regla.

Firmas en Cloudinary (todas privadas, ver arriba):

- **Usuario** (`subirFirmaUsuario`): `<carpeta del colegio>/usuarios/firmas/firma_<id_user>_<8 al
  azar>`. Antes se subía con el nombre original y `overwrite`, y dos `firma.png` se pisaban. Al
  cambiarla, la anterior se borra de Cloudinary (después de guardar la nueva; si falla, solo se
  registra), salvo que una historia clínica la use: ahí se conserva con su fila inactiva. El
  `public_id` sale de la `url` con `ArchivosPrivados::recursoDe`.
- **Manual de una cita** (Super Admin al marcarla atendida): fila inactiva de `firmas`. Si la
  historia pasa a otra firma (se vuelve a atender) o se elimina, `Firma::descartarSiNoSeUsa` borra
  la fila y su archivo cuando ninguna historia la usa; nunca toca la activa. Si el cambio de estado
  falla antes de enlazarla, el controlador borra el archivo recién subido.
- **Historia clínica** (`firma_padre`/`firma_madre`): `<carpeta del colegio>/historia-clinica/firmas`
  (las de antes, en `historia-clinica/firmas` sin colegio). Se borran como `image`, que es como se
  suben: con `raw` Cloudinary respondía «not found» y quedaban. Al reemplazarla, la anterior se
  borra después de guardar la nueva (antes se borraba primero y, si la subida fallaba, el registro
  quedaba apuntando a un archivo inexistente).
- **Legacy**: una fila de `firmas` con `nombre` y sin `url` ya no se lee en ningún lado.

## PDFs

### Documentos de src/documentos (pdfmake)

Qué se cambió, el deploy y los pendientes:
[`DOCUMENTOS_PDFMAKE_2026_11_12.md`](../DOCUMENTOS_PDFMAKE_2026_11_12.md).

- Los documentos nuevos se definen en el frontend (`src/documentos/`, ver su
  `docs/contexto/documentos.md`) y el servidor los genera con
  `app(DocumentoPdfService::class)->generar('<id>', $datos, $correoUsuario)`, que devuelve el
  PDF en binario y lanza `RuntimeException` si falla.
- Ejecuta `node resources/documentos/render.cjs` (JSON por stdin, PDF por stdout). Ese archivo
  **se compila en el frontend** con `pnpm build:documentos`; no se edita aquí. Si se cambia
  un documento en el frontend y no se recompila, el servidor sigue generando la versión vieja.
- **El servidor necesita Node** (>= 20). `NODE_BINARY` en `.env` si `node` no está en el PATH
  del usuario del servidor web.
- **Windows**: Node aborta al arrancar sin la variable `SystemRoot` (`Assertion failed:
  ncrypto::CSPRNG`), y el servidor web (`php artisan serve`, Apache) puede lanzar procesos
  con un entorno que no la trae. `DocumentoPdfService::entornoNode()` la pasa explícita; no
  la quites.
- La institución (marca y carpeta de documentos) sale del dominio del correo. El logo es el de
  `MarcaDominioService::rutaLocalLogo()` (ver «Logo» abajo) y se manda como data URL; si es
  webp se pasa a PNG con GD, porque pdfmake no embebe webp.
- Ya migrados: carta de entrega (`InventarioServices::generarCartaEntregaPdf`), paz y salvo y
  listados de préstamos de Biblioteca (`BibliotecaServices`), carta de recomendación de
  Instituciones (`InstitucionController`) y certificado de capacitación
  (`CapacitacionesController::certificado`, fondo en `resources/pdf/capacitaciones/`).
- La institución sale del correo del usuario o, sin usuario (la carta de recomendación de un
  jardín), de `DocumentoPdfService::dominioDelColegio()`, que traduce la connection activa con
  `DOMINIO_POR_CONNECTION`. Un documento propio de un colegio (carpeta
  `instituciones/<colegio>/` del frontend) no se genera para otro: lanza `RuntimeException`. El
  certificado de capacitación es solo de Royal School: a un usuario de Hebreo Unión el endpoint
  le responde 422.
- Imágenes extra (sello, firma) van dentro de `$datos` con
  `DocumentoPdfService::imagenComoDataUrl($rutaLocal)`; el documento no puede descargarlas.
- Cada documento lanza un proceso de Node (~0,3 s). En los envíos masivos (recordatorio de
  préstamos) eso se suma por usuario; si llega a pesar, generar varios en un solo proceso.

### TCPDF (documentos aún no migrados)

- **Logo**: todo PDF/Excel usa `app(MarcaDominioService::class)->rutaLocalLogo($correo)`.
  Devuelve el logo de la marca del dominio del correo, configurado en /marcas (sin
  correo, usa el del usuario autenticado). Si no hay marca, devuelve el fijo
  `royal/royal-school/logotipoBackground.png`, y un WEBP sale convertido a PNG. Un colegio
  nuevo solo configura su marca: no hay logos por documento. Ejemplo: `HojaVidaPdfService`.
- Las demás imágenes fijas de documentos (sellos, firmas) viven en Cloudinary bajo
  `royal/royal-school/` (`paz-salvo/sello.webp`, `firma_administrativa.jpg`), no en el disco
  del servidor.
  `rutaLocalRoyalSchool()` las descarga a un temporal porque TCPDF/PhpSpreadsheet necesitan
  ruta local; si falla devuelve null y el documento sale sin esa imagen (sin respaldo local).
  Una nueva se sube con `POST /api/migracion/cloudinary` (`directorio=royal-school/...`).
  **No las subas desde la consola de Cloudinary**: la cuenta usa carpetas dinámicas y la
  consola les pone un public_id con sufijo aleatorio (`logotipoBackground_fk3t8o`), así que
  `royal/royal-school/<archivo>` da 404. El public_id tiene que ser exactamente
  `royal/royal-school/<nombre>`. `firma_administrativa.jpg` es la firma fija del V°B° en
  el reporte operativo de inventario.
- El `checkPageBreak` de TCPDF es `protected`; para salto de página con coordenadas
  manuales, usa un wrapper propio que compare `GetY()` con `getPageHeight() - getBreakMargin()`.

## Correo

- `MailService`: `sendView`, `sendGeneric` (con BCC a `COPIA_GESTOR`) y `send($to,
  Mailable, copiaGestor?)`. Filtra correos inválidos antes de enviar. Si detecta el límite
  por hora del hosting (`452` + "too many recipients"), lanza `MailRateLimitException`.
  `sendGeneric`/`GenericMail` aceptan `$urlBoton` (y `$textoBoton`, por defecto "Ver en
  OMNIA"), que agrega un botón con enlace al final del correo. Hoy lo usan los correos de
  creación y edición de Permisos y Licencias
  (`PermisosLicenciasServices::enviarConBotonAprobadores`): el mismo correo se manda dos
  veces, con botón a `FRONTEND_URL/permisos-licencias/solicitud/{id}?origen=correo` para
  los destinatarios cuyo perfil tiene la opción 83 (o Super Admin), y sin botón para el
  resto. Test: `tests/Unit/PermisosLicencias/CorreosQueApruebanTest.php`.
- **Correos de Admisiones** (acudientes): todos extienden
  `resources/views/emails/layouts/admisiones.blade.php` (encabezado, línea dorada, pie) y
  solo definen `title` y `content`. Hoy: `sendRequestEmail` (código de verificación) y
  `registroAcudiente` (registro confirmado, que envía `AdmissionsController::familyRegister`
  al crear al acudiente, con enlace a `FRONTEND_URL/{slug}/admissions`). Un correo nuevo de
  admisiones usa ese layout en vez de copiar el HTML.
- **Destinatarios fijos**: `App\Enums\Mails` → `correos_institucionales` por `grupo`.
  Ninguna dirección va en el código ni en las migraciones: cada BD carga las suyas, y un
  grupo vacío significa que ese aviso no se envía. Se administran en
  `/api/admin-management/correos-institucionales` (perfil 1 + `admin.access`). Todo
  `case` nuevo del enum necesita su `label()`.

| Grupo | Uso |
|---|---|
| `COPIA_GESTOR` | BCC de `sendGeneric` y de `send(..., copiaGestor: true)` |
| `VICERRECTORIA` | escalación por límite de llegadas tarde |
| `RESERVAS_SISTEMAS` / `_MAKER_LAB` / `_BIBLIOTECA` / `_CAFETERIA` | aviso de reserva (sistemas + el grupo del salón) |
| `CRONOGRAMA_SISTEMAS` | avisos de Inventario y Préstamos |
| `ADMISIONES_ESTADO` | cambio de estado de una inscripción |
| `RRHH_ASISTENCIA` | cierre automático de asistencias vencidas |
| `COMPRAS_VENTAS` | copia de los avisos de solicitudes de compra (nueva solicitud y verificación de entrega) |
| `DIRECCION_ADMINISTRATIVA` | avisos de Áreas Comunes |
| `NOTICIAS_*` | listas de distribución de Noticias (`NOTICIAS_TODOS` es también el fallback del banner informativo) |

- Eventos (enlazados en `AppServiceProvider`): `PasswordRestore` → cola `default` (3
  reintentos) y `RequestEmailAdmission` → cola `emails` (3 reintentos).
- `EstadoInscripcionCambiado` → `NotificarEstadoInscripcionCambiado`, cola `emails` (3
  reintentos; lo enlaza el descubrimiento de eventos de Laravel, no `AppServiceProvider`).
  Se despacha en `AdmisionesServices::actualizarEstadoDeInscripcionAspirante`, el único
  punto que cambia el estado de una inscripción (Reportes, modo admin, "Enviar admisión" y
  la cita de psicología atendida). El listener envía el correo a `ADMISIONES_ESTADO` + el
  acudiente y, en revisión de psicología, a las psicólogas del nivel. Lleva datos simples
  (no modelos). Lo nuevo que deba pasar con cada cambio de estado va en otro listener del
  mismo evento. Si en producción se cachean los eventos (`event:cache` / `optimize`), hay que
  regenerar la caché al desplegar.
- Para "usuarios del perfil X en el nivel Y", usa el método estático
  `Usuario::correosPorPerfilesYNivel($perfiles, $idNivel, $soloActivos = false)`. Su
  valor por defecto es `false` a propósito: así se conserva el comportamiento de los
  avisos de permisos.

## Configuración

| Archivo | Clave |
|---|---|
| `config/auth.php` | guard por defecto `api` (jwt) |
| `config/cors.php` | localhost:3000/5173/5174/4000 + Vercel + gestorsami |
| `config/queue.php` | `database`; cola `emails` para admisiones |
| `config/mail.php` | `log` por defecto |
| `config/services.php` | Google OAuth, Hikvision, SAMI SSO, `asistencia_qr.redes` |

- **Base de administración: connection `admin_management`, base `admin_managements`.** En
  producción la base se llama `admin_managements` (con «s»), que es el valor por defecto de
  `DB_ADMIN_DATABASE`. La connection sigue siendo `admin_management`: es la clave que usan los
  modelos (`$connection`) y no se cambia. En local, `.env` puede apuntar a otra base. En los
  scripts SQL para producción (`migraciones_sql.sql`) usa `admin_managements`.
- **`MANTENIMIENTO`** (`.env`): bloquea la API con un 503.
- **Scheduler**: en local no lo corre `composer run dev`; usa `php artisan
  schedule:work`. `schedule:run` solo dispara las tareas del minuto exacto en que se
  invoca; para probar un comando, córrelo directo.

## Laravel Boost (solo desarrollo)

`laravel/boost` (`require-dev`) agrega `boost.json`, `.mcp.json` (servidor MCP
`laravel-boost`: consultas de solo lectura a la BD, esquema, logs y documentación), el
bloque de guidelines en `CLAUDE.md` y skills en `.claude/skills/`. Se regenera con
`php artisan boost:install` o `boost:update`.
