# Acudientes (`/acudientes`, opción 175)

`AcudientesController` + `AcudientesService` (`routes/api/acudientes.php`). Frontend:
`/gestion-academica/acudientes`. Reemplaza `AcudientesAdminController` (`/acudientes-admin`, 112,
eliminado) y, en el menú, «Vinculación Estudiante - Padre» (131).

- `GET /` — `UsuariosServices::mostrarAcudientesPaginados` (tipo ACUDIENTE, con `hijos_count` de
  `estudiantes_padres` activos). `solo_autoregistrados=1` filtra `origen_registro='admisiones'`.
- `POST /`, `PUT /{id}` — documento, nombre, apellido, correo (únicos documento y correo en
  `usuarios`), teléfono. Perfil `Perfil::idParaCrear(TipoPerfil::ACUDIENTE)`; la contraseña es
  aleatoria e inservible: el colegio nunca la escribe. El correo se guarda en minúsculas.
- `PUT /estado` — `{ids, estado}`.
- `POST /{id}/acceso-portal` — token en `password_reset_tokens` (7 días, el de recuperar dura 60
  min) y `GenericMail` con botón a `/password/restore?token&email&colegio=<slug>`. El slug sale de
  `ColegioAdmisionService::colegioDeLaSesion()` (colegio activo cuya connection es la de la
  petición); sin colegio responde error. Solo acudientes activos.
- Hijos: `/vinculacion-acudientes` (ver `gestion-academica.md`) acepta la 131 **o** la 175.
- Migración `2026_11_18_120000_seed_opcion_acudientes`: crea la 175 y la otorga a los perfiles con
  la 112 o la 131.
