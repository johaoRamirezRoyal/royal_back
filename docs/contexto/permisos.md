# Permisos (`cron_opciones` / `cron_permisos`)

Son tablas legacy que se manejan con `DB::table(...)`:
- `cron_opciones` (`id`, `nombre`, `id_modulo`, `activo`, ...): el catálogo de opciones.
- `cron_permisos` (`id_opcion`, `id_perfil`, `activo`): qué perfil tiene cada opción.

Historial, verificación y pendientes del cambio a middleware:
`docs/PERMISOS_MIDDLEWARE_2026_10_20.md`.

## Cómo se protege un endpoint

- **Ids en un solo lugar: `App\Support\Permiso`**, con una constante por opción
  (`Permiso::INVENTARIO = 12`, con el nombre de `cron_opciones` al lado) y por perfil
  (`PERFIL_SUPER_ADMIN`, `PERFILES_DOCENTE`, ...). Si un id cambia en la BD, se cambia
  solo ahí. Las constantes `OPCION_*` de cada controller apuntan a esa clase.
- **Ruta**: `->middleware(Permiso::ruta([Permiso::X, ...], [Permiso::PERFIL_Y, ...]))`.
  El middleware (`RequirePermiso`) deja pasar si el perfil tiene **cualquiera** de esas
  opciones o es uno de esos perfiles; si no, responde 403. Las opciones del perfil se
  cargan una vez por petición (`PermisosPerfil`).
- **Controller**, con la misma regla:
  - Por método: trait `Concerns\VerificaPermisos` y
    `if ($rechazo = $this->sinAcceso($request, self::OPCION_X, ...)) return $rechazo;`.
  - Por controller: `implements HasMiddleware` con `only`/`except` (lo usan
    `GestionAcademicaController`, `BibliotecaController` y `HikvisionController`).
  - **Nunca en el constructor**: Laravel crea el controller antes de correr el middleware
    de la ruta.
- **Lo que depende de los datos** (qué ve cada quien, opciones restrictivas como la 135,
  "solo hoy" con la 101, editar solo lo propio) se resuelve dentro de la acción con
  `PermisosPerfil::tiene($request, ...)`, como en
  `ObservacionEstudianteController::acceso()`. La ruta solo responde "¿puede entrar?".
- **Sin middleware a propósito**: autoservicio (`/info-perfil`, `/documentos-varios`),
  catálogos de lectura (`/cursos`, lecturas de `/usuarios` y `/areas`) y
  `/compartido/historia-clinica`.

## Reglas

- Antes de proteger un endpoint, busca en `src/pages/` del frontend qué otros módulos lo
  consumen. Un endpoint de lectura compartido (dropdowns, catálogos) acepta el OR de
  todas las opciones válidas.
- **Super Admin (perfil 1)** recibe todas las opciones menos
  `Permiso::OPCIONES_RESTRICTIVAS` (hoy, la 135). Una opción que *quite* capacidades se
  agrega ahí. `crearPermiso`/`eliminarPermiso` rechazan el perfil 1 con 422.
- **Para todo otro perfil es fail-closed**: una opción sin filas en `cron_permisos` no la
  tiene nadie. Le pasó a "Metricas Asistencias" (127).
- **Crear una opción**: sigue el patrón de
  `2026_08_18_100000_seed_opcion_llegadas_tarde_recepcion.php` (`up()` que inserta en
  `cron_opciones` y otorga en `cron_permisos`, y un `down()` simétrico).
  - Es preferible insertar el **id explícito** y fallar si está ocupado (como
    `seed_opcion_consulta_asistencias_clases`, con id 132), o buscar la opción por nombre.
  - **No supongas el resultado de `insertGetId`**; ya falló varias veces:
    - Noticias quedó en 69 ("NEWS Royal"), no en 107 (que es "Año Escolar y Periodos").
    - Instituciones quedó en 106/111; 104/105 son de Compras.
    - Acudientes quedó en 112, no en 106.
    - Mis áreas comunes es 119, no 117/118.
  - Volver a correr migraciones `seed_opcion_*` sobre un dump importado creó 19 opciones
    duplicadas (126–145) que se borraron el 2026-09-16; 146/147 se renumeraron a 127/128.
    Si un id no cuadra, sospecha primero de eso.
- Antes de cerrar un cambio, revisa quién tiene la opción hoy:
  ```
  php artisan tinker --execute="foreach (DB::table('cron_permisos as p')->join('perfiles as pf','pf.id_perfil','=','p.id_perfil')->where('p.id_opcion',N)->where('p.activo',1)->pluck('pf.nombre') as \$n) echo \$n . PHP_EOL;"
  ```

## Endpoints de permisos

- `GET /api/usuarios/mis-permisos` (`PermisosPerfil::opciones`): los ids de las opciones
  del perfil en sesión. El frontend los pide una vez por sesión. Este endpoint no
  protege nada.
- `/permisos` (opción 28): asignar y revocar. Revocar borra todas las filas duplicadas
  del par opción/perfil.
- CRUD de módulos y opciones (`/permisos/modulos`, `/permisos/opciones`): solo perfil 1
  literal (`soloSuperAdmin()`). Crear una opción ahí no protege nada hasta que el código
  use su id. `eliminarOpcion` borra en cascada sus `cron_permisos`.
