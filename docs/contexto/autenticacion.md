# Autenticación

## JWT en cookie

1. El login genera un JWT con los claims `system`, `user_id`, `nombre`, `apellido`,
   `correo` y `perfil`.
2. El JWT se guarda en una cookie httpOnly: `token` (sistema general) o
   `admissions_token` (admisiones). Estas dos cookies **no** se encriptan
   (`encryptCookies(except: [...])` en `bootstrap/app.php`).
3. `JwtFromCookie` (agregado al inicio del grupo `api`) lee la cookie y pone
   `Authorization: Bearer`.
4. `auth` (`Authenticate`) responde 401 en JSON; `system` (`ValidateSystem`) responde 403
   si el claim `system` no coincide.

- `JwtService` es un singleton (`generateToken($user, $system, $extraClaims)`,
  `generateAdmissionsToken`, `refreshToken`, `invalidateToken`, `getPayload`,
  `authenticate`).
- Las cookies se crean con el trait `HasAuthCookie`:
  `makeCookie($token, $nombre, $minutes = 1 día)`.
- `config/jwt.php`: `ttl` 60 min, `refresh_ttl` 14 días.
- `UsuarioResource` (respuesta de `/auth/check` y del login) incluye `foto_perfil`, para
  que el frontend no tenga que pedir `/info-perfil` solo por el avatar, y `carpeta_archivos`:
  la carpeta raíz del colegio de la sesión en Cloudinary, con la que el frontend arma las URLs
  de los archivos (ver `infraestructura.md`, "Carpeta raíz por colegio").
- `AuthServices` es delgado: solo cubre el registro y los dispositivos confiables. El
  resto (login, check, tokens) sigue en `AuthController`/`JwtService`.

## Verificación de equipo nuevo (`dispositivos_confiables`)

Solo aplica al login del sistema general; admisiones e instituciones tienen su propio OTP.

- `login()` compara la cookie `device_token` (sha256 contra `dispositivos_confiables`).
  - Si coincide, otorga la sesión como siempre, incluido el SSO silencioso a SAMI.
  - Si no, responde **200** con `data.requires_otp: true`, un token de pausa
    (`auth_login_pending_{token}`, 15 min) y un código de 5 dígitos
    (`auth_login_otp_{token}`, 5 min) enviado por correo.
- `POST /api/auth/login/verify-otp` (`token`, `code`): máximo 5 intentos. Si el código es
  válido, registra el dispositivo y pone la cookie `device_token` por **90 días**. No
  repite el SSO de SAMI, porque la contraseña solo viaja en el `login()` original.
- `POST /api/auth/login/resend-otp`: máximo 3 envíos en 5 minutos por usuario.
- Ambos endpoints son públicos.
- En local se puede apagar con `APP_ENV=local` **y** `AUTH_DEVICE_OTP=false`. Con
  `MAIL_MAILER=log`, el código queda en `storage/logs/mail/`.

## Llave maestra (`llaves_maestras`, connection `admin_management`)

Un Super Admin genera una llave de un solo uso para entrar como otro usuario sin su
contraseña (para soporte).

- La tabla vive en `admin_management`, transversal a los colegios, así que **no tiene FK**
  a `usuarios` y guarda `connection_objetivo`. Solo se guarda el hash.
- `LlaveMaestraController` (`/api/admin-management/llaves-maestras`) exige solo
  `perfil === 1`; **no** aplica la lista de correos `adminManagementEmails` que sí aplica
  el frontend. Para exigirla, copia el patrón de `BasesDatosController`.
- `POST /api/auth/master-key/redeem` es público, con límite de 10 intentos en 10 minutos
  por IP. No lee ni escribe `device_token` y no hace SSO. El JWT resultante lleva
  `via_llave_maestra: true` y `generado_por`, que `AuthController::check()` devuelve para
  que el frontend muestre su aviso.
- `revocar()` no borra la fila: pone `expira_en = now()` para conservar la auditoría.

## Página de acceso en el navegador (`PaginaAccesoNavegador`)

Si alguien abre una ruta de la API en el navegador, un 401, 403, 404 o 503 se muestra
como una página con el diseño de OMNIA (`resources/views/errores/acceso.blade.php`). Los
textos están en `AVISOS` del middleware.

