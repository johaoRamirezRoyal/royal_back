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
- **Disco `cloudinary`** (driver `cloudinary_uploads`,
  `App\Services\Cloudinary\CloudinaryUploadsAdapter`):
  - Usa el mismo direccionamiento que la migración: `<CLOUDINARY_UPLOADS_PREFIJO,
    royal>/<ruta>`. Así los archivos migrados y los nuevos se ven igual, y el frontend
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
- **`POST /api/migracion/cloudinary`** (`MigracionCloudinaryController`) no usa JWT, sino
  el header `X-Migracion-Token` contra `MIGRACION_API_TOKEN`.
  - Si la variable está vacía, responde 404 (queda apagado). Después de cambiarla, corre
    `config:cache`.
  - Sube hasta 20 archivos de 10 MB a `royal/<directorio>` y sobrescribe por nombre.
  - Lo usa el script externo `script_liberacion`, que **no** actualiza la BD.

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
