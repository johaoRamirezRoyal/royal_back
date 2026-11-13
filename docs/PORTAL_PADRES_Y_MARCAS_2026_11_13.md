# Portal para padres y marcas unificadas (2026-11-13)

Cambio en `royal_back` y en el frontend (`S.A.M.I_frontend`). Las reglas vigentes están en
[`contexto/autenticacion.md`](contexto/autenticacion.md) («Login multi-colegio y portal para
padres») y, del lado del frontend, en `docs/contexto/modulos/portal-padres.md` y
`docs/contexto/autenticacion.md`. Este documento explica qué se cambió, por qué y qué falta
hacer en el despliegue.

## Por qué

- Los acudientes ya eran usuarios del sistema general (tipo ACUDIENTE, vinculados en
  `estudiantes_padres`), pero entraban al panel administrativo con el sidebar del personal.
- Usan correos personales (gmail, hotmail, outlook...). Eso rompía tres cosas que dependían del
  dominio del correo:
  - la **marca**: un `@gmail.com` no tiene marca, así que el login, el portal y los PDF salían
    sin logo o con el de Royal School;
  - el **login**: el correo se buscaba base por base y se quedaba con la primera; el mismo correo
    en dos colegios entraba siempre al primero, y si la contraseña no coincidía ahí, fallaba;
  - **recuperar contraseña**: exigía `@royalschool.edu.co` y solo buscaba en la base por defecto.
- La identidad de cada institución estaba duplicada en `marcas_dominio` y `colegios_admision`, y
  no coincidía (Royal School tenía dos logos y dos colores).

## Qué cambió

### Login de padres por colegio (`/:colegio/padres`)

- `POST /api/auth/login` acepta `colegio` (slug de `colegios_admision`): busca el correo solo en
  la base de ese colegio, responde 404 si el slug no existe y 403 si el usuario no es ACUDIENTE.
  El slug se valida en `ColegioAdmisionService::colegioConUsuarios`, el mismo que usa recuperar
  contraseña.
- Sin slug (el personal en `/`), el login recorre las bases como antes, pero **si la contraseña o
  el estado no coinciden en una, sigue con la siguiente** (`AuthServices::resolverUsuarioMultiTenant`).
- Índice en `usuarios.correo` en todas las bases con usuarios: el login leía la tabla completa.

### Recuperar contraseña por colegio

- `/auth/password/restore`, `validate-token` y `update-password` aceptan el mismo `colegio`:
  cambian a la base de ese colegio y no exigen correo institucional.
- El enlace del correo lleva `?colegio=` (`PasswordRestore` → `SendPasswordRestore` →
  `PasswordRestoreEmail`) y la página de restablecer vuelve a `/:colegio/padres`.
- Sin slug, igual que antes (base por defecto, solo `@royalschool.edu.co`).

### Marcas en una sola tabla

- Tabla nueva **`marcas`** (`admin_management`): nombre, lema, color, logo, logo secundario,
  imagen de fondo, activo. `marcas_dominio` queda como dominio → marca (`id_marca`, varios
  dominios por marca) y `colegios_admision` como slug → marca (`id_marca`).
- `MarcaService` / `MarcaController` (`/api/branding/marcas`, cada marca con sus `dominios[]`)
  reemplazan el CRUD de `MarcaDominioController`, que se borró. `GET /api/branding/dominios`
  queda como lista de solo lectura (dominio + nombre de su marca) para los banners.
- `MarcaDominioService` solo resuelve. `resolverPorCorreo` mantiene su contrato (lo usan la
  sesión, los PDF y los Excel) y, si el dominio no tiene marca, **usa la del colegio de la base
  de la sesión** (solo con sesión).
- El branding por slug (`/api/admissions/colegio/{slug}/branding`) sale de la marca del colegio
  e incluye `imagen_marca`. `UsuarioResource` también trae `imagen_marca`.
- `/colegios` (antes «Colegios de admisión») queda como registro general de slugs: slug, base,
  calendario y marca (obligatoria). La tabla, el modelo y la API conservan el nombre viejo.

### Portal para padres