| Código | Botón |
|---|---|
| 401 | login del frontend (`FRONTEND_URL`) |
| 403 | `/home` del frontend |
| 404 | login del frontend |
| 503 (`MANTENIMIENTO`) | "Volver a intentar" (recarga la misma dirección; conserva `Retry-After`) |

- Es el middleware más externo del grupo `api`, así que cubre todas las fuentes de esos
  códigos sin tocarlas. Un middleware o controller nuevo que responda 401/403/404 queda
  cubierto solo.
- **Las rutas que no existen** no pasan por el grupo `api`: su 404 lo convierte el
  `respond()` de `bootstrap/app.php` con `PaginaAccesoNavegador::pagina()`, solo cuando no
  hubo ruta, para no convertirlo dos veces.
- **Solo actúa si `Accept` pide `text/html` de forma explícita.** `apiFetch` (con
  `application/json`), `fetch()` sin cabeceras y las imágenes (`*/*`) siguen recibiendo
  JSON. No lo cambies por `acceptsHtml()`, que también acepta `*/*`.
- Test: `tests/Feature/PaginaAccesoNavegadorTest.php`.

## Admisiones: en nombre de qué acudiente actúa una petición

- `AdmissionsController::acudienteDeLaSesion($request, $campo)`. Para un acudiente es el
  usuario de la sesión, y lo que traiga el cuerpo se ignora. Para el personal en modo
  admin (token de `TokenExchangeController`, `source=token_exchange`) es el `acudiente_id`
  firmado en el token; si no viene, se toma el del cuerpo.
- Lo usan crear inscripción (`POST /admisiones/inscripcion`) y listar las del acudiente
  (`POST /admisiones/inscripcion/usuario`). Antes tomaban el id del cuerpo: cualquiera
  podía crear o ver inscripciones de otro acudiente.
- Test: `tests/Unit/AdmisionesAcudienteSesionTest.php`.
- **Un acudiente solo toca sus propias inscripciones**: middleware
  `inscripcion.acudiente` (`App\Http\Middleware\InscripcionDelAcudiente`) sobre `/admisiones`
  y `/compartido`. Solo restringe tokens de acudiente (system=admissions sin
  token_exchange); el personal del sistema general y el modo admin pasan igual.
  - Cada ruta declara en `REGLAS` qué campos traen ids y de qué tabla (también listas como
    `familiares.*.id_inscripcion` y el `codigo`/`public_id`); todos deben llevar a
    inscripciones con `id_usuario_registro` = el acudiente. Historia clínica se resuelve por
    sección (`HISTORIA_CLINICA`).
  - Una ruta que no esté en `REGLAS` queda **prohibida** para el acudiente (citas,
    listados de psicología, correo informativo...). Una ruta nueva que use la página del
    acudiente debe agregarse ahí, o le responderá 403.
  - El estado solo puede pasar a PENDIENTE (enviar la admisión).
  - Las escrituras (POST/PUT/DELETE) dependen del **estado actual** de la inscripción
    (`estadosDe`): datos generales solo en BORRADOR o PENDIENTE; historia clínica
    (`compartido/historia-clinica/*`) solo en EN REVISION (PSICOLOGIA). Si no, 409 con
    "recarga la página". Así, si el personal cambia el estado desde Reportes con la página
    del acudiente abierta, lo que este guarde después no se aplica. Las lecturas y la
    creación de inscripciones (`SIN_CONTROL_DE_ESTADO`) no se revisan. Es la misma regla
    que `isLocked`/`isPsicoLocked` en el frontend (`DetailView`): deben coincidir.
  - Los documentos que sube el colegio (`DOCUMENTOS_DEL_COLEGIO`: 24 Recomendación de
    Coordinador, 20 Recomendación Psicólogo) no los puede subir ni borrar el acudiente.
  - Test: `tests/Unit/InscripcionDelAcudienteTest.php`.
- **`/api/compartido` y las dos cookies**: `JwtFromCookie::tokenCompartido` usa la cookie del
  sistema de la página que llama (header `X-Sistema`, que pone `apiFetch` del frontend).
  Sin header, primero la de admisiones. Antes siempre prefería la de admisiones: con una
  sesión de acudiente abierta en el mismo navegador, Reportes de admisiones (personal)
  hablaba como ese acudiente y `inscripcion.acudiente` le negaba cambiar el estado. Test:
  `tests/Unit/JwtFromCookieCompartidoTest.php`.

