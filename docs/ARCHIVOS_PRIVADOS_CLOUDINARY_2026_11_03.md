# Archivos privados en Cloudinary (2026-11-03)

Cambio en `royal_back` y en el frontend (`S.A.M.I_frontend`). Las reglas vigentes están en
[`contexto/infraestructura.md`](contexto/infraestructura.md) («Archivos privados en
Cloudinary») y, del lado del frontend, en `docs/contexto/componentes.md` (`fileUrl.ts`). Este
documento explica qué se cambió, por qué y qué falta hacer en el deploy.

## Por qué

Todo se subía a Cloudinary como `type: upload`, es decir, público. Las URLs se podían armar
sin preguntarle al backend: `src/utils/fileUrl.ts` las construía con el `cloud_name` (que está
en el bundle), la carpeta del colegio y el nombre del archivo, que en varios módulos era el
nombre original. Así, cualquiera que supiera o adivinara una URL podía descargar, sin sesión,
cédulas y documentos de admisiones, citas de psicología, firmas, fotos de carnet de menores
y certificados del personal. Y una URL filtrada (un correo reenviado, el historial de otro
equipo) daba acceso para siempre.

## La solución

Los archivos sensibles se suben como `type: authenticated`. Sin la firma `s--XXXXXXXX--` en la
URL, Cloudinary responde 401. **La firma solo la pone el backend**, y solo la entrega a quien
tiene sesión.

### Qué es privado

Regla única: `App\Services\Cloudinary\ArchivosPrivados::PATRON`, evaluada sobre el `public_id`
completo. Las carpetas cuentan en cualquier nivel, con o sin la carpeta del colegio delante.

| Carpeta | Contenido |
|---|---|
| `Admisiones/` | documentos de inscripción, citas de psicología (observaciones y firmas manuales) |
| `historia-clinica/` | firmas de padre y madre |
| `usuarios/firmas/` | firma de cada usuario («Mi firma») |
| `carnets/<archivo>` | fotos de carnet (`carnets/plantillas/` sigue pública) |
| `certificados/` | certificados; también `formacion/certificados` y `experiencia-laboral/certificados` (Perfil) |
| `gestion-humana/` | certificados de cursos de altura |
| `produccion-intelectual/` | producción intelectual (Perfil) |
| `permisos-licencias/` | evidencias de permisos y licencias |
| `tramites/documentos/` | trámites |
| `recursos/documentos/` | documentos de recursos |
| `documentos-varios/` | documentos varios |
| `proveedores/documentos/` | documentos de proveedores |
| `royal-school/firma*`, `royal-school/paz-salvo/sello*` | firma de la directora (V°B° de inventario) y sello del paz y salvo de biblioteca |
| **raíz del colegio** (solo el disco `cloudinary`) | fotos de perfil, cotizaciones y facturas de compras (`SolicitudesServices::CARPETA_*` están vacías) y lo que se migró de SAMI |

Sigue público lo que no tiene datos personales: logos y branding, banners, noticias, revistas,
cumpleaños, capacitaciones, biblioteca, plantillas de carnet, cartas de recomendación de
Instituciones y evidencias de `inventario/programacion-mant`. Enfermería no sube archivos.

### Cómo funciona

Lo vigente (quién firma, `/api/public`, el middleware, varios colegios, la migración) está en
[`contexto/infraestructura.md`](contexto/infraestructura.md), «Archivos privados en Cloudinary»,
y en `docs/contexto/componentes.md` del frontend (`fileUrl.ts`). En corto: el backend sube con
`type: authenticated`, firma las URLs al responder JSON con sesión y en `/api/public`, y el
frontend pide ahí los privados del disco en vez de armar la URL de Cloudinary.

## Cambios relacionados

- **Documentos por Laravel (después del deploy).** Al cerrar sesión, los archivos se seguían
  viendo: la URL firmada no vence y el navegador guardaba el 302 de `/api/public` por 7 días.
  Ahora el 302 de las fotos va sin caché y los documentos (todo lo privado salvo las fotos) los
  transmite Laravel con sesión: los del JSON por `GET /api/privado/{token}/{nombre}` y los del
  disco por `/api/public`. Sigue abierto: una foto firmada que alguien copió sigue abriendo.
- **`GET /api/biblioteca/imagen/...` ahora exige sesión.** Servía por nombre, sin token,
  cualquier archivo del disco de uploads, privados incluidos. El frontend no lo usaba.
