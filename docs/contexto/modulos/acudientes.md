# Acudientes (`/acudientes`, opción 175)

`AcudientesController` + `AcudientesService` (`routes/api/acudientes.php`). Frontend:
`/gestion-academica/acudientes`. Reemplaza `AcudientesAdminController` (`/acudientes-admin`, 112,
eliminado) y, en el menú, «Vinculación Estudiante - Padre» (131).

- `GET /` — `UsuariosServices::mostrarAcudientesPaginados` (tipo ACUDIENTE, con `hijos_count` de
  `estudiantes_padres` activos). `solo_autoregistrados=1` filtra `origen_registro='admisiones'`.
- `POST /`, `PUT /{id}` — documento, nombre, apellido, correo (únicos documento y correo en
  `usuarios`), teléfono. Perfil `Perfil::idParaCrear(TipoPerfil::ACUDIENTE)`; la contraseña queda
  vacía (null, no se puede entrar) hasta que el acudiente la crea: el colegio nunca la escribe. El correo se guarda en minúsculas.
- `PUT /estado` — `{ids, estado}`.
- `POST /importar` — creación masiva desde Excel (`AcudientesService::importarExcel`, máximo 500
  filas): A documento, B nombres, C apellidos, D correo, E teléfono, F documento del hijo. Crea
  (mismas reglas que el formulario: `AcudientesService::reglas()`), o actualiza solo las columnas
  llenas si el documento ya es de un acudiente, y vincula al hijo con
  `VinculacionAcudienteService::vincular` (ya vinculado no cuenta como error). Documentos
  comparados sin espacios ni puntos (una consulta por tipo para todo el archivo). Los acudientes
  nuevos quedan sin contraseña (`pass` null: `Hash::check` siempre falla); hashear una al azar
  costaba ~0,3 s por fila. 500 filas ≈ 6 s. No envía el acceso al portal. Test:
  `ImportarAcudientesTest`. Reemplaza a `/vinculacion-acudientes/importar` (eliminado);
  `/usuarios/importar` queda sin botón en el frontend.
- `POST /{id}/acceso-portal` — token en `password_reset_tokens` (7 días, el de recuperar dura 60
  min) y `AccesoPortalPadresMail` (vista `emails.accesoPortalPadres`: logo, color, imagen y nombre de la
  marca del colegio; sin marca activa, OMNIA) con botón a `/password/restore?token&email&colegio=<slug>`. El slug sale de
  `ColegioAdmisionService::colegioDeLaSesion()` (colegio activo cuya connection es la de la
  petición); sin colegio responde error. Solo acudientes activos. Si el hosting llega a su límite
  de correos por hora (`MailRateLimitException`, ver `noticias.md`) responde **429**: el envío en
  lote del frontend se detiene ahí. Ese límite lo comparten todos los correos del sistema
  (códigos de inicio de sesión incluidos); por eso el frontend envía máximo 50 por lote.
- Hijos: `/vinculacion-acudientes` (ver `gestion-academica.md`) acepta la 131 **o** la 175.
- Migración `2026_11_18_120000_seed_opcion_acudientes`: crea la 175 y la otorga a los perfiles con
  la 112 o la 131.
