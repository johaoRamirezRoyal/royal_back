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
  que el frontend no tenga que pedir `/info-perfil` solo por el avatar.
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