- **Firmas de usuario que se pisaban** (`UsuariosServices::subirFirmaUsuario`). Se subían con el
  nombre original y `overwrite`: si dos personas subían `firma.png`, una quedaba firmando con la
  imagen de la otra. Ahora el `public_id` es `firma_<id_user>_<8 al azar>`.
- **Firmas de historia clínica que no se borraban** (`HistoriaClinicaService`). Se borraban como
  `raw` y se habían subido como `image`; Cloudinary respondía «not found», se tomaba como éxito
  y el archivo quedaba. Ahora se borran como `image`.
- **Firmas de historia clínica por colegio.** Se suben a
  `<carpeta del colegio>/historia-clinica/firmas` (antes, todas a `historia-clinica/firmas`).
- **Sin soporte de archivos legacy de SAMI.**
  - Se quitó el disco `sami` (`config/filesystems.php`) y sus variables de `.env.example`
    (`SAMI_UPLOAD_ROOT`, `SAMI_UPLOAD_URL`). Con `UPLOADS_DISK=sami` las subidas fallan.
  - `descargarFirma` ya no lee firmas legacy (fila de `firmas` con `nombre` y sin `url`). Sin
    `url`, el PDF sale con la línea de firma en blanco.
  - El inicio de sesión silencioso en SAMI (`SamiSsoService`) no cambió: no es de archivos.
- **Validación de la carpeta en «Bases de datos».** `PUT /admin-management/bases-datos/carpeta`
  rechaza una carpeta que contenga el nombre de una carpeta privada
  (`ArchivosPrivados::chocaConCarpetaPrivada`, p. ej. `certificados`): volvería privado todo
  ese colegio. `carnets` y `royal-school` se permiten (no cambian nada).
- **Vite fuera del backend.** Era el esqueleto de Laravel sin uso (apuntaba a
  `resources/css/app.css` y `resources/js/app.js`, que no existen; ninguna vista usa `@vite`).
  Se borraron `package.json` y `vite.config.js`. `composer run setup` ya no corre npm, y
  `composer run dev` levanta solo `serve` y la cola con `npx --yes concurrently` (en local sigue
  haciendo falta Node). El frontend tampoco necesita Vite en un servidor: Vercel lo usa solo
  para compilar.

## Deploy

1. Subir backend y frontend juntos. Si se sube solo el backend, las fotos de carnet y de perfil
   del frontend viejo (que arma la URL de Cloudinary) dan 401 apenas se corra el paso 2.
2. `php artisan cloudinary:privatizar --dry-run` para ver la lista; luego sin `--dry-run`. Hace
   `rename` con `to_type: authenticated` e `invalidate`, no vuelve a subir nada. Usa Admin API
   para listar (1 llamada por cada 500 archivos, cuota por hora). Se puede repetir. Correrlo
   apenas termine el deploy: hasta entonces `deleteFile` busca como `authenticated` lo que sigue
   en `upload` y no lo borra.
3. Revisar que el `.env` de producción no tenga `UPLOADS_DISK=sami` (debe quedar sin definir o
   en `cloudinary`).
4. En la consola de Cloudinary: activar *Security → Strict transformations* y confirmar que
   *Resource list* esté restringido.
5. Con sesión, abrir una foto de carnet, un documento de admisión, una firma y un certificado de
   Perfil, y generar un reporte operativo de inventario con V°B° y un paz y salvo.
6. Contar las firmas legacy que dejan de verse en los PDFs:
   `SELECT COUNT(*) FROM firmas WHERE activo = 1 AND (url IS NULL OR url = '')`.

### Cómo fue el deploy (2026-11-03)

- Se desplegó el código antes de migrar. Durante ~2 horas las imágenes privadas dieron **404**:
  el backend ya las pedía como `authenticated` y en Cloudinary seguían como `upload`. **La
  migración tiene que correr en el mismo momento que el deploy**, no después.
- Peor: cada URL firmada pedida antes de migrar su archivo quedó con el 404 guardado en el CDN,
  y seguía rota ya migrada. Arreglo permanente: las URLs firmadas no llevan `v1/` (la versión
  no entra en la firma, así que cambia la URL sin cambiar la firma).
- Hubo un parche temporal en `/api/public` (si la URL pública aún respondía, redirigía ahí). Se
  quitó al terminar la migración.
- El frontend pide `/api/public/...?c=2` para no usar los 302 que los navegadores guardaron
  (7 días) hacia URLs rotas. **Se puede quitar desde el 2026-11-11** (`localUrl` en
  `src/utils/fileUrl.ts`).
- `cloudinary:privatizar`: 5265 archivos en dos corridas, 0 fallos, y el `--dry-run` posterior
  dio 0 pendientes. Va a ~0,7 archivos por segundo (Cloudinary procesa los `rename` en fila;
  lanzarlos en paralelo no lo acelera): unas 2 horas para 5000 archivos.

