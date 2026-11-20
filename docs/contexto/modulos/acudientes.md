# Acudientes (`/acudientes`, opción 175)

`AcudientesController` + `AcudientesService` (`routes/api/acudientes.php`). Frontend:
`/gestion-academica/acudientes`. Reemplaza `AcudientesAdminController` (`/acudientes-admin`, 112,
eliminado) y, en el menú, «Vinculación Estudiante - Padre» (131).

- `GET /` — `UsuariosServices::mostrarAcudientesPaginados` (tipo ACUDIENTE, con `hijos_count` de
  `estudiantes_padres` activos). `solo_autoregistrados=1` filtra `origen_registro='admisiones'`;
  `acceso=enviado|sin_enviar` filtra por `usuarios.acceso_portal_enviado_at` (cuándo se le envió el
  correo de acceso; también ordenable).
- `POST /`, `PUT /{id}` — documento, nombre, apellido, correo (únicos documento y correo en
  `usuarios`), teléfono. Perfil `Perfil::idParaCrear(TipoPerfil::ACUDIENTE)`. **Contraseña inicial = número de
  documento sin puntos ni espacios** (`AcudientesService::nuevo`, bcrypt de 10 rondas: lo que la
  protege es el código por correo de equipo nuevo y el límite de intentos, no el costo del hash).
  El colegio no la escribe. El correo se guarda en minúsculas.
- `PUT /estado` — `{ids, estado}`.
- `POST /importar` — creación masiva desde Excel (`AcudientesService::importarExcel`, máximo 500
  filas): A documento, B nombres, C apellidos, D correo, E teléfono, F documento del hijo. Crea
  (mismas reglas que el formulario: `AcudientesService::reglas()`), o actualiza solo las columnas
  llenas y distintas de lo guardado si el documento ya es de un acudiente (repetir los mismos datos
  no cuenta como actualizado), y vincula al hijo con
  `VinculacionAcudienteService::vincular` (ya vinculado no cuenta como error). La respuesta trae
  `vinculos` (fila, acudiente, estudiante, curso) para revisar cada vínculo nuevo: un documento de
  hijo mal escrito puede coincidir con otro estudiante y ese acudiente vería a un niño ajeno. Documentos
  comparados sin espacios ni puntos (una consulta por tipo para todo el archivo). Los acudientes
  nuevos quedan con su documento como contraseña: un hash de ~60 ms por fila, 500 filas ≈ 40 s
  (`set_time_limit(180)`). No envía el acceso al portal. Test:
  `ImportarAcudientesTest`. Reemplaza a `/vinculacion-acudientes/importar` (eliminado);
  `/usuarios/importar` queda sin botón en el frontend.
- `POST /{id}/acceso-portal` — token en `password_reset_tokens` (7 días, el de recuperar dura 60
  min) y `AccesoPortalPadresMail` (vista `emails.accesoPortalPadres`: logo, color, imagen y nombre de la
  marca del colegio; sin marca activa, OMNIA) con botón a `/password/restore?token&email&colegio=<slug>`. El slug sale de
  `ColegioAdmisionService::colegioDeLaSesion()` (colegio activo cuya connection es la de la
  petición); sin colegio responde error. Solo acudientes activos. El correo cambia según
  `estadoContrasena`: `documento` (sigue con la inicial: se la recuerda y le pide cambiarla),
  `sin_contrasena` (pide crearla) o `propia` (ya tiene; el botón sirve si la olvidó). Si el hosting llega a su límite
  de correos por hora (`MailRateLimitException`, ver `noticias.md`) responde **429**: el envío en
  lote del frontend se detiene ahí. Si el correo sale, guarda `acceso_portal_enviado_at = now()`
  (migración `2026_11_20_100000_add_acceso_portal_enviado_to_usuarios_table`, en cada base de
  colegio; rellena los envíos anteriores con los enlaces de 7 días de `password_reset_tokens`). Ese límite lo comparten todos los correos del sistema
  (códigos de inicio de sesión incluidos); por eso el frontend envía máximo 50 por lote y el
  backend corta en **100 accesos por hora por usuario** (`RateLimiter`, también 429, con su propio
  mensaje).
- Hijos: `/vinculacion-acudientes` (ver `gestion-academica.md`) acepta la 131 **o** la 175.
- Migración `2026_11_18_120000_seed_opcion_acudientes`: crea la 175 y la otorga a los perfiles con
  la 112 o la 131.
