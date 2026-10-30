# Infraestructura

## Mapa de rutas (`routes/api.php`)

| Grupo | Prefijos |
|---|---|
| Sin autenticación | `/` (welcome), `POST /pushNotification/{tenant?}` (Hikvision), `POST /migracion/cloudinary` (token propio), `/auth` (`api/auth.php`), `GET /biblioteca/imagen/{carpeta}/{filename}`, `/admissions`, `/institucion` |
| `auth:api` (ambos sistemas) | `/documentos`, `/compartido` (+ `anioAcademico.php`, `historiaClinica.php`) |
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
    sobrescribe (p. ej. `sami`, que sirve `public/upload` del SAMI legado), así que en el
    `.env` de producción no debe quedar `UPLOADS_DISK=sami`.
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
  - Cambiarla no mueve los archivos ya subidos: hay que moverlos en Cloudinary.
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

## Firmas manuales en citas de psicología

`PUT /compartido/citaPsicologia/estado` acepta `firma` (imagen, multipart con `_method=PUT`)
al pasar a ATENDIDA, **solo del Super Admin** (`Permiso::PERFIL_SUPER_ADMIN`; si no, 403 antes
de subir nada). Se sube a `Admisiones/CitasPsicologia/<id>/firma` y se crea un `Firma`
con `activo = 0` a nombre de la psicóloga de la cita, enlazado a la historia
(`hce_observaciones_firmas.id_firma_psicologa`). Sin firma manual se usa la activa de la
psicóloga; sin ninguna, 422 antes de cambiar el estado. Por eso
`UsuariosServices::subirFirmaUsuario` hace `updateOrCreate` sobre `['id_user', 'activo' => 1]`:
si fuera solo por `id_user` podría pisar una firma manual y cambiar la de una historia ya
firmada. `listarCitasPsicologia` devuelve `psicologa_tiene_firma` por cita.

## PDFs

- Todo PDF institucional nuevo usa este logo fijo, no el de la marca por dominio:
  `app(CloudinaryService::class)->rutaLocalRoyalSchool('logotipoBackground.png')` (ejemplo:
  `HojaVidaPdfService`).
- Las imágenes fijas de documentos (logos, escudos, sellos) viven en Cloudinary bajo
  `royal/royal-school/` (`logotipoMarca.png`, `paz-salvo/{logo,sello}.webp`,
  `instituciones/logo.png`, `horario/logo.png`), no en el disco del servidor.
  `rutaLocalRoyalSchool()` las descarga a un temporal porque TCPDF/PhpSpreadsheet necesitan
  ruta local; si falla devuelve null y el documento sale sin esa imagen (sin respaldo local).
  Una nueva se sube con `POST /api/migracion/cloudinary` (`directorio=royal-school/...`).
- El `checkPageBreak` de TCPDF es `protected`; para salto de página con coordenadas
  manuales, usa un wrapper propio (como `ensureSpacio()` en `CartaRecomendacionPdfService`).

## Correo

- `MailService`: `sendView`, `sendGeneric` (con BCC a `COPIA_GESTOR`) y `send($to,
  Mailable, copiaGestor?)`. Filtra correos inválidos antes de enviar. Si detecta el límite
  por hora del hosting (`452` + "too many recipients"), lanza `MailRateLimitException`.
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

- **`MANTENIMIENTO`** (`.env`): bloquea la API con un 503.
- **Scheduler**: en local no lo corre `composer run dev`; usa `php artisan
  schedule:work`. `schedule:run` solo dispara las tareas del minuto exacto en que se
  invoca; para probar un comando, córrelo directo.

## Laravel Boost (solo desarrollo)

`laravel/boost` (`require-dev`) agrega `boost.json`, `.mcp.json` (servidor MCP
`laravel-boost`: consultas de solo lectura a la BD, esquema, logs y documentación), el
bloque de guidelines en `CLAUDE.md` y skills en `.claude/skills/`. Se regenera con
`php artisan boost:install` o `boost:update`.
