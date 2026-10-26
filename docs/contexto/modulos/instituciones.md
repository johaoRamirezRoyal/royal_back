# Instituciones (jardines asociados)

Es un portal para jardines infantiles asociados, que **no son `usuarios`**, donde
diligencian cartas de recomendación de sus egresados aspirantes. Tiene dos controllers:
`InstitucionController` (público, sesión propia) e `InstitucionAdminController` (admin,
`auth:api` + `system:general`).

## Sesión propia (`EnsureInstitucionSession`, alias `institucion.session`)

- Usa un token opaco en `Cache` (`institucion_session_{token}`), con `expires_at` dentro
  del valor, porque `Cache` no expone el TTL restante.
- El TTL es de **12 h**, o de **15 min** en producción.
- Revisa `activo` **en cada request**: desactivar una institución corta su sesión de
  inmediato.
- La cookie es httpOnly y se crea con `HasAuthCookie`.

## Login y correo

- `POST /api/institucion/login` (`id_institucion`, `nit`):
  - El NIT funciona como contraseña y se guarda **hasheado** (`$hidden`).
  - Límite de 5 intentos en 10 minutos por IP e institución.
  - El mensaje de error es genérico, para no permitir enumeración.
- El NIT basta para entrar. **Excepción**: con correo ya verificado y una IP distinta de
  `ultima_ip`, se pide un código enviado a ese correo (`verify-login-otp`).
- `request-email-otp` / `verify-email-otp` solo sirven para el **primer** registro del
  correo (409 si ya hay uno). El correo es único en la BD y se valida antes de enviar el
  código. Si el dominio coincide con `dominio_play_and_learn`, se asigna ese
  `tipo_documento` (`tipoDocumentoParaCorreo()`, también al crear o editar desde el
  admin).
- **Bloqueo**:
  - Se bloquea al pasar `primer_ingreso_at` (el primer login, no la creación) +
    `dias_plazo_bloqueo_correo` sin correo verificado.
  - Bloqueada, solo se rechaza el envío de cartas (403).
  - Desactivar una institución sin correo reinicia `primer_ingreso_at`.
- `configuracion_instituciones` (fila única, `actual()`): `dias_plazo_bloqueo_correo`,
  `correo_notificacion` (lista separada por comas) y `dominio_play_and_learn`.

## Carta de recomendación

- `Institucion::TIPOS_DOCUMENTO = ['coordinador_psicologo', 'play_and_learn']`. Los datos
  se guardan como JSON en `cartas_recomendacion.datos`, con `idioma` (`es` o `en`; Play
  and Learn solo en español). Solo `datos.nombre_estudiante` es obligatorio.
- Primero se guarda; después `generarSubirYNotificar()` genera el PDF, lo sube a
  Cloudinary y avisa, fuera de la transacción.
- `CartaRecomendacionPdfService` recrea fielmente los formatos originales (en
  `src/assets/Admissions/LettersOfRecommendation` del frontend):
  - Las coordenadas van en pt y el logo está en
    `storage/app/public/images/instituciones/logo.png`.
  - "Información de los padres" fuerza un salto de página.
  - Un campo vacío se muestra como `"-"`.
  - La firma (data URL base64) se dibuja como imagen.

## Endpoints

- **Públicos** (`/api/institucion`): `GET /instituciones` (solo `id` y `nombre`),
  `POST /login`, `/resend-login-otp` y `/verify-login-otp`.
- **Con `institucion.session`**: `GET /check`, `POST /logout`, `/request-email-otp`,
  `/verify-email-otp` y `POST|GET /carta-recomendacion`.
- **Admin** (`/api/instituciones-admin`; `/configuracion` va registrado **antes** de
  `/{id}`):

| Gate | Endpoints |
|---|---|
| 106 o 111 | `GET /` (con `cartas_count` y `ultima_carta_at` de `withCount`/`withMax`; en `store`/`update` vienen en 0/null), `GET /{id}/cartas` |
| 106 | `POST /`, `PUT /{id}` (campos `sometimes`), `PUT /estado`, `GET|PUT /configuracion`, `POST /{id}/recordatorio` |

## Permisos

- **106** "Gestión de Instituciones" está otorgada al Super Admin.
- **111** "Ver Instituciones y Documentos" (solo `index` y `cartas`) está otorgada a
  Admisiones (perfil 9).
- Estos ids estuvieron mal hardcodeados como 104/105 (que son de Compras) y se
  corrigieron el 2026-09-22. Ese mismo día se corrigió Acudientes
  (`AcudientesAdminController`), que es **112** y no 106. La migración
  `2026_09_22_100832_restore_perfil_admisiones_...` restauró el permiso del perfil 9
  buscando la opción por nombre.

## Recordatorio (`POST /{id}/recordatorio`)

- Envía `RecordatorioDocumentoInstitucionMail` con el documento esperado, cuántos ha
  enviado y el enlace `frontend_url/admissions?tipo=institucion`.
- Responde 404 si no existe, 422 si está inactiva o no tiene `email` (un correo sin
  verificar sí se acepta) y **429** si llega `MailRateLimitException`.
- **Envía un correo por llamada, a propósito**: el lote lo recorre el frontend y se
  detiene en el primer 429. No lo conviertas en un loop en el servidor sin conservar ese
  corte.