`PortalPadresController`, solo tipo ACUDIENTE. Todo id de estudiante se valida contra
`estudiantes_padres` activo (404 si no es su hijo).

| Endpoint | Devuelve |
|---|---|
| `GET /api/portal-padres/hijos` | hijos activos: id, nombre, apellido, curso (sin documento) |
| `GET /api/portal-padres/hijos/{id}/semana` | `llegadas`: historial completo (fecha, hora, justificada, revocado, límite, total del periodo, periodo); `asistencias`: excepciones en clases dictadas (fecha, estado, asignatura, hora) |

**El acudiente no ve** (decisión del 2026-11-13): el documento de sus hijos, observaciones, la nota libre del personal
(`observacion` de llegadas tarde y de asistencia), soportes de excusas ni correos. Los endpoints
no devuelven esos campos.

### Frontend (resumen)

- `/:colegio/padres`: la misma pantalla de login con la marca del colegio, para correos personales;
  sin Google ni llave de acceso.
- `/portal-padres`: «La semana del hijo» (friso con la marca, un hijo a la vez, lunes a viernes).
  `AppLayout` redirige ahí a todo ACUDIENTE.
- `/marcas` reemplaza `/marcas-dominio`; `/colegios` reemplaza `/colegios-admision`. Las rutas
  viejas no redirigen (solo las usa el Super Admin).

## Despliegue

1. Subir backend y frontend juntos: el frontend nuevo llama a `/api/branding/marcas` y a los
   endpoints del portal, y el backend nuevo ya no tiene el CRUD de `/api/branding/dominios`.
2. `php artisan migrate`:
   - `2026_11_13_100000_add_index_correo_to_usuarios_table`: índice en `usuarios.correo` en
     **cada** base con usuarios (`mysql`, `sami_hebreo`), solo si no existe.
   - `2026_11_13_110000_create_marcas_table` (`admin_management`): crea `marcas`, copia cada
     marca por dominio y enlaza cada colegio con la marca de su mismo nombre (gana lo de Marcas por
     dominio; del colegio solo se toma lo que le falta, p. ej. el logo secundario). SQL y detalle
     en `migraciones_sql.sql`.
3. Revisar en `/marcas` que no hayan quedado dos marcas para la misma institución (pasa si había
   dos dominios con el mismo nombre): mover el dominio a una y borrar la otra.
4. Crear en `/colegios` el colegio de cada base que tenga acudientes (hoy solo existe
   `royalschool`; falta el de `sami_hebreo`) y elegir su marca. Sin esa fila no hay enlace del
   portal ni respaldo de marca para ese colegio.
5. Compartir con las familias el enlace `https://<dominio>/<slug>/padres`. Cada acudiente necesita
   una cuenta ACUDIENTE vinculada a sus hijos (Vinculación Estudiante - Padre).
6. Después, en una migración aparte: borrar las columnas de identidad viejas de `marcas_dominio`
   (nombre, descripción, color, logo, imagen) y de `colegios_admision` (nombre, descripción,
   color, logos). Se dejaron para que el código anterior no fallara durante el despliegue; ya no
   se leen ni se escriben.

Al reemplazar una imagen en `/marcas` se borra la anterior de Cloudinary: el código viejo que aún
apunte a ella la mostraría rota mientras dura el despliegue.

## Pendientes

- **Fotos del curso** (evidencias de clase o actividad): no existe. Decisiones abiertas en
  `docs/contexto/modulos/portal-padres.md` del frontend.
- **Notas / boletín** en el portal: depende de `feat/notas-cualitativas`.
- `GET /api/gestion-academica/asistencias-clase` sin `id_horario_clase` responde 500 en vez de
  un error de validación (encontrado durante las pruebas, sin corregir).

## Pruebas en local

En la base local (`sami_royal`) quedaron datos de prueba que no van a producción: un acudiente
`padre.prueba@gmail.com` (id 11670) vinculado a dos estudiantes, y para los cursos de
`prueba.docente@royalschool.edu.co` (id 11658: Cuarto A, Quinto A, Tercero B) un horario de dos
clases por curso, su asistencia del primer periodo, llegadas tarde y observaciones. Se borran por
id con el SQL de limpieza que se generó junto con ellos.
