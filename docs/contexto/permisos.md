# Permisos (`cron_opciones` / `cron_permisos`)

Son tablas legacy que se manejan con `DB::table(...)`:
- `cron_opciones` (`id`, `nombre`, `id_modulo`, `activo`, ...): el catálogo de opciones.
- `cron_permisos` (`id_opcion`, `id_perfil`, `activo`): qué perfil tiene cada opción.

Historial, verificación y pendientes del cambio a middleware:
`docs/PERMISOS_MIDDLEWARE_2026_10_20.md`.

## Cada colegio tiene sus perfiles: catálogo común, perfiles y permisos propios

- **El catálogo (`cron_modulos`, `cron_opciones`) es igual en todas las bases.** Es parte
  del código: se crea solo con migraciones, con id explícito, y las migraciones se corren
  en cada base (`php artisan migrate --database=sami_hebreo`).
- **`perfiles` y `cron_permisos` son de cada colegio.** Los números de perfil no coinciden
  entre colegios (Coordinador: 26 en el Royal, 22 en el Hebreo).
- **`perfiles.tipo`** (`App\Enums\TipoPerfil`: `ADMINISTRATIVO`, `DOCENTE`, `OPERATIVO`,
  `ESTUDIANTE`, `ACUDIENTE`, `PROVEEDOR`, `EGRESADO`) dice qué clase de persona agrupa un
  perfil. Lo llenó la migración `2026_11_10_100000` por nombre y se cambia en
  `PUT /permisos/perfiles/{id}/tipo` (opción 28; el Super Admin no se toca). Viaja con la
  sesión como `perfil_tipo` (`UsuarioResource`) y en `POST /permisos/opciones-perfil`.
- **Regla para código nuevo:** no compares números de perfil (salvo
  `PERFIL_SUPER_ADMIN`). Para "¿es docente / estudiante / operativo?" usa el tipo; para
  "¿puede hacer X?" usa una opción. Herramientas:
  - `$usuario->esTipo(TipoPerfil::DOCENTE)` para un usuario.
  - `whereIn('perfil', Perfil::idsDeTipo(TipoPerfil::ESTUDIANTE))` para filtrar. Sin perfiles
    de ese tipo devuelve `[]` (falla cerrado). En un `Rule::exists`, ponlo dentro de
    `->where(fn ($q) => ...)` para que consulte al validar y no al armar las reglas.
  - `Perfil::idParaCrear(TipoPerfil::PROVEEDOR)` para crear un usuario de ese tipo (el
    perfil de menor id del tipo).
  - `Permiso::ruta([...], [TipoPerfil::DOCENTE])` en una ruta (regla `tipo:DOCENTE`).
  - `GET /usuarios/paginados?tipos[]=ESTUDIANTE`.
- **Acciones = opciones (fase 3, migración `2026_11_10_110000_seed_opciones_roles`).** Lo que
  decidía un número de perfil es una opción, otorgada por nombre del perfil en cada base:
  155 Coordinación de nivel (antes 26), 156 Dirección administrativa (antes 7), 157 Compras
  de todos los niveles (antes 1/2), 158 Revocar llegadas tarde de trabajadores (antes 1/8),
  159 Responsable de bloques, 160 Puede tener asignaturas (docentes y psicólogas).
  - **155 y 156 son restrictivas** (`OPCIONES_RESTRICTIVAS`): encierran en un nivel, así
    que Super Admin no las tiene.
  - Para el usuario de la petición: `PermisosPerfil::tiene($request, ...)`. Para otro usuario
    (servicios): `PermisosPerfil::perfilTiene($usuario->perfil, ...)`. Para "a quién se le
    avisa" o "quién puede ser elegido": `whereIn('perfil', PermisosPerfil::perfilesCon(...))`
    (no incluye al Super Admin implícito; agrégalo si debe estar).
- **Segunda tanda (migración `2026_11_10_120000_seed_opciones_roles_avisos`):** 161
  Evaluaciones — administración total (antes 1, 2 y 8), 162 Realizar cualquier evaluación
  (antes 1 y 8), 163 Avisos de llegadas tarde de trabajadores (antes 8), 164 Avisos de
  estudiantes en el límite (antes 20 y 11), 165 Asistencia de nivel (antes 11), 166 Sus permisos
  avisan a la dirección administrativa (antes 10, 23, 32, 33 y 11), 167–169 Psicóloga de
  preescolar/primaria/bachillerato en Admisiones (antes 21, 24 y 25) y 170 Gestionar
  inscripciones como personal (antes los NOMBRES "administrador"/"admisiones" en
  `TokenExchangeController`, que ahora además usa la base del colegio del token).
- **El único número de perfil que queda en el código es el Super Admin (1).** Ventas en
  Compras usa la opción 105, la misma que ya exigía el backend.

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