## Limitaciones y pendientes

- La firma no vence: quien tenga una URL firmada de una foto la abre (los documentos ya no
  salen firmados al navegador). Para que venza haría falta
  `type: private` + `private_download_url` (sin miniaturas) o token auth (plan pago).
- La firma de la directora y el sello del paz y salvo fueron públicos con una URL fija: puede
  haber copias fuera. Si preocupa, cambiar las imágenes.
- Las firmas que ya se pisaron no se recuperan: hay que pedirles a esos usuarios que vuelvan a
  subir su firma. Se detectan con filas activas de `firmas` que compartan `url`.
- Al cambiar la firma de un usuario, la imagen anterior queda en Cloudinary (privada): la tabla
  `firmas` solo guarda la URL, no el `public_id`.
- Las historias clínicas firmadas sin firma manual apuntan a la fila activa de `firmas`: si la
  psicóloga cambia su firma, esas historias muestran la nueva.
- Las firmas de historia clínica anteriores siguen en `historia-clinica/firmas`, sin separar por
  colegio.
- No se probó contra el Cloudinary real (solo tests con el SDK y `Http::fake`).
- Ya estaba mal antes de este cambio: `composer.lock` no tiene `laravel/boost`, que sí está en
  `require-dev` (`composer validate` lo avisa). Se arregla con `composer update laravel/boost`.

## Archivos

Backend (`royal_back`):

| Archivo | Cambio |
|---|---|
| `app/Services/Cloudinary/ArchivosPrivados.php` | nuevo: regla, `tipoEntrega`, `url` (firma), `firmar`, `chocaConCarpetaPrivada` |
| `app/Http/Middleware/FirmarArchivosPrivados.php` | nuevo: firma las URLs del JSON con sesión |
| `app/Console/Commands/CloudinaryPrivatizarCommand.php` | nuevo: `cloudinary:privatizar {--dry-run} {--carpeta=*}` |
| `bootstrap/app.php` | registra el middleware en el grupo `api` |
| `app/Services/Cloudinary/CloudinaryService.php` | `type` según la regla al subir y borrar; `rutaLocalRoyalSchool` firma |
| `app/Services/Cloudinary/CloudinaryUploadsAdapter.php` | URLs firmadas, `type` al subir/borrar, transformación opcional en `recurso()` |
| `app/Http/Controllers/Documentos/DocumentoController.php` | `verPublico` redirige a Cloudinary firmado; `?descargar=1` |
| `routes/api.php` | `auth:api` en `GET /biblioteca/imagen/...` |
| `app/Services/inventario/InventarioServices.php` | `descargarFirma` firma la URL; sin firmas legacy ni disco `sami` |
| `app/Services/Usuarios/UsuariosServices.php` | `public_id` único para la firma |
| `app/Services/HistoriaClinica/HistoriaClinicaService.php` | borra firmas como `image`; sube bajo la carpeta del colegio |
| `app/Http/Controllers/AdminManagement/BasesDatosController.php` | valida que la carpeta no choque con una privada |
| `app/Services/FileStorageService.php` | comentario (ya no hay disco `sami`) |
| `config/filesystems.php`, `.env.example` | sin disco `sami` |
| `composer.json`, `AGENTS.md` | sin npm/Vite en `setup` y `dev` |
| `package.json`, `vite.config.js` | borrados |
| `tests/Unit/Cloudinary/ArchivosPrivadosTest.php` | nuevo: regla, firma contra el SDK, `firmar`, carpetas que chocan |
| `tests/Unit/Cloudinary/FirmarArchivosPrivadosTest.php` | nuevo: firma con sesión, conserva `{}`, no firma sin sesión |
| `tests/Unit/Cloudinary/CloudinaryUploadsAdapterTest.php` | raíz y fotos de carnet privadas, plantillas públicas |
| `tests/Unit/Cloudinary/RutaLocalRoyalSchoolTest.php` | firma y sello se piden firmados |
| `docs/contexto/infraestructura.md`, `docs/contexto/modulos/{carnets,compras,inventario}.md` | reglas vigentes |

Frontend (`S.A.M.I_frontend`):

| Archivo | Cambio |
|---|---|
| `src/utils/fileUrl.ts` | `esPrivado`; los privados van por `/api/public` también en producción; `?descargar=1` |
| `docs/contexto/componentes.md`, `docs/contexto/modulos/{carnets,gestion-humana,compras}.md` | reglas vigentes |
