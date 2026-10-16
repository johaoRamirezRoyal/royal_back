# AGENTS.md — Royal Backend (OMNIA.)

Laravel 12 REST API (PHP ^8.2) para gestión escolar. JWT via `tymon/jwt-auth`.

## Quick start

```bash
composer run setup        # install, .env, migrate, key:generate, npm install/build
composer run dev          # concurrently: serve + queue:listen --tries=1 --queue=emails,default + npm run dev
composer run test         # config:clear + php artisan test
php artisan serve         # dev server on localhost:8000
```

SIEMPRE DEBES ESTAR EN LA RAMA MAIN PARA CADA CAMBIO QUE DEBES REALIZAR.

## Registro manual de SQL de migraciones (`migraciones_sql.sql`)

**Regla fija para cualquier sesión que cree una migración nueva en este repo**:
después de escribir el archivo en `database/migrations/`, anexar al final de
`migraciones_sql.sql` (raíz del repo) el SQL plano equivalente a su `up()`,
con un encabezado `-- ---` que indique el nombre exacto del archivo de
migración y un comentario breve de qué hace y por qué (mismo criterio que los
comentarios ya presentes ahí). No hace falta tocar `down()`.

- El SQL debe ser el que realmente ejecuta Laravel, no una aproximación —
  para obtenerlo sin aplicar la migración dos veces, usar `pretend()`:
  ```bash
  php artisan tinker --execute="
  \$m = require database_path('migrations/NOMBRE_DEL_ARCHIVO.php');
  \$queries = DB::connection()->pretend(function() use (\$m) { \$m->up(); });
  foreach (\$queries as \$q) { echo \$q['query'] . ';' . PHP_EOL; }
  "
  ```
  Si la migración usa `insertGetId()` (como el patrón de seed de
  `cron_opciones`/`cron_permisos`, ver más abajo), `pretend()` no puede
  resolver el id generado — reescribir esa parte a mano con
  `SET @variable = LAST_INSERT_ID();` para que el SQL quede ejecutable de
  verdad, y anotar los ids reales que terminó asignando el autoincrement
  (correr la migración de verdad y consultar la tabla) en un comentario final.
- `migraciones_sql.sql` es solo documentación/histórico — nunca se ejecuta
  contra la BD como script, y no reemplaza correr `php artisan migrate`.
  Sirve para poder auditar el DDL acumulado sin abrir cada archivo PHP, y
  como referencia para aplicar los mismos cambios a mano en otro entorno si
  hiciera falta.
- Motivo de esta práctica: la BD real es externa/compartida entre varios
  entornos (ver "DB externa" más abajo) — tener el SQL crudo centralizado
  facilita revisar o portar cambios de esquema sin depender de correr
  Laravel.

## Arquitectura general

- **Lenguaje**: Español — comentarios, mensajes de error, commits, docs.
- **API 100% JSON** — sin vistas Blade (excepto emails).
- **DB externa** — migraciones mínimas (14), tablas core preexistentes.
- **Sin tests** — `tests/` no existe, solo `phpunit.xml`.

## Mapa de rutas (`routes/api.php`)

### Sin autenticación
| Prefix | File | Uso |
|--------|------|-----|
| `/` | inline | Welcome JSON |
| `POST /pushNotification` | inline | Hikvision test |
| `/auth` | `api/auth.php` | login, register, check, password restore |
| `GET /biblioteca/imagen/{carpeta}/{filename}` | inline | Imagen pública |
| `/admissions` | `api/admissions.php` | Flujo público de admisiones |

### `auth:api` (compartido — ambos sistemas)
| Prefix | File |
|--------|------|
| `/documentos` | inline |
| `/compartido` | inline + `api/anioAcademico.php` + `api/historiaClinica.php` |

### `auth:api` + `system:admissions`
| Prefix | File |
|--------|------|
| `/admisiones` | `api/admisiones.php` |
| `/admisiones/tipos-documentos` | `api/TipoDocumentos.php` |

### `auth:api` + `system:general`
| Prefix | File |
|--------|------|
| `/auth` | `api/auth-protected.php` |
| `/info-perfil` | `api/perfilUsuario.php` |
| `/usuarios` | `api/usuarios.php` |
| `/cursos` | `api/cursos.php` |
| `/permisos` | `api/permisos.php` |
| `/areas` | `api/areas.php` |
| `/inventario` | `api/inventario.php` |
| `/prestamos` | `api/prestamos.php` |
| `/reservas` | `api/reservas.php` |
| `/salones` | `api/salones.php` |
| `/horas` | `api/horas.php` |
| `/categorias` | `api/categorias.php` |
| `/hikvision` | `api/hikvision.php` |
| `/biblioteca` | `api/Biblioteca.php` |
| `/tipos-documentos` | `api/TipoDocumentos.php` |
| `/llegadas-tarde` | `api/llegadasTarde.php` |
| `/gestion-academica` | `api/gestionAcademica.php` |
| `/gestion-convivencial` | `api/gestionConvivencial.php` |
| `/documentos-varios` | `api/documentosVarios.php` |
| `/asistencia-gestion` | `api/asistenciaGestion.php` |
| `/enfermeria` | `api/enfermeria.php` |
| `/proveedores` | `api/proveedores.php` |
| `/solicitudes` | `api/solicitudes.php` |
| `/evaluaciones` | `api/evaluaciones.php` |

## Autenticación JWT

### Flujo
1. Login genera JWT con claims: `system`, `user_id`, `nombre`, `apellido`, `correo`, `perfil`.
2. Token se guarda en httpOnly cookie: `token` (general) o `admissions_token` (admisiones).
3. `JwtFromCookie` middleware (prepended al grupo `api`) lee la cookie y setea `Authorization: Bearer`.
4. `ValidateSystem` middleware (alias `system`) verifica el claim `system` del JWT.

### Cookies no encriptadas
`bootstrap/app.php:21` — `encryptCookies(except: ['token', 'admissions_token'])`

### Middleware stack
```php
// bootstrap/app.php
$middleware->prependToGroup('api', JwtFromCookie::class);
$middleware->alias([
    'auth' => Authenticate::class,     // 401 JSON
    'system' => ValidateSystem::class, // 403 si system mismatch
]);
```

### JwtService (`app/Services/JwtService.php`)
Singleton bound en `AppServiceProvider`. Métodos: `generateToken()`, `generateAdmissionsToken()`, `refreshToken()`, `invalidateToken()`, `getPayload()`, `authenticate()`.

### Cookie helper — `HasAuthCookie` trait
```php
use App\Http\Traits\HasAuthCookie;
// $this->makeCookie($token, 'token'|'admissions_token')
```
Usado en: `AuthController`, `AdmissionsController`.

## Sistema de permisos (`cron_opciones` / `cron_permisos`)

Tablas legacy manejadas directo por SQL (`DB::table(...)`, no Eloquent/seeders):
- `cron_opciones` (`id`, `nombre`, `id_modulo`, `user_log`, `activo`, `fechareg`) — catálogo
  de opciones/permisos (cada fila = acceso a un módulo, submódulo o acción).
- `cron_permisos` (`id_opcion`, `id_perfil`, `activo`, ...) — la matriz real: qué
  `id_perfil` tiene otorgada cuál `id_opcion`.

El chequeo real vive en `UsuariosServices::tienePermiso($opcion, $perfil): array{permiso: bool, error: bool}`
(`SELECT ... FROM cron_permisos WHERE id_opcion=? AND id_perfil=? AND activo=1`). Lo
expone `GET /api/usuarios/permiso?opt=&per=` (`UsuariosController::tienePermiso`) para
que el frontend decida qué renderizar (`PermissionGate` / fail-closed — ver
`docs/sistema-permisos.md` en el repo del frontend para el detalle de ese lado).

**Ese chequeo NO se aplica solo — cada controller tiene que llamarlo.** No existe
middleware global de permisos (revisar `bootstrap/app.php`: `JwtFromCookie`, `auth`,
`system` son los únicos alias). Una auditoría (2026-08-18) encontró que de 32
controllers, solo 2 verificaban permisos antes de ejecutar una acción — el resto,
incluido `PermisosController` (el que administra los permisos mismos), dejaba que
cualquier usuario autenticado hiciera lo que quisiera con un request directo,
saltándose por completo lo que el frontend mostraba u ocultaba. Se corrigió ese día en
`PermisosController`, `UsuariosController`, `LlegadasTardeController`/`ConfigController`,
`GestionAcademicaController`, `EnfermeriaController`, `InventariosController`,
`SalonesController`, `BibliotecaController`, `CategoriasController` — usarlos como
referencia al crear un controller nuevo con acciones sensibles.

Dos patrones según el caso (ver los controllers de arriba para ejemplos reales):

**(a) Todo el controller detrás de una sola opción, sin rutas públicas mezcladas** — un
chequeo único en el constructor:
```php
public function __construct(
    private MiService $service,
    UsuariosServices $usuariosService,
    Request $request,
) {
    $tienePermiso = $usuariosService->tienePermiso(self::OPCION, $request->user()->perfil)['permiso'] ?? false;
    if (!$tienePermiso) {
        abort($this->error('No tienes permiso para esta acción', 403));
    }
}
```
Si el controller tiene alguna ruta pública mezclada (ej. `BibliotecaController::verImagenBiblioteca`,
servida fuera de `auth:api`), el chequeo debe ser condicional a que exista usuario
autenticado (`if ($usuario = $request->user()) { ... }`) — nunca asumas que
`$request->user()` no es null.

**(b) Métodos que necesitan opciones distintas** — helper `sinAcceso()` al inicio de
cada método:
```php
private function sinAcceso(Request $request, int ...$opciones): ?JsonResponse
{
    $perfil = $request->user()->perfil;
    foreach ($opciones as $opcion) {
        if ($this->usuariosService->tienePermiso($opcion, $perfil)['permiso'] ?? false) {
            return null;
        }
    }
    return $this->error('No tienes permiso para esta acción', 403);
}
```

Reglas:
- Antes de gatear un endpoint, revisa (grep en el frontend, `src/pages/`) qué otros
  módulos lo consumen — un endpoint de lectura compartido (dropdowns, catálogos) debe
  aceptar cualquiera de las opciones válidas (OR), no solo la del módulo "dueño".
- Nunca confíes en un campo del body para "quién hizo esto" (`user_log`, `id_log`) si es
  para auditoría/seguridad — usa `$request->user()->id_user`, no lo que mande el cliente.
- Antes de dar por cerrado el cambio, confirma en BD quién tiene la opción hoy para no
  bloquear a un perfil que ya debería tener acceso:
  ```
  php artisan tinker --execute="foreach (DB::table('cron_permisos as p')->join('perfiles as pf','pf.id_perfil','=','p.id_perfil')->where('p.id_opcion',N)->where('p.activo',1)->pluck('pf.nombre') as \$n) echo \$n . PHP_EOL;"
  ```
- Para crear una opción nueva sigue el patrón de
  `database/migrations/2026_08_18_100000_seed_opcion_llegadas_tarde_recepcion.php`: un
  `up()` con `insertGetId` en `cron_opciones` + inserts iniciales en `cron_permisos`, y un
  `down()` simétrico. El `id` lo asigna el autoincrement — corre la migración local antes
  de hardcodear el número en el frontend.
- **Todo es fail-closed, incluido Super Admin**: crear la fila en `cron_opciones` sin el
  `up()` también insertando en `cron_permisos` deja la opción sin nadie con acceso — ni
  siquiera perfil 1, porque `tienePermiso()` no tiene ningún caso especial para Super
  Admin, solo mira la fila de `cron_permisos`. Pasó de verdad con la opción "Metricas
  Asistencias" (id 127 hoy, ver `2026_09_16_160000_seed_permisos_opcion_metricas_asistencias.php`):
  quedó creada pero invisible para todos hasta que una segunda migración le otorgó el
  acceso. Al agregar una opción, verifica en el mismo `up()` (o justo después, con el
  query de arriba) que quedó otorgada a los perfiles que la necesitan.

### CRUD de módulos/opciones desde la UI — solo Super Admin (`PermisosController`)

Alternativa a escribir una migración para lo de arriba: `/permisos` (frontend) tiene una
pestaña "Módulos y opciones" que llama a `POST/PUT/DELETE /api/permisos/modulos` y
`/api/permisos/opciones`. A diferencia del resto de este controller (gateado por la
opción `28`, otorgable a cualquier perfil), estos 7 endpoints están detrás de
`PermisosController::soloSuperAdmin()` — un chequeo aparte de `sinAcceso()` que exige
`perfil === 1` literal, no una opción de `cron_permisos`. Igual que con cualquier
`cron_opciones` nueva: **crear una opción desde esta UI no gatea nada por sí sola** — un
desarrollador todavía tiene que escribir el `PermissionGate`/`sinAcceso()` que la
referencie por id en el sitio que se quiere proteger. `eliminarOpcion` borra en cascada
los `cron_permisos` de esa opción para no dejar filas huérfanas.

## Noticias — envío masivo, rate-limit y correos de distribución

`NoticiasController`/`NoticiasService` (`app/Http/Controllers/Noticias/`,
`app/Services/Noticias/NoticiasService.php`). Dos formas de contenido: el "mensaje
general" (una sola fila siempre vigente, `asistencia_mensaje_general`) y las "noticias
programadas" (`asistencia_mensaje`, por `fecha`). Un comando que corre cada hora
(`EnviarNoticiasDiariasCommand`, `noticias:enviar-diarias`) envía por correo lo que esté
activo y no se haya enviado aún.

- **Cumpleaños anuales (`asistencia_mensaje.repetir_anual`, default false)** — solo los
  cumpleaños marcados se repiten cada año:
  - **Correo** (`enviarProgramadosDelDia`): con la marca, compara solo mes y día de `fecha`
    (desde el año de esa fecha en adelante) y `enviado_at` pasa a guardar el **último**
    envío — una fila está pendiente si no se envió este año. Sin la marca, el
    comportamiento de siempre: una sola vez, en su fecha exacta (`whereNull('enviado_at')`).
    Un 29 de febrero se envía el 28 en años no bisiestos.
  - **Home** (`programadasVisiblesDeTipo('cumpleanos')` + `aniversarioVisible`): con la
    marca, la ventana (`dias_visualizacion`, default 10) cuenta desde el aniversario más
    reciente (revisa también el del año anterior, para ventanas que cruzan año nuevo); sin
    ella, la ventana única desde `fecha`. Los de fecha única se filtran ya en SQL para no
    traer los ~290 históricos en cada carga del Home; el aniversario se calcula en PHP
    (`ponytail:`, pocos anuales — pasar a SQL si llegan a miles).
  - **Por qué default false — datos de producción**: hay ~290 cumpleaños previos
    (2022–2026, todos `tipo='cumpleanos'`, activos, `enviado_at` NULL; en local esas filas
    son `normal`, la migración del `tipo` las dejaba así y en producción se cambiaron a
    mano). Antes se creaba una fila nueva por persona cada año, así que repetirlos todos
    mandaría varias felicitaciones a la misma persona el mismo día (p. ej. 4 el 31 de
    octubre: #3, #89, #162, #259) a nivel 0, con riesgo de repetir el incidente de abajo si
    "Todos" no tiene lista de distribución. Esos siguen con fecha única hasta que Gestión
    Humana convierta **una fila por persona**: desde el formulario (guardar un cumpleaños
    lo marca) o con la acción masiva `POST /api/noticias/repetir-anual`
    (`cambiarRepeticionAnual`, ignora lo que no sea cumpleaños e informa cuántos omitió).
    Filtro `repetir_anual` en `listarProgramados`. No hace falta desactivarlos.
  - `crearProgramado`/`actualizarProgramado` solo guardan `repetir_anual` si `tipo` es
    cumpleaños; al actualizar sin el campo, se conserva el valor que tenía.
  - Las de tipo `normal` no se envían por correo (solo se muestran en el Home, por su
    ventana `fecha` + `dias_visualizacion`).
- **Interruptor de correos** (`configuracion_noticias.enviar_correos_programadas`, fila
  singleton `ConfiguracionNoticias::actual()`, `GET|PUT /api/noticias/configuracion`,
  opción 69): apagado, `enviarProgramadosDelDia` no envía nada **ni marca `enviado_at`** —
  si se enciende el mismo día, la siguiente ejecución horaria manda los de hoy. No afecta
  al mensaje general.
- **Despliegue — migrar antes que el código**: `2026_10_16_100000_create_configuracion_noticias_table`
  y `2026_10_16_110000_add_repetir_anual_to_asistencia_mensaje_table`. Sin ellas, el envío
  horario falla (tabla `configuracion_noticias` inexistente) y `para-mostrar` también
  (columna `repetir_anual`), así que la sección de noticias del Home falla para todos. Si
  se aplican con SQL a mano, registrar ambas en `migrations` para que un `migrate`
  posterior no las repita. En cada base de colegio que use Noticias.
- **`asistencia_mensaje` es MyISAM** (tabla legacy): no soporta transacciones, un
  `DB::beginTransaction()`/`rollBack()` alrededor de una prueba **no** deshace nada (pasó
  de verdad el 2026-10-16: las filas de prueba quedaron guardadas). Para probar contra
  esa tabla, usa instancias en memoria (`new MensajeProgramado([...])`) para la lógica de
  fechas, y si hace falta crear filas, ponles un título reconocible y bórralas en un
  `finally`. El `ALTER TABLE` también bloquea la tabla mientras corre.
- **Probarlo manualmente**: `routes/console.php` programa el comando con `->hourly()`
  (al minuto 0 de cada hora, con `withoutOverlapping()`; antes era `->daily()` a las 00:00,
  y una noticia programada para hoy pero creada después de medianoche — o un día en que el
  servidor estuvo apagado a esa hora — no se enviaba nunca; repetirlo cada hora es seguro
  por `enviado_at`/`ultimo_envio_fecha`). En local `composer run dev` **no** corre el
  scheduler: usa `php artisan schedule:work` en otra terminal. Correr `php artisan schedule:run` en cualquier otro momento no
  hace nada, porque el scheduler solo dispara tareas cuyo horario coincide con el minuto
  exacto en que se invoca (así está pensado para un cron real cada minuto, no para una
  prueba puntual). Para probar el envío ya mismo, salta el scheduler y corre el comando
  directo: `php artisan noticias:enviar-diarias`. Con `MAIL_MAILER=log` en `.env` (el
  valor típico en local) esto no manda ningún correo real — solo lo deja escrito en
  `storage/logs/laravel.log`, seguro para repetir cuantas veces haga falta.
- **Incidente de origen (2026-09-16)**: el mensaje general se enviaba por correo
  individualmente a cada usuario de TODOS los niveles (~2149 destinatarios en un run
  real), agotando en minutos el límite de "Max Emails Per Hour" de la cuenta de correo
  del hosting (cPanel/Exim) — 1967 de 2149 correos fallaron con
  `452-4.5.3 "Your message has too many recipients"`, y el comando siguió reintentando
  uno por uno durante 1h22m en vez de cortar.
- **`App\Exceptions\MailRateLimitException`** — `MailService::send()` detecta esa firma
  exacta (código `452` + "too many recipients", deliberadamente específica para no
  confundir un buzón lleno de UN destinatario con el límite de cuenta) y la lanza en vez
  de solo devolver `false`. `NoticiasService` la captura en sus loops de envío individual
  y corta el lote (loguea cuántos alcanzó a enviar) en vez de seguir martillando el SMTP.
- **Correos de distribución (`correos_institucionales`, grupos `NOTICIAS_*`)** — la
  solución de raíz al incidente: en vez de un correo por usuario, cada nivel puede
  asociarse a una lista de distribución real del colegio (`nivel.grupo_correo_distribucion`,
  migración `add_grupo_correo_distribucion_to_nivel_table`) y el envío manda UN solo
  correo a esa lista (`NoticiasService::resolverDestinatariosDistribucion`, grupos
  `App\Enums\Mails::NOTICIAS_TODOS/NOTICIAS_PREESCOLAR/NOTICIAS_PRIMARIA/NOTICIAS_SECUNDARIA/NOTICIAS_ADMINISTRATIVO`
  — mismo mecanismo de `correos_institucionales` que ya usan Admisiones/Biblioteca/Gestión
  Humana/Dirección Administrativa, ver el enum). Un nivel sin alias asociado (o el alias
  sin filas activas) cae al envío individual de siempre, con el corte por rate-limit como
  red de seguridad. La fila real `nivel` "Secundaria" en BD corresponde a 10°-11°/Media —
  lo que a diario llaman "Bachillerato" (ver migración
  `backfill_id_nivel_academico_for_secundaria`) — de ahí que mapee al alias `midhigh@`.
  `NoticiasController::correosDistribucion`/`crearCorreoDistribucion`/
  `actualizarCorreoDistribucion`/`eliminarCorreoDistribucion`/`asignarGrupoNivel`
  (endpoints bajo `/api/noticias/correos-distribucion*`) administran esto desde la
  pestaña "Correos de distribución" del frontend — todos scoped a `grupo` `NOTICIAS_*`
  para no tocar los grupos de otros módulos en la misma tabla.
- **`OPCION_NOTICIAS` — mismo tipo de drift de `insertGetId` que Instituciones (104 vs
  106, ver más abajo)**: la migración `seed_opcion_noticias` asumía que la fila quedaría
  en el id `107`, pero en esta BD el `107` real es "Año Escolar y Periodos" (otra
  feature completamente distinta) — el `107` correcto para Noticias resultó ser el `69`
  ("NEWS Royal", una opción que ya existía de antes). Confirmado contra la tabla real
  2026-09-16 y corregido en `NoticiasController::OPCION_NOTICIAS` y en el frontend
  (`router/index.tsx`, `sideBar/index.layout.tsx`) — **antes de asumir un id de opción
  desde un comentario o esta doc, confírmalo contra `cron_opciones` real.**
  `OPCION_CORREOS_DISTRIBUCION` sí se creó y otorgó correctamente desde el principio,
  pero también cambió de número (ver el punto de sincronización justo abajo).
- **Sincronización de `cron_opciones` con producción (2026-09-16)**: al comparar un
  dump real de `cron_opciones` de producción (hasta el id `125`) contra local, resultó
  que ids `126`–`145` en local eran **19 filas duplicadas** de opciones que ya existían
  con un id más bajo — causadas por volver a correr migraciones viejas `seed_opcion_*`
  cuya tabla `migrations` local no tenía registro de que ya habían corrido contra los
  datos importados del dump. Se borraron esas 19 filas (y sus `cron_permisos`, sin
  intentar fusionar sus permisos con los de la fila original — esos permisos eran tan
  artefacto del re-run como las filas mismas) y se renumeraron las 3 opciones
  genuinamente nuevas para que siguieran justo después del último id real de
  producción: `139→126` ("Noticias", sin uso real en código — reemplazada por la 69,
  "NEWS Royal"), `146→127` ("Metricas Asistencias"), `147→128` ("Noticias — Correos de
  distribución", `NoticiasController::OPCION_CORREOS_DISTRIBUCION`). Si vuelves a
  encontrar un id de opción que no cuadra con lo documentado acá, sospecha primero de
  este mismo patrón (migraciones legacy re-corridas sobre un dump importado) antes de
  asumir que la doc está desactualizada.

## Asistencia de Trabajadores — aviso de llegada tarde (`AsistenciaGestionService`)

`notificarLlegadaTarde()` (llamado desde `registrarAsistencia`/`registrarAsistenciaManual`
vía `notificarLlegadaTardeDespuesDeResponder`, en `app()->terminating()` para no bloquear
la respuesta al dispositivo Hikvision) envía el aviso de entrada tarde. Configuración en
la fila única `configuracion_asistencia` (id=1):

- `notificar_llegada_tarde` — maestro, apagado por defecto.
- `notificar_llegada_tarde_trabajador` — si el aviso también le llega al propio trabajador.
- `notificar_recursos_humanos` (perfil `8`, todo el colegio) y `notificar_coordinador_nivel`
  (perfil `26`, acotado al `id_nivel` del trabajador que llegó tarde) — **reemplazan** un
  selector libre de perfiles (`perfiles_notificar_llegada_tarde`, columna JSON) que existió
  brevemente; se simplificó a estos dos toggles fijos porque en la práctica solo esos dos
  roles tenían sentido, y un selector libre invitaba a elegir perfiles grandes que
  dispararían el límite de destinatarios (`MAX_DESTINATARIOS_LLEGADA_TARDE = 30`, mismo
  criterio del incidente de rate-limit de Noticias, ver abajo). Migración
  `2026_09_22_155537_replace_perfiles_notificar_llegada_tarde_with_rh_coordinador` — el
  down() restaura la columna JSON si hace falta revertir.
- El coordinador se resuelve con `Usuario::correosPorPerfilesYNivel(array $perfiles,
  ?int $idNivel, bool $soloActivos = false)` (`app/Models/Usuarios/Usuario.php`, método
  estático) — el mismo mecanismo que ya usan `PermisosLicenciasServices::destinatariosNotificacion`
  (avisos de solicitudes de permiso al coordinador/directivo de nivel del beneficiario) y
  `ProcesoCompra\SolicitudesServices` (aviso al coordinador al confirmar una solicitud de
  compra) — si necesitas resolver "usuarios de perfil X en el nivel Y" en un servicio
  nuevo, usa este método estático en vez de reimplementarlo otra vez. **Nota de historial**:
  este método nació dos veces en paralelo — una vez como `UsuariosServices::correosPorPerfilesYNivel`
  (instancia, inyectada) al extraerlo de la copia `private` que tenía
  `PermisosLicenciasServices`, y por separado como este mismo `Usuario::correosPorPerfilesYNivel`
  estático en otra rama que ya lo traía adoptado en `ProcesoCompra\SolicitudesServices` —
  el conflicto de merge del 2026-09-22 se resolvió a favor de la versión estática (ya con
  más consumidores) y se eliminó la copia en `UsuariosServices`; si ves una referencia a
  `UsuariosServices::correosPorPerfilesYNivel` en un commit viejo, ya no existe. `$soloActivos`
  nace en `false` por defecto: el método original en `PermisosLicenciasServices` no
  filtraba por `estado`, y cambiar ese comportamiento por default habría alterado (sin
  pedirlo) a quién le llegan los avisos de permisos ya en producción — `AsistenciaGestionService`
  y `SolicitudesServices` sí pasan `true` explícito.

## Gestión Académica (`/gestion-academica` — `GestionAcademicaController`)

### Registro de actas (opciones 136/137, controller aparte)

`ActasEstudianteController` + `ActasEstudianteService`, tabla `actas_estudiante` (modelo
`App\Models\GestionAcademica\ActaEstudiante`). El docente hace el acta en papel; un usuario
con permiso (rector, coordinador) la registra: fecha, estudiante, tipo
(`dialogo_formativo` | `convivencial`) y observación opcional.

- **Permisos** (sembrados para Super Admin por `2026_10_15_100100_seed_opciones_actas_estudiante`):
  **136** "Ver actas de estudiantes" (listado, conteo, gráficas) y **137** "Gestionar actas
  de estudiantes" (crear/editar/eliminar; también da lectura). Chequeo por método (`sinAcceso`).
- **138 "Ver actas de mis estudiantes"** (`2026_10_15_130000_seed_opcion_actas_mis_estudiantes`,
  sin otorgar por defecto): solo lectura limitada a los estudiantes que **hoy** están en los
  cursos con carga académica activa del usuario (`CargaAcademicaService::obtenerCursosDocente`,
  el mismo de Observaciones). Las cuatro lecturas pasan por `sinLectura()`, que con 136/137
  no limita y con solo la 138 llama `ActasEstudianteService::limitarACursos($ids)`: lo aplica
  `filtrar()` (listado, agrupado y gráficas), `filtros()` (cursos y estudiantes del catálogo) y
  `conteo()` (403 si el estudiante no está en sus cursos). Escribir sigue exigiendo la 137.
- **Docente asociado** (`DOCENTE_ASOCIADO_SQL`): el docente que solicita tiene carga académica
  activa (por asignatura o suelta) en `a.id_curso`, el curso del estudiante al registrar. El
  listado devuelve `docente_asociado` (null sin docente, 0/1) y el agrupado
  `no_asociadas_total`. Filtros `id_docente_solicita` y `asociacion` (`no_asociado` |
  `asociado`) en listado y estadísticas. **La 138 incluye además las actas que el propio
  docente solicitó** (`limitarACursos($cursos, $idDocente)`): las ve en el listado, sus
  estudiantes entran en el catálogo y `conteo()` no las rechaza.
- **Periodo y año no se envían**: salen de la `fecha` (el `periodo_academico` que la contiene;
  422 si no cae en ninguno). `id_curso` es la foto del curso del estudiante al registrar
  (solo se recalcula si al editar cambia el estudiante) — así las gráficas históricas no se
  mueven cuando el estudiante pasa de curso. Sin FK a `periodo_academico` (es MyISAM).
- **Listado agrupado** (`GET /?agrupar=1`): una fila por estudiante —su acta más reciente
  (`ROW_NUMBER() OVER (PARTITION BY id_estudiante …)`, MariaDB 10.2+/MySQL 8) más
  `total_actas`, `dialogo_total` y `convivencial_total` de las actas que cumplen los filtros y
  la búsqueda—. Ordenable también por `total_actas` (`ORDENES_AGRUPADO`). El detalle de un
  estudiante es el listado normal con `id_estudiante`.
- **Asunto** (`asunto`, varchar 200; migración
  `2026_10_15_120000_add_asunto_to_actas_estudiante_table`): obligatorio en `POST`/`PUT`
  (lo valida el controller), nullable en la tabla solo por las actas previas. Se devuelve en
  el listado y entra en la búsqueda.
- **Docente que solicita** (`id_docente_solicita`, nullable, FK a `usuarios` con `SET NULL`;
  migración `2026_10_15_110000_add_id_docente_solicita_to_actas_estudiante_table`): quien
  diligenció el acta en papel, distinto de `id_usuario_registra` (quien la digita). Opcional
  en `POST`/`PUT`; si viene, `resolver()` exige un docente activo
  (`ActaEstudiante::PERFILES_DOCENTE` = 3, 14). El listado devuelve `docente_solicita`
  (nombre, ordenable y buscable) y `/filtros` trae `docentes` para el formulario.
- **Máximo 3 actas de diálogo formativo por estudiante y periodo**
  (`ActaEstudiante::MAX_DIALOGO_FORMATIVO`): **no se bloquea**; crear/actualizar devuelven
  `conteo_dialogo` + `advertencia` (desde la 2.ª), y `GET .../conteo` permite avisar antes de guardar.
- Endpoints (`/gestion-academica/actas`): `GET /` (DataTable remoto: `page`, `per-page`, `s`,
  `sort`, `dir`), `GET /estadisticas` (resumen, `por_nivel`, `por_curso`, `por_periodo`,
  `por_anio` — histórico, ignora año/periodo —, `top_estudiantes`), `GET /filtros`
  (años, periodos, niveles, cursos y estudiantes activos con su curso),
  `GET /conteo?id_estudiante=&fecha=[&excluir_id=]` (diálogo formativo y convivenciales del
  periodo), `POST /` (**lote**: `{ actas: [...] }`, hasta 50, en una transacción — si una falla
  no se guarda ninguna; devuelve `advertencias` por estudiante calculadas tras insertar todo el
  lote), `PUT /{id}` (una), `DELETE /{id}`.
  Filtros de listado y estadísticas: `id_anio_escolar` (por defecto el habilitado),
  `id_periodo_academico`, `id_nivel`, `id_curso`, `id_estudiante`, `tipo`.

### Consulta de asistencias de clases (opción 132, controller aparte)

`ConsultaAsistenciaClaseController` + `ConsultaAsistenciaClaseService`, rutas
`GET /gestion-academica/consulta-asistencias` (listado paginado con el contrato de
DataTable remoto: `page`, `per-page`, `s`, `sort`, `dir` + `fecha_inicio`, `fecha_fin`,
`id_curso`, `id_docente`, `id_asignatura`, `estado`), `.../{id}` (registro por estudiante
de una sesión) y `.../filtros` (catálogos de curso/asignatura/docente). **Solo lectura**:
ver las asistencias de cualquier clase sin ser el docente. Acepta la opción **132**
("Consulta de asistencias de clases", sembrada para Super Admin por
`2026_10_13_100000_seed_opcion_consulta_asistencias_clases`) **o** la 99 completa.

- **Por qué un controller aparte**: el constructor de `GestionAcademicaController` aborta
  todo para quien no tenga la 99 (o sea docente en `METODOS_DOCENTE`); meter la consulta
  ahí habría obligado a abrir otro hueco en ese chequeo. Con un controller propio, dar la
  132 a coordinación no le da acceso a nada más del módulo.
- **Id explícito en la migración**: inserta `cron_opciones.id = 132` en vez de
  `insertGetId`, y falla con un mensaje claro si ese id ya está ocupado por otra opción —
  para no repetir el desfase de ids de Noticias/Instituciones/Acudientes.
- **Datos**: una fila por `academico_asistencia_clase`; el docente sale de
  `COALESCE(ca.id_docente, da.id_docente)` (cubre la carga "suelta" sin asignatura);
  ausentes/tardanzas/permisos por subconsulta sobre las excepciones;
  `total_estudiantes` son los estudiantes activos **hoy** en el curso (no hay foto
  histórica del curso por fecha), y el `% de asistencia` solo se calcula en clases
  `DICTADA`. El detalle une los activos del curso con cualquier estudiante que tenga una
  excepción en esa sesión aunque ya no esté en el curso.

### Autoservicio del Docente (tercer patrón de permisos, además de (a)/(b) arriba)

El perfil Docente (`id_perfil` `3`) normalmente NO tiene la opción `99` otorgada en
`cron_permisos` — en vez de eso, el constructor de `GestionAcademicaController` combina
el chequeo de opción con una whitelist fija de acciones self-scoped:

```php
private const PERFIL_DOCENTE = 3;
private const METODOS_DOCENTE = [
    'verAsistenciasClase', 'crearAsistenciaClase', 'actualizarAsistenciaClase',
    'verAsistenciasEstudiantes', 'crearAsistenciaEstudiantes', 'eliminarAsistenciaEstudiante',
    'verMiMenuHorario', 'verMiHorario', 'reservarMiHorario', 'eliminarMiHorario',
    'verMetricasAsistencia', 'obtenerMisCursos', 'verFranjasHorarias',
];
```

Si `$request->user()->perfil === PERFIL_DOCENTE` y la acción está en esa lista, pasa sin
necesitar la opción 99 — **el resto del controller (asignaturas, áreas, carga académica,
esquemas/franjas fuera de listar, años escolares, calendario) sigue exigiéndola**. Al
agregar un método nuevo que el docente deba poder usar desde autoservicio (Asistencia de
clases / Mi horario), agrégalo a `METODOS_DOCENTE` explícitamente — no asumas que "es de
lectura" es suficiente (`verFranjasHorarias` se quedó fuera al construir esto y rompió el
flujo de "apartar horario" hasta que se agregó). `verMetricasAsistencia` se deja pasar
siempre, pero el propio `AsistenciaEstudianteService::metricasPorCurso` recibe
`id_docente_scope` (el `id_user` del docente, resuelto server-side, nunca de un
parámetro) y restringe ahí los resultados a solo sus cursos — no confíes en que
"whitelisteado en el controller" sea suficiente aislamiento para endpoints agregados,
que también agregan datos de terceros.

### "Horario suelto" — bloque de horario sin asignatura

Un `academico_carga_academica` normalmente vincula curso+docente **a través de**
`id_docente_asignatura` (FK a `academico_docente_asignatura`, que a su vez amarra
docente+asignatura). Algunos horarios (ej. dirección de grupo, actividades sin materia)
necesitan un docente atado a un curso sin pasar por ninguna asignatura. En vez de crear
una asignatura ficticia (lo que ensuciaría reportes/exports que agrupan por asignatura),
`academico_carga_academica` ganó una **segunda ruta**, mutuamente excluyente con la
primera y no forzada por constraint de BD (solo por código de aplicación):

- `id_docente_asignatura` se volvió `NULL`-able (antes `NOT NULL`) —
  `2026_09_22_134203_add_id_docente_to_academico_carga_academica_table` hace
  `DB::statement('ALTER TABLE academico_carga_academica MODIFY id_docente_asignatura INT NULL')`
  (vía `DB::statement` crudo, no `Blueprint::change()` — este proyecto no tiene
  `doctrine/dbal` instalado, ver convención ya documentada en otras migraciones del repo).
- `id_docente` (nuevo, nullable, FK directa a `usuarios.id_user`) es la ruta alterna:
  cuando está seteado, `id_docente_asignatura` es `NULL` y viceversa. `unique(['id_docente',
  'id_curso'])` evita duplicar el mismo docente+curso por la ruta directa.
- `CargaAcademica::getIdDocenteEfectivoAttribute()` resuelve "el docente de esta carga,
  por cualquiera de las dos rutas" (`$this->id_docente ?? $this->docenteAsignatura?->id_docente`)
  — deliberadamente **no** en `$appends` (forzaría cargar la relación `docenteAsignatura`
  en cada serialización aunque no se use).
- `CargaAcademicaService::añadirCargaAcademicaSuelta(int $id_curso, int $id_docente, bool
  $silentIfExists = false)` — la ruta de creación para la ruta directa (usada solo desde
  el admin, ver abajo).

**Todo método que antes asumía `docenteAsignatura` no-nulo tuvo que aprender a resolver el
docente por ambas rutas** (con `orWhere`/`leftJoin` en vez de `whereHas`/`join`, que
descartarían silenciosamente las filas con `id_docente_asignatura` nulo):
`HorarioClaseService::añadirHorarioClase`/`franjaDisponibleParaCarga`/`esquemasDelDocente`/`verHorario`,
`FranjaHorariaService::verFranjasHorarias` (resolución de `$docenteScope`),
`DocenteHorarioService::actualizarDescripcion`/`eliminar` (chequeo de dueño),
`HorarioExcelService::exportarTodosLosDocentes`,
`AsistenciaEstudianteService::metricasPorCurso` (resolución de `$cursosDocente`).

**Solo el admin puede crear un horario suelto** (pestaña "Horario" de Gestión Académica,
`HorarioClaseRequest` acepta `id_curso`/`id_docente` opcionales para esa rama) — se evaluó
y se construyó completo un flujo de autoservicio equivalente en "Mi horario"
(`GestionAcademicaController::reservarMiHorario`/`MiHorarioRequest`/
`DocenteHorarioService::reservar`/`verMenu` con `id_asignatura` nullable) pero fue
**revertido por decisión explícita del usuario** ("quita el que los profesores puedan
asignarse franjas horarias sin asignaturas, deja que el admin se encargue de ello") — si
se retoma esa idea, el punto de partida ya existió una vez en este mismo archivo de
servicio, revisar el historial de git antes de reconstruirlo desde cero. Lo que sí quedó
del intento (y es un fix real, no atado a autoservicio): `DocenteHorarioService::actualizarDescripcion`/`eliminar`
usan `$horario->cargaAcademica?->id_docente_efectivo` para el chequeo de dueño, porque un
docente sigue necesitando poder editar/eliminar (vía la UI normal de "Mi horario") un
bloque suelto que el admin le asignó.

### Bug corregido: "aplicar a todos los días" no revertía las franjas dependientes al desmarcar

`FranjaHorariaService::actualizarHorarioFranja()` tiene un atajo cuando la franja pasa de
"no asignable" a asignable (`desmarcarFranjaNoAsignable`): cuando se creó una franja "no
asignable" con `aplicar_todos_los_dias`, el backend replica esa franja (mismo horario,
mismo color/etiqueta) en el resto de los días de la semana
(`quitarNoAsignableDeOtrosDias` las localiza por horario+`id_horario_asistencia`). El
atajo de desmarcar llamaba `desmarcarFranjaNoAsignable` sobre la franja editada pero
**no** propagaba el cambio a esas franjas replicadas — quedaban "no asignable" para
siempre, aunque el usuario hubiera marcado "aplicar a todos los días" al desmarcar.
Reproducido en `tinker` (marcar 4 franjas no-asignable con replicación → las 4 quedan
marcadas; desmarcar 1 con el flag → las otras 3 seguían marcadas, incorrectamente) antes
de escribir el fix: el short-circuit de desmarcar ahora también llama
`$this->quitarNoAsignableDeOtrosDias($franja->id)` cuando `$aplicarTodosDias === true`.

### Años escolares y Calendario A/B

- `anio_escolar` (`id`, `anio_inicio`, `anio_fin`, `activo`, `fechareg`) sigue siendo
  legacy sin migración propia en este repo — no la borres/recrees, solo se le agregan
  filas nuevas.
- `configuracion_academica` (single-row, id=1, `tipo_calendario` enum A/B — migración
  `2026_08_21_140000_create_configuracion_academica_table`) reemplaza el cutoff fijo de
  "Calendario B" (1 ago–30 jun) que antes estaba hardcodeado en `AnioEscolarServices`.
  Calendario A = 1 feb–30 nov del mismo año calendario (`anio_inicio == anio_fin`);
  Calendario B = 1 ago–30 jun del año siguiente. Toda la lógica de rango/resolución vive
  en `AnioEscolarServices::rangoParaAnioInicio()`/`anioInicioParaFecha()` — no
  reimplementar el cutoff en otro sitio, reusar estos dos métodos (`rangoDeAnioEscolar()`
  ya expone el rango de un `Anio` existente para validaciones, ver
  `PeriodoAcademicoRequest`).
- `AnioEscolarServices::obtenerUltimoAnioEscolar()` (detrás de
  `GET /compartido/anio-academico/ultimo`) **prioriza la fila con `activo=1`** sobre el
  cálculo por calendario — solo recalcula por fecha si no hay ninguna fila habilitada
  todavía (antes de la primera corrida del cron, o si un admin deshabilitó todas). Lo usan
  también `AdmissionsController` (3 sitios) para resolver el año de nuevas inscripciones.
- Comando programado `anio-escolar:cerrar-abrir` (`CerrarAbrirAnioEscolarCommand`,
  `Schedule::...->daily()` en `routes/console.php`) llama
  `AnioEscolarServices::cerrarYAbrirAnioEscolar()`: cierra (`activo=false`) cualquier año
  activo que ya no corresponda a hoy según el calendario configurado, y abre (crea +
  activa) el que sí corresponde si todavía no existe — pero si esa fila ya existe y fue
  desactivada a mano, **no la reactiva** (respeta el override manual del admin). Igual que
  el resto de `Schedule::`, no hace nada solo — necesita `php artisan schedule:run` cada
  minuto vía el timer de systemd ya documentado para los otros jobs.
- Endpoints nuevos en `GestionAcademicaController` (todos gateados por opción 99, sin
  bypass de perfil): `GET|PUT /gestion-academica/configuracion-calendario`,
  `POST /gestion-academica/anios-escolares` (creación manual, respaldo si el cron no
  corrió), `PUT /gestion-academica/anios-escolares/estado` (habilitar/deshabilitar a
  mano). El listado (`GET /compartido/anio-academico/todos`) sigue viviendo aparte,
  compartido con Admisiones y sin gate de opción — no se tocó.

### Tablas sin migración propia descubiertas/completadas en este repo

`academico_asistencia_clase` y `academico_asistencia_estudiante` ya se usaban en
`AsistenciaClaseService`/`AsistenciaEstudianteService` sin tener `Schema::create` en
`database/migrations/` — mismo patrón legacy que `anio_escolar`/`cron_opciones`. Se les
agregaron migraciones (`2026_08_21_160000_...`, `2026_08_22_100000_...`) con sus FKs y
unique constraints (`(id_horario_clase, fecha)` / `(id_asistencia_clase, id_alumno)`).
Si un `db:seed`/endpoint nuevo falla con "Base table ... doesn't exist" en una tabla de
Gestión Académica, es probable que sea este mismo patrón — revisa si tiene migración real
antes de asumir que el dato está mal.

### Bug de índice único corregido: `uq_franja_horaria`

La migración original de `academico_franja_horaria` puso el unique en
`(id_anio_escolar, id_dia_semana, orden)`; cuando se introdujo `id_esquema` (franjas por
nivel, no directo por año) nadie actualizó ese índice. Efecto real: dos niveles del mismo
año no podían tener franjas en el mismo día+orden, aunque
`FranjaHorariaService::añadirFranjaHoraria` ya valida duplicados por `id_esquema`, no por
año — el índice de BD era más restrictivo que la regla de negocio. Corregido en
`2026_08_21_150000_fix_uq_franja_horaria_scope_to_esquema` (nuevo índice
`uq_franja_horaria_esquema` en `(id_esquema, id_dia_semana, orden)`; hubo que agregar un
índice de reemplazo para `id_anio_escolar` antes de poder borrar el viejo, porque MySQL no
deja quitar un índice del que depende una FK sin uno de repuesto).

### Seeders de datos de prueba (`database/seeders/`)

`DatabaseSeeder` corre, en orden: `AsignaturaSeeder` → `AreaAcademicaSeeder` (backfill de
`id_area`) → `PeriodoAcademicoSeeder` (vía el Service real, respeta el calendario
configurado) → `EsquemaHorarioSeeder` (uno por nivel usado) → `FranjaHorarioSeeder` →
`DocenteAsignaturaSeeder` → `CargaAcademicaSeeder` → `HorarioSeeder` →
`AsistenciaClaseSeeder`. Los últimos cuatro ya existían con datos reales (docentes,
materias, 5 días de horario) pero estaban rotos: hardcodeaban `id_anio_escolar=1` (año
inactivo) y buscaban al docente por `CONCAT(nombre,' ',apellido) = 'Nombre Completo'`
exacto, que no matchea nada contra los datos reales de `usuarios` (nombres completos
metidos en un solo campo, `apellido` a veces literalmente `"."`). Ambos arreglados:
`FranjaHorarioSeeder` ahora resuelve el año activo dinámicamente y crea franjas por
esquema (nivel); el matching de docente pasó a un resolver difuso compartido
(`database/seeders/Concerns/ResolvesDocentePorNombre.php`) que compara por palabras sin
importar orden ni palabras de más — reutilízalo en cualquier seeder nuevo que necesite
resolver un `Usuario` por nombre "limpio" contra datos reales sucios.

### Vinculación Estudiante - Padre (`/vinculacion-acudientes` — `VinculacionAcudienteController`)

Gestiona `estudiantes_padres` (tabla legacy, sin migración ni índice único) desde el
módulo Académico. Opción **131** (`OPCION_VINCULACION`, `sinAcceso()` en cada método),
creada por `2026_09_23_100000_seed_opcion_vinculacion_estudiante_padre` — **no** la `73`
("Estudiantes", legacy Matricula), que está otorgada al perfil Acudiente.

- `GET /` acudientes (perfil 6) paginados — reusa `UsuariosServices::mostrarAcudientesPaginados`.
- `GET /estudiantes-buscar?s=&cursos[]=` estudiantes (perfil 16) activos — reusa
  `mostrarUsuariosPaginados`, expuesto acá para no depender de `/usuarios/paginados` (sin
  chequeo de permiso).
- `GET /{idAcudiente}/estudiantes` vinculados activos + `otros_acudientes` de cada uno.
- `POST /` / `DELETE /` `{ id_acudiente, id_estudiante }` — `VinculacionAcudienteService`.
  Desvincular es **soft** (`activo=0`): llegadas tarde, enfermería y `/usuarios/paginados`
  ya filtran `activo=1`. Re-vincular reactiva la fila existente del par en vez de crear
  otra. `vincular` exige perfil 6/16 exactos, aunque en la tabla hay ~30 vínculos legacy
  con `id_acudiente` de staff (Docente, Coordinador, …) que siguen visibles para esos
  módulos pero no aparecen en este listado.
- `POST /importar` (multipart `archivo`, xlsx/xls/csv, máx. 5 MB y 3000 filas) —
  `importarExcel`: col A documento acudiente, col B documento estudiante, fila 1
  encabezados (la plantilla la genera el frontend con exceljs). Documentos comparados
  **normalizados** (solo alfanuméricos, `REGEXP_REPLACE` en MariaDB) porque en `usuarios`
  hay documentos con `\t`, `\r\n` o puntos sobrantes; un documento que resuelve a más de
  un usuario del perfil se reporta como ambiguo, nunca se adivina. Resultado por fila
  (`vinculados`/`ya_vinculados`/`errores[{fila, mensaje}]`); las filas válidas se aplican
  aunque otras fallen.

## Gestión Convivencial (`/gestion-convivencial` — `ObservacionEstudianteController`)

Sección nueva (módulo **15** "Gestión Convivencial" en `cron_modulos`). Su primer módulo es
**Observaciones de estudiantes**. Archivos: `routes/api/gestionConvivencial.php`,
`App\Http\Controllers\GestionAcademica\ObservacionEstudianteController`,
`App\Services\GestionAcademica\ObservacionEstudianteService` y `AccesoObservaciones`.
Tabla `academico_observacion_estudiante`. Frontend: `/gestion-convivencial/observaciones`.
El controller vive en el namespace `GestionAcademica` porque se reusa la carga académica,
pero las rutas cuelgan del prefijo propio.

### Modelo

- Una fila = una observación de un día: `fecha`, `tipo` (`POSITIVA`|`NEGATIVA`), `motivo`,
  `id_asignatura` (nullable), `id_docente` (quien la registró), `id_curso` (curso del
  estudiante **al registrarla**, para que las actas pasadas conserven el de entonces) e
  `id_periodo_academico`.
- **Acta** = observaciones de un estudiante en un periodo. Se usa `periodo_academico`
  (los periodos de Configuración académica), **no** la tabla legacy `periodos` de
  Evaluaciones.
- `periodo_academico` y `academico_asignatura` son **MyISAM**, así que no tienen FK desde
  esta tabla: el servicio las valida. Solo hay FK reales hacia `usuarios` y `curso`
  (InnoDB). Un `Schema::create` con FK hacia una tabla MyISAM falla con
  `errno: 150 "Foreign key constraint is incorrectly formed"` y deja la tabla a medio crear.

### Permisos (ids explícitos, `2026_10_14_100100_seed_opcion_observaciones_estudiantes`)

| Quién | Registra a | Ve |
|---|---|---|
| Docente (perfiles 3, 14), autoservicio sin `/permisos` | estudiantes de sus cursos con carga activa (`CargaAcademicaService::obtenerCursosDocente`) | solo las suyas |
| Opción **133** "Observaciones de estudiantes" | cualquier estudiante activo | solo las suyas |
| Opción **99** (Gestión Académica) | igual que la 133 | solo las suyas |
| Opción **134** "Ver todas las observaciones" | nadie si no tiene otro permiso (sola es de solo lectura) | las de **todos** los profesores en todos los cursos |
| Opción **135** "Observaciones — solo consulta" | **nadie**, aunque además sea docente o tenga la 133/99 (gana sobre ellas) | las de **todos** |

Las observaciones **no son globales: son de quien las registra.** La 133 y la 134 están
otorgadas a Super Admin (1), Psicóloga Preescolar/Primaria/Secundaria (21/24/25) y
Coordinador (26). Nadie edita ni elimina las observaciones de otro, ni siquiera con la 134.
La 135 (`2026_10_15_100000_seed_opcion_observaciones_solo_consulta`) no se otorga a ningún
perfil por migración: se asigna desde `/permisos`. Con ella `puedeRegistrar` es false y
`verTodas` true; `propiaEditable` también rechaza editar/eliminar las propias registradas
antes, y el acta marca `editable: false` en todas.

### Periodos cerrados: bloqueo e historial

- **Cerrado = su `fecha_fin` ya pasó** (no "empezó el siguiente": si dos periodos se
  solapan, el anterior sigue editable hasta su propia `fecha_fin`; Configuración académica no
  impide solaparlos). Cerrado bloquea registrar, editar, mover a esa fecha y eliminar.
- Toda comparación usa la fecha de la app (`Carbon::today()`, America/Bogota), **no**
  `CURDATE()`: `periodo_cerrado` (que decide editar/eliminar) y `preparar()` (registrar)
  tienen que cerrar a la misma hora aunque MySQL esté en otra zona.
- **El comando diario `periodos:desactivar-vencidos` pone `activo = 0` a todo periodo
  vencido.** Por eso `periodos()`/`periodoDeFecha()` no filtran solo `activo = 1` sino
  `activo = 1 OR fecha_fin < hoy` (`periodosVisibles()`): con el filtro anterior, las actas
  de un periodo desaparecían al día siguiente de cerrarse. Un periodo desactivado a mano que
  aún no termina sigue oculto.

El constructor resuelve todo esto una sola vez en un `AccesoObservaciones` (`idUsuario`,
`verTodas`, `puedeRegistrar` y `idsCursosRegistro`, que es null cuando puede registrar a
todos). El servicio recibe ese objeto y nunca lee permisos del request:
- `soloDocente()` filtra por autor salvo con la 134.
- `idsCursosEstudiantes()` define a qué estudiantes puede buscar o abrir.

Sin registro ni 134 → 403.

### Reglas (todas en `ObservacionEstudianteService`)

- **Periodo**: sale de la fecha, buscando el periodo activo que la contiene. Si la fecha
  no cae en ninguno → 400.
- **Periodo cerrado** (`fecha_fin < hoy`): no se crea, edita ni elimina nada en él (403).
  Su acta se puede seguir consultando. No hay cierre manual.
- **Fecha futura**: se permite. El aviso es solo del frontend.
- **Asignatura**: la de quien registra en el curso del estudiante.
  - Si dicta varias, es obligatoria (422).
  - Si dicta una, se asigna sola.
  - Si no dicta ninguna (psicología, coordinación), va null.
  - Una asignatura que no dicta en ese curso → 422.
  - Al editar se valida contra el `id_curso` guardado, no contra el curso actual.
- Solo el autor edita o elimina; la observación de otro ni siquiera se encuentra (404).

### Endpoints (`/gestion-convivencial/observaciones`)

- `GET /` — **una fila por estudiante** con observaciones en el rango filtrado, con su
  **última** observación (por `fecha DESC, id DESC`). Además trae `total_rango`,
  `positivas_rango` y `negativas_rango`, calculados con `ROW_NUMBER()`/`COUNT() OVER
  (PARTITION BY id_estudiante)`; requiere MariaDB ≥ 10.2 (local: 10.4).
  - Contrato DataTable remoto: `page`, `per-page`, `s`, `sort`, `dir`.
  - Filtros: `id_periodo`, `fecha_inicio`, `fecha_fin`, `id_curso`, `tipo`, `id_estudiante`.
  - El frontend manda lunes–domingo de la semana actual por defecto.
  - La búsqueda (`s`) mira el estudiante, el curso y el motivo de la última observación.
- `GET /catalogos` — cursos visibles, periodos (con `cerrado`/`actual`) y los flags
  `ver_todas`/`puede_registrar`.
  - `actual` es el periodo que contiene hoy.
  - En vacaciones entre periodos, `actual` es el último que ya empezó.
- `GET /estudiantes?s=` — búsqueda (máx. 20) para abrir el acta de quien aún no tiene
  observaciones.
- `GET /estudiantes/{id}/acta?id_periodo=` — devuelve:
  - el estudiante y el periodo (por defecto el actual) con la lista de periodos;
  - las observaciones visibles, cada una con `editable`;
  - el `resumen` de positivas/negativas;
  - `mis_asignaturas` y `puede_registrar` (para ESTE estudiante) y `ver_todas`.
- `POST /` `{ id_estudiante, fecha, tipo, motivo, id_asignatura? }`, `PUT /{id}` (lo mismo
  sin `id_estudiante`), `DELETE /{id}`.

### Historia de este módulo (2026-10-14)

Pasó por tres versiones el mismo día, antes de llegar a ningún entorno:
1. Un texto único por estudiante, editable por todos sus docentes, con control de
   concurrencia por `updated_at`.
2. Una bitácora por día en actas por periodo, visible para todos.
3. La actual: observaciones propias de cada docente más la opción 134 para ver todo, en
   su propia sección.

La tabla y el seed se reescribieron en el lugar (no hay migraciones de "corrección"), así
que en una BD que haya corrido una versión intermedia hay que borrar la tabla, la opción
133 y sus registros en `migrations` antes de volver a migrar.

## Proceso de compra (`/proveedores` + `/solicitudes`)

Módulo de compras en dos tablas paralelas: la solicitud inicial (`solicitudes_inicial`,
modelo `SolicitudInicial`, la pide el empleado y la decide su Coordinador) y la
formalizada (`solicitudes`, modelo `Solicitud`, la gestiona Compras hasta cerrarla).
Cada tabla tiene su propia columna `estado` — no es el mismo campo ni el mismo
significado en ambas, aunque los números se parezcan.

Opciones del módulo 9 "Proceso de compra" en `cron_opciones`: **59 Cotizaciones, 60
Listado de solicitudes** (legada — sigue gateando ver/verificar/aplazar/rechazar sobre
la solicitud final y agregar-inventario), **61 Proveedores**, **104 Compras — Gestión de
compras** (seguimiento, asignar proveedor, disponible en stock, anular), **105 Compras —
Ventas** (verificar-entrega). Estas dos últimas se crearon 2026-08-31 — no confundir con
los ids `109`/`110` (pertenecen a otro módulo, "Uso areas comunes"; `110` ni siquiera
existe todavía — un bug real de config tuvo esos ids hardcodeados en
`SolicitudesController` hasta que se corrigió). La bandeja de aprobación
(`listar`/`aprobar`/`rechazarInicial`) no usa un `id_opcion` en absoluto: gatea por
perfil (`noPuedeGestionarAprobaciones()`) — solo Coordinador (26, acotado a su propio
`id_nivel`) o Super Admin(1)/Administrador(2) (sin recorte).

### Proveedores

Un proveedor es un `Usuario` con `perfil=17`; `proveedor_detalle.id_proveedor` =
`usuarios.id_user`, y las tablas hijo (`proveedor_documento`, `proveedor_contactos`,
`proveedor_banco`) usan `id_proveedor` = `id_user`.

| Endpoint | Gate | Uso |
|----------|------|-----|
| `GET /proveedores`, `GET /proveedores/select`, `GET /proveedores/tipos-documento` | No | Listado / dropdown (solo activos) / catálogo |
| `GET /proveedores/{id}` | 61 | Detalle con documentos/contactos/bancos |
| `POST /proveedores`, `PUT /proveedores/{id}`, `PUT /proveedores/{id}/estado` | 61 | CRUD proveedor (crea usuario perfil 17 + detalle) |
| `GET/POST /proveedores/{id}/documentos` | 61 | Subida de documentos (FileStorage, no Cloudinary) |
| `PUT\|POST /proveedores/documentos/{docId}`, `PUT .../estado`, `DELETE ...` | 61 | Update/estado/elimina (borra archivo) |
| `GET/POST /proveedores/{id}/contactos`, `PUT/DELETE /proveedores/contactos/{cId}` | 61 | Contactos |
| `GET/POST /proveedores/{id}/bancos`, `PUT/DELETE /proveedores/bancos/{bId}` | 61 | Cuentas bancarias |

### Solicitudes — flujo y estados

`SolicitudesServices` expone el flujo completo por constantes propias, distintas para
cada tabla:

| `solicitudes_inicial.estado` | Significado |
|---|---|
| 0 `ESTADO_PENDIENTE` | Recién creada, esperando decisión del Coordinador |
| 1 `ESTADO_APROBADA` | Aprobada por `verificar` (decision `aprobar`) — no confundir con `aprobar()`, ver abajo |
| 2 `ESTADO_DEVUELTA` | Devuelta por `verificar` (decision `devolver`) |
| 3 `ESTADO_RECHAZADA` | Rechazada (por `verificar` o por `rechazar-inicial`) |
| 4 `ESTADO_CONVERTIDA` | Ya tiene fila espejo en `solicitudes` (la creó `aprobar()` o `asignar-proveedor`) |
| 5 `ESTADO_CANCELADA` | El propio solicitante la canceló (`cancelar`, solo mientras seguía en 0) |

| `solicitudes.estado` | Significado |
|---|---|
| 0 `ESTADO_PENDIENTE_GESTION` | Recién aprobada por el Coordinador, esperando que Compras la gestione |
| 1 `ESTADO_FORMALIZADA` | Proveedor asignado (`asignar-proveedor`) |
| 2 `ESTADO_CERRADA` | Entrega verificada y conforme (`verificar-entrega`, decision `cerrar`) |
| 3 `ESTADO_DEVOLUCION` | Entrega verificada con devolución (`verificar-entrega`, decision `devolucion`) |
| 4 `ESTADO_DISPONIBLE_STOCK` | Resuelta con stock propio, sin iniciar compra (`disponible-stock`) |

`solicitudes` tiene además dos columnas de sub-estado independientes de `estado`:
- `activo` — sub-estado del trámite con el proveedor, solo relevante tras
  `asignar-proveedor`: `1` normal, `10` aplazada (`PUT .../aplazar`, con
  `fecha_aplazado`), `0` rechazada (`PUT .../rechazar`, con `motivo`). Convención
  heredada del legacy, no un enum propio.
- `anulada` — `0`/`1`, oculta la solicitud del seguimiento (`POST .../anular`) sin
  borrar sus datos.

Flujo completo:

```
solicitudes_inicial (creada por el empleado)
  crear → estado 0
    ├─ aprobar (Coordinador de su nivel, o Admin) → estado 1
    │     └─ crea la fila espejo en `solicitudes`, estado 0 (ESTADO_PENDIENTE_GESTION)
    │           ├─ disponible-stock → estado 4, fin
    │           └─ asignar-proveedor → estado 1 (FORMALIZADA)
    │                 ├─ aplazar → activo 10        (reversible, sigue en curso)
    │                 ├─ rechazar → activo 0          (fin)
    │                 ├─ anular → anulada 1            (oculta, no borra)
    │                 └─ verificar-entrega
    │                       ├─ decision cerrar → estado 2 (CERRADA)
    │                       │     └─ agregar-inventario (no cambia estado, crea filas en `inventario`)
    │                       └─ decision devolucion → estado 3 (DEVOLUCION)
    ├─ rechazar-inicial (Coordinador/Admin) → estado 3, fin (nunca se crea fila final)
    └─ cancelar (el propio solicitante, solo si seguía en 0) → estado 5, fin

Variante legada — `verificar` (decision aprobar|devolver|rechazar) hace lo mismo que
aprobar/rechazar-inicial pero registra además un checklist en
`solicitud_verificacion_inicial`; su `aprobar` interno NO crea la fila final por sí solo
— `asignar-proveedor` la crea igual si todavía no existe (`crearSolicitudFinalDesdeInicial`).
```

| Endpoint | Gate | Uso |
|----------|------|-----|
| `POST /solicitudes` | No | Crea la inicial + productos (cualquier empleado autenticado) |
| `GET /solicitudes/mias` | No | "Mis solicitudes" del propio usuario (`id_user` siempre de sesión) |
| `POST /solicitudes/{id}/cancelar` | No | Solo el dueño, solo si `estado_inicial === 0` |
| `GET /solicitudes` | Perfil (Coordinador su nivel / Admin todas) | Bandeja de aprobación; filtros `per-page`, `id_nivel`, `perfil`, `estado`, `fecha_desde`/`fecha_hasta`, `s` |
| `POST /solicitudes/{id}/aprobar` | Perfil (Coordinador su nivel / Admin) | `estado_inicial` 0→1 + crea la fila final |
| `POST /solicitudes/{id}/rechazar-inicial` | Perfil (Coordinador su nivel / Admin) | `estado_inicial` 0→3 |
| `GET /solicitudes/{id}` | 60 | Detalle con `verificacionInicial` |
| `POST /solicitudes/{id}/verificar` | 60 | Variante legada: rubros Si/No + observaciones; decision `aprobar\|devolver\|rechazar` |
| `PUT /solicitudes/{id}/aplazar` | 60 | `fecha_aplazado` + `activo=10` |
| `PUT /solicitudes/{id}/rechazar` | 60 | `motivo`/`observacion` + `activo=0`, limpia `fecha_aplazado` |
| `POST /solicitudes/{id}/agregar-inventario` | 60 | Agrega los artículos de la compra al inventario |
| `GET /solicitudes/seguimiento` | 104 | Todas las `solicitudes` no anuladas; filtros `fecha_desde`/`fecha_hasta`/`id_user`/`s` (abiertos) + `id_nivel`/`perfil` (solo si Super Admin/Admin) |
| `POST /solicitudes/{id}/asignar-proveedor` | 104 | Multipart: `id_proveedor` (perfil 17 activo), `iva`, `cotizacion_doc`; formaliza (estado 1) y copia/actualiza productos |
| `POST /solicitudes/{id}/disponible-stock` | 104 | Solo si `estado===0` y sin proveedor; pasa a estado 4 |
| `POST /solicitudes/{id}/anular` | 104 | `anulada=1` |
| `POST /solicitudes/{id}/verificar-entrega` | 105 | Multipart: rubros + `factura_doc`; decision `cerrar\|devolucion` |

Quirks del módulo:
- Archivos: cotización → `solicitudes/cotizaciones`, factura → `solicitudes/facturas`.
  Se guarda el `nombre_guardado` en `cotizacion_doc`/`factura_doc`; la URL de la
  cotización se expone en la respuesta como `url_cotizacion`.
- Los multipart de update usan `POST` (PHP no parsea campos en `PUT` multipart).
- Los FormRequest de subida necesitan `Accept: application/json` (si no, 302 a `/`).
- `fecha_ingreso` de proveedor no admite `'0000-00-00'` (MySQL strict) — forzar null.
- Las notificaciones por correo (`notificarNuevaSolicitud`, `notificarCambioEstado`) son
  no bloqueantes a propósito: un fallo de correo nunca debe impedir crear/gestionar la
  solicitud — mismo patrón que otros módulos (ver Evaluaciones/Instituciones).

### Agregar artículos al inventario (`POST /solicitudes/{id}/agregar-inventario`)

El inventario cuenta **por filas** (una unidad = una fila en `inventario`; no hay
columna `cantidad`). El endpoint (`SolicitudesController::agregarInventario` →
`InventarioServices::agregarArticulosAInventario`) crea N filas por artículo validando
que la cantidad a ingresar no supere lo solicitado:

- El rastreo de "ya ingresado" usa `inventario.id_compra` = id de la solicitud final y
  `inventario.detalles` = `"<id_producto>-<descripcion_producto>-<id_compra>"`
  (`Inventario::detalleCompra`; desde 2026-10, antes era solo `"<id_producto>"`). Para
  contar/asociar filas a un producto usar `Inventario::detalleEsDeProducto` (o en SQL
  `detalles = id OR detalles LIKE 'id-%'`), que acepta ambos formatos — comparar
  `detalles` contra el id a secas deja de contar las filas nuevas y permite ingresar más
  de lo comprado.
- Por artículo: `disponible = solicitud_productos.cantidad`; si `yaIngresado + cantidad
  > disponible` → error 422 con el detalle. Acumula dentro del mismo request.
- Cada fila: `descripcion` = nombre del producto (máx 200), `precio` = el del artículo
  (o el enviado), `estado` = 1 por defecto, `activo`=1, `confirmado`=1, `id_compra`,
  `detalles`, y `id_area`/`id_usuario`/`id_categoria`/`fecha_compra` del request.
  Registra `inventario_log` vía `registrarLog`. Todo en una transacción.
- Request: `articulos[]` con `id_producto` (debe pertenecer a la solicitud), `cantidad`
  ≥ 1, `id_area` activa (el frontend la deja cambiar; por defecto la de la solicitud),
  `id_usuario` activo, `id_categoria` (obligatoria); opcionales `estado`,
  `precio`, `fecha_compra`.
- Respuesta: `articulos_creados` + `resumen[]` con `solicitado`/`ingresado`/`restante`.

### Reportes de inventario y Visto Bueno (`InventariosController`, `InventarioServices`, `routes/api/inventario.php`)

Reemplaza al módulo legacy `vistas/modulos/reportes` (`index.php` + `visto.php` +
`excelInventario.php`). Un `inventario` puede tener varias filas en `reportes`: la
original (`id_reporte IS NULL`, `estado` 2=reportado o 6=mantenimiento) y, cuando se
resuelve, una fila "solución" (`estado=3`) con `id_reporte` apuntando a la original.

- **`GET /inventario/reportes`** (`mostrarReportesDeInventario`) — listado de reportes
  pendientes o solucionados (`estado_solucion=pendiente|solucionado`), join
  `inventario` + `reportes`. En la rama "pendientes", cuando el caller manda `estado`
  (p. ej. `/inventario/reportado` con `estado=2`, o Mantenimiento con `estado=6`), se
  exige `iv.estado = estado` — replica el filtro exacto del legacy
  (`ModeloReportes::mostrarReportesModel`: `iv.estado=2 AND rp.estado=2`). Sin `estado`
  explícito (la pestaña general "Reportes") se usa el filtro amplio
  `iv.estado NOT IN (4,5)`, para no ocultar mantenimientos. **Antes de este ajuste** el
  filtro amplio se aplicaba siempre, y colaba inventario cuyo `iv.estado` ya había
  cambiado por otra vía pero conservaba un reporte sin resolver — si el listado de
  "Reportado"/"Mantenimiento" vuelve a mostrar filas que no debería, revisar primero que
  el frontend siga mandando `estado` en `externalFilters`. El parámetro `sin_solucion`
  se acepta pero no se usa en la query (queda muerto, la rama pendientes/solucionados ya
  la decide `estado_solucion`).
- **`PUT /inventario/reportes/solucionar`** (`solucionarReporteInventario`) — crea la
  fila "solución" (`estado=3`). Guarda el texto de la solución en **`descripcion`**, no
  en `observacion` (`observacion` queda `NULL` en la fila de solución). **Quirk de
  datos migrados**: el legacy (`ModeloReportes::solucionarReporteModel`) guardaba ese
  mismo texto en `observacion` — los reportes solucionados antes de la migración a
  Laravel tienen el texto en `observacion`, no en `descripcion`. Cualquier vista que
  muestre "la solución" de un reporte debe leer `descripcion` con fallback a
  `observacion` (ver `VistoBueno/index.tsx` en el frontend).
- **Visto bueno** (equivalente a `visto.php`) — tres endpoints nuevos:
  - `GET /inventario/reportes/visto-bueno` (`reportesPendientesVistoBueno`) — filas
    `reportes` con `estado=3 AND visto_bueno=0`, con `inventario.area` y `usuario`
    eager-loaded. Acepta `search` (contra `descripcion`/`codigo`/`marca` del inventario)
    y `per_page`.
  - `PUT /inventario/reportes/{id}/visto-bueno` (`vistoBuenoReporte`) — marca una fila.
  - `PUT /inventario/reportes/visto-bueno` (`vistoBuenoGeneral`) — marca todas las
    `estado=3 AND visto_bueno=0` (el legacy también tocaba `reportes_zonas`, tabla de
    otro módulo no migrado aún — si aparece, replicar ahí también).

### Orden de los listados de inventario

Ninguno de los dos listados tenía `ORDER BY` alfabético por defecto — ambos se
corrigieron para ordenar por `inventario.descripcion` cuando no hay un sort explícito:

- **`GET /inventario/listado`** (`obtenerListadoInventario`, usado por
  `InventarioPorEstado` — Liberado/Descontinuado/Mis Inventarios) — sin `sort=usuario`
  ni `sort=cantidad`, ahora aplica `orderBy('inventario.descripcion', $dir)`. Antes no
  tenía ningún `ORDER BY` (orden indefinido de la BD).
- **`GET /inventario/listado-consolidado`** (`obtenerListadoConsolidado`, modo
  agrupado — el que realmente usa la página `/inventario/listado`) — antes ordenaba por
  `MAX(inventario.id) DESC` (lo más reciente primero); ahora por
  `inventario.descripcion` ascendente. Es el endpoint que importa si "el listado
  principal de inventario" vuelve a reportarse como desordenado.

## Áreas Comunes (`/inventario/areas-comunes` — `BloquesController` + `AreasComunesController`)

Módulo para administrar inventario ubicado en espacios físicos compartidos (patios,
salas, canchas...), organizado en **Bloques** → **Áreas** → ítems de `inventario` con
`categoria.tipo_categoria = 3` ("Área Común"). Reutiliza `InventarioServices` para
reportar/solucionar/descontinuar/mover — no duplica esa lógica, `AreasComunesController`
solo agrega las acciones propias del módulo (reclasificar, check semestral, mover,
historial/indicador de checks). Rutas en `routes/api/bloques.php`.

### Permisos (`cron_opciones`, módulo 6 "Zonas" — legacy, reusado solo como agrupador;
sus tablas legacy `zonas`/`areas_zona`/`chek_zonas`/`reportes_zonas` no se tocan)

| Id | Nombre | Perfiles otorgados | Alcance |
|---|---|---|---|
| `108` | Administrador áreas comunes | Super Admin (1), Administrador (2) | Todo el módulo, sin recorte |
| `109` | Uso áreas comunes | Asistente de nivel (11), Coordinador (26) | Todo el módulo, pero `obtenerTodosLosBloques` recorta server-side a `id_nivel` del usuario cuando NO tiene también la 108 |
| `119` | Mis áreas comunes (autoservicio) | Asistente de nivel (11), Coordinador (26) — mismos perfiles que pueden ser responsables de un bloque (`BloquesServices::PERFILES_RESPONSABLES`) | Solo `/mis-areas`: bloques donde el usuario autenticado figura como responsable (`bloque_usuario`) — ver `BloquesServices::obtenerBloquesResponsable` |

Ambos controllers gatean por método vía el patrón `sinAcceso()` (no constructor-wide):
`crearBloque`/`actualizarBloque`/`desactivarBloques`/`asignarResponsables`/
`usuariosAsignablesBloque`/`asignarAreasBloque`/`reclasificarInventario`/`moverItem`
exigen **108**; `registrarCheck`/`historialChecks`/`indicadorChecks` aceptan **108 o
109**; `misBloques` exige **119** en solitario (`BloquesController::OPCION_MIS_AREAS_COMUNES`).
**118 y 117 no son permisos de este módulo** — `117`/`118` fueron ids provisionales
usados por error en el sidebar del frontend antes de confirmarse contra `cron_opciones`
en producción que el id real de "Mis áreas comunes" es `119` (corregido 2026-09-21); no
reintroducir esos ids acá.

### Endpoints (`routes/api/bloques.php`)

| Endpoint | Gate | Método/servicio |
|---|---|---|
| `GET /` | 108 o 109 | `obtenerTodosLosBloques` — recorta por nivel si solo tiene 109 |
| `GET /mis-bloques` | 119 | `obtenerBloquesResponsable` |
| `POST /` / `PUT /` | 108 | `crearBloque` / `actualizarBloque` |
| `POST /estado` | 108 | `desactivarBloques` (activar/desactivar en lote) |
| `POST /responsables` | 108 | `asignarResponsables` (sync de `bloque_usuario`) |
| `GET /usuarios-asignables` | 108 | usuarios activos con perfil 11 o 26 |
| `POST /areas` | 108 | `AreasComunesController::asignarAreasBloque` |
| `POST /inventario/reclasificar` | 108 | reclasifica ítems YA existentes a una categoría `tipo_categoria=3`, sin mover su `id_area`/`id_bloque` |
| `POST /inventario/check` | 108 o 109 | check semestral en lote — ver quirks abajo |
| `POST /inventario/mover` | 108 | mueve un ítem a otro bloque/área (`id_area=null` = directo al bloque) |
| `GET /inventario/checks` | 108 o 109 | `historialChecks` — filtra por ítem/bloque/área/año/periodo/responsable |
| `GET /inventario/checks/indicador` | 108 o 109 | `indicadorChecksAreasComunes` — % de áreas con al menos un check |

### Quirks

- **Check semestral (`registrarCheckInventario`)**: migración de `chek_zonas` legacy — un
  lote de ids se omite (sin bloquear el resto) cuando el ítem tiene un reporte/mantenimiento
  genuinamente pendiente (`estado IN [2,6]` sin fila de solución vinculada) o cuando ya
  existe un check para ese mismo `id_anio` **y** `periodo` — un año puede tener varios
  checks, uno por periodo, sin restricción contra cuál sea el periodo institucional
  "vigente" en ese momento.
- **`id_bloque` derivado en `historialChecks`/`indicadorChecksAreasComunes`**: un ítem
  reclasificado a Área Común normalmente nunca llega a tener `inventario.id_bloque`
  propio, solo `id_area` — ambos métodos resuelven el bloque real con un
  `whereHas`/`whereExists` que cae al `id_bloque` del área cuando el directo es nulo.
  Cualquier query nueva que filtre áreas comunes por bloque debe replicar ese mismo
  fallback o perderá silenciosamente los ítems asignados a un área puntual.
- **`reclasificarAreaComun` vs. `moverItemAreaComun`**: el primero solo cambia
  `id_categoria` (el ítem se queda donde ya estaba); el segundo cambia la ubicación real
  (`id_bloque`/`id_area`). No confundirlos — son las dos acciones "Agregar área común
  existente" y "Mover" del frontend, respectivamente.

### Notificación por correo (`InventarioServices::notificarAreaComun`)

Reportar un daño, programar mantenimiento preventivo o registrar un check semestral
sobre un ítem de Área Común (`categoria.tipo_categoria = 3`) dispara un correo
**adicional** al genérico que ya enviaba cada flujo (ese sigue intacto, va a
grupo `CRONOGRAMA_SISTEMAS` + responsable/reportador) — este es específico del módulo,
asunto `"Notificación | Área Común — {movimiento}"`, y siempre se refiere al ítem como
"Área común", nunca como "inventario".

- **Destinatarios**: todos los responsables del bloque (`Bloque::responsables()`,
  `bloque_usuario` — puede haber varios) + el responsable del inventario
  (`inventario.id_user`) + Dirección Administrativa. **El responsable del área y el
  responsable del inventario NO son necesariamente la misma persona** — son dos campos
  independientes (`bloque_usuario` se asigna desde "Responsables" en Bloques;
  `inventario.id_user` se elige aparte, como "Usuario responsable", al crear/asignar el
  ítem) — confirmado en código, no asumir que coinciden.
- **Dirección Administrativa**: `Mails::DIRECCION_ADMINISTRATIVA->recipients()`
  (`app/Enums/Mails.php`), que lee `correos_institucionales` filtrando por
  `grupo = 'DIRECCION_ADMINISTRATIVA'` y `activo = true` — mismo mecanismo que usa
  `NoticiasService` para sus listas de distribución (`GRUPOS_DISTRIBUCION`), reutilizado
  tal cual, sin tabla ni lógica propia.
- **Contenido fijo**: Bloque, Área (si existe), Área común (la `descripcion` del ítem),
  Responsable del área, Responsable del inventario, Movimiento, Fecha del movimiento
  (la real del mantenimiento programado en ese caso, no `now()`) y quién lo realizó (el
  usuario logueado que ejecutó la acción).
- **Un solo método cubre los tres triggers**: `notificarAreaComun(array $entradas,
  string $movimiento, ?int $idActor)` recibe `[['inventario' => Inventario, 'fecha' =>
  ?Carbon], ...]` e **ignora en silencio** cualquier ítem que no sea Área Común — así
  `reportarInventario` y `programarMantenimientoPreventivo` (compartidos con el módulo
  general de Inventario) pueden pasarle la misma lista de ítems que ya procesaron, sin
  filtrar antes por categoría. Se llama desde:
  - `reportarInventario` → movimiento "Reporte de daño".
  - `programarMantenimientoPreventivo` → movimiento "Mantenimiento preventivo
    programado".
  - `registrarCheckInventario` → movimiento "Check semestral registrado" (antes no
    enviaba ningún correo; el `select(['id', 'descripcion'])` original se amplió a
    también traer `id_area`/`id_bloque`/`id_categoria`/`id_user`, necesarios para
    resolver bloque/responsables).
- Cualquier excepción al construir o enviar el correo se registra en el log
  (`Log::error`) y no interrumpe la operación de negocio (reporte/mantenimiento/check ya
  quedó guardado en BD de todas formas).

## Evaluaciones (`/evaluaciones` — `EvaluacionesController`)

Módulo de **evaluaciones de calidad de servicios / desempeño** (Gestor de
Calidad, no académico): un Coordinador (perfil `26`) evalúa periódicamente
(hasta 3 veces al año, una por `periodo` institucional) a los usuarios de
ciertos perfiles/niveles a su cargo — ej. Docentes de su propio nivel,
Proveedores. Estructura jerárquica: `Evaluacion` → `EvaluacionServicio`
(catálogo del "servicio" evaluado) → `EvaluacionSeccion` (ponderada por
`porcentaje`) → `EvaluacionPregunta` (tipada vía `EvaluacionTipoPregunta`) →
`EvaluacionOpcionPregunta` (cada una con un `valor` numérico). Cada
`EvaluacionRespuestaEvaluacion` es la respuesta completa de un evaluador a un
evaluado en un periodo, con sus `EvaluacionRespuestaPregunta` hijas.

### Tablas (10)

La mayoría son legacy sin migración en este repo (mismo patrón que
`cron_opciones`/`anio_escolar`); las tres marcadas sí tienen migración propia,
añadida en 2026-08 al construir el flujo de "realizar evaluaciones".

| Tabla | Modelo | PK | Timestamps | Migración propia |
|-------|--------|----|------------|-------------------|
| `evaluaciones` | `Evaluacion` (SoftDeletes) | `id` | sí | no |
| `evaluaciones_servicios` | `EvaluacionServicio` | `id` | sí | no |
| `evaluaciones_tipos_pregunta` | `EvaluacionTipoPregunta` | `id` | no | no (filas via migraciones puntuales, ver "Tipos de pregunta" abajo) |
| `evaluaciones_secciones` | `EvaluacionSeccion` | `id` | sí | no |
| `evaluaciones_preguntas` | `EvaluacionPregunta` | `id` | sí | no |
| `evaluaciones_opciones_pregunta` | `EvaluacionOpcionPregunta` | `id` | no | no |
| `evaluaciones_nivel` | `EvaluacionNivel` (pivote `Evaluacion`↔`Nivel`) | `id` | no | no |
| `evaluaciones_perfil` | `EvaluacionPerfil` (pivote `Evaluacion`↔`Perfil`, perfiles evaluables) | `id` | no | sí — `2026_08_26_100000_create_evaluaciones_perfil_table` |
| `evaluaciones_respuestas_evaluacion` | `EvaluacionRespuestaEvaluacion` | `id` | sí | columnas `id_evaluado`/`id_periodo`/`id_anio_escolar` añadidas vía migración (ver abajo) |
| `evaluaciones_respuestas_pregunta` | `EvaluacionRespuestaPregunta` | `id` | sí | no |

`evaluaciones_respuestas_evaluacion` empezó sin `id_evaluado`/`id_periodo`
(migraciones `2026_08_26_100100_add_id_evaluado_...` y
`2026_08_28_100000_add_id_periodo_...`); el índice único
`uq_resp_eval_evaluado_periodo` (`id_evaluacion`+`id_evaluado`+`id_periodo`)
es el mecanismo real que impide evaluar dos veces al mismo usuario en el mismo
periodo — MySQL permite múltiples `NULL` en un unique, así que no rompe filas
viejas sin periodo. `id_periodo` referencia `periodos` (periodo institucional
general, **no** `periodo_academico` — eso es solo para lo académico). Por eso
la acción "Evaluar" del listado (`GET /{id}/evaluables`) queda **siempre**
visible en el frontend, incluso para un usuario ya evaluado en el periodo
activo — el evaluador puede registrar una evaluación de un periodo distinto
y el unique lo permite (solo bloquea repetir el mismo evaluado+periodo). Por
la misma razón, `evaluado`/`id_respuesta` en `obtenerEvaluables` apuntan a la
respuesta **más reciente** (`MAX(completada_en)`) del usuario para esa
evaluación, sin filtrar por periodo — así "Editar respuesta" en el frontend
siempre trae la última evaluación realizada para editarla, sea cual sea el
periodo en que se hizo, en vez de exigir que sea la del periodo activo.

`id_anio_escolar` (migración
`2026_09_02_090000_add_id_anio_escolar_to_evaluaciones_respuestas_evaluacion_table`,
FK a `anio_escolar`) se agregó porque el año que trae `periodo.id_anio` es un
dato del catálogo legacy `periodos` y puede no coincidir con el año escolar
realmente vigente (`anio_escolar.activo=1`) al momento de la respuesta —
`enviarRespuesta` lo resuelve aparte vía `AnioEscolarServices::obtenerUltimoAnioEscolar()`
(la misma fuente que el indicador "Año escolar activo" en `Responder.tsx`) y
lo guarda en la fila, en vez de derivarlo de `periodo.anioEscolar`. Cualquier
lugar que muestre "el año de una evaluación ya guardada" (ej. la columna
"Última evaluación" de `Detalle.tsx`) debe leer `respuesta.anioEscolar`
(relación `EvaluacionRespuestaEvaluacion::anioEscolar()`), no
`respuesta.periodo.anioEscolar`.

### Periodo institucional (catálogo y activo — lógica en `PeriodoServices`)

`periodos` es dato del dominio año académico, no de Evaluaciones — ver "A qué
archivo pertenece una funcionalidad" en Convenciones de código. La lógica vive
en `App\Services\AnioEscolar\PeriodoServices`:
- `listar(array $filtros)` — catálogo completo (con `anioEscolar`), filtrable
  por `activo`. Expuesto en `GET /evaluaciones/periodos`.
- `resolverActivo(): ?Periodo` — lee `periodos` filtrando `en_curso = 1`
  (columna explícita, no derivada de `periodos.activo` ni del año escolar
  activo — puede haber varios "activos" a la vez, `en_curso` es la única
  fuente confiable de "cuál es el vigente ahora"). No hay CRUD para `periodos`
  todavía, se marca a mano en BD. Consumido por `EvaluacionesServices`
  (inyectado) para el conteo de evaluados/evaluables de un periodo.
- `periodoActivo(): array` — mismo `resolverActivo()` en shape de respuesta
  API. Expuesto en `GET /evaluaciones/periodo-activo`.

Desde 2026-09, el periodo de una respuesta ya **no** se resuelve
automáticamente al enviarla — `enviarRespuesta` exige `id_periodo` explícito
en el payload (lo elige el evaluador, ver frontend `Responder.tsx`) y rechaza
con 422 si no es un periodo activo válido ("Selecciona un periodo activo
válido"). `resolverActivo()`/`periodoActivo()` siguen existiendo para el
conteo de evaluados/evaluables por periodo activo, no para fijar un valor por
defecto en el formulario de respuesta.

### Permisos (reales, ya otorgados)

Opciones en `cron_opciones`, creadas a mano el 2026-08-21 (no hay migración de
creación de las opciones en sí) y otorgadas a Super Admin por defecto; el
Coordinador (perfil `26`) recibió 102/103 vía
`2026_08_28_110000_grant_opciones_evaluaciones_a_coordinador` (idempotente,
solo inserta si no existe ya).

| Opción | Constante | Uso |
|--------|-----------|-----|
| 101 | `OPCION_ADMIN` | CRUD evaluaciones, servicios, secciones, preguntas, opciones |
| 102 | `OPCION_VER` | Lectura de evaluaciones, respuestas, resultados |
| 103 | `OPCION_RESPONDER` | Ver "mis evaluaciones", evaluables, enviar/editar respuestas, reenviar correo, descargar PDF |

Patrón de permisos: **(b)** — helper `sinAcceso()` al inicio de cada método,
vía `UsuariosServices::tieneAlgunPermiso()` (OR de varias opciones). Casi todo
endpoint de lectura acepta el OR de las tres opciones.

**Ojo con el alcance real más allá del gate de opción** — varios métodos del
servicio aplican una segunda capa de scoping por perfil que el gate de opción
por sí solo no cubre:
- **Coordinador** (`PERFIL_COORDINADOR = 26`): en `obtenerEvaluables` y
  `enviarRespuesta` solo puede ver/evaluar usuarios de **su propio nivel**
  (`usuarios.id_nivel`), aunque la evaluación cubra varios niveles; en
  `listarDisponiblesParaCoordinador` (`mis-evaluaciones`) solo ve evaluaciones
  que él mismo creó o que incluyen su nivel; en `listarRespuestas`,
  `obtenerRespuesta`, `actualizarRespuesta`, `reenviarCorreo` y
  `generarPdf` solo puede tocar sus propias respuestas (`id_user` = él).
- **Super Admin** (`PERFIL_SUPER_ADMIN = 1`): sin ninguna de esas
  restricciones — ve/edita todo.
- **`PERFILES_SIN_NIVEL = [17]`** (Proveedor): usuarios de perfiles cuyo
  `id_nivel` legítimamente queda `NULL`/`0` (son empresas externas, no
  personal de un nivel académico/administrativo) — el filtro de nivel se
  ignora para ellos en vez de dejarlos fuera de la lista de evaluables.

### Endpoints (`routes/api/evaluaciones.php`)

Todos bajo prefijo `/api/evaluaciones`, middleware `auth:api` + `system:general`.

#### Catálogo de servicios

| Método | Ruta | Gate | Uso |
|--------|------|------|-----|
| `GET` | `/servicios` | 102, 101, 103 | Listar servicios activos |
| `POST` | `/servicios` | 101 | Crear servicio |
| `PUT` | `/servicios/{id}` | 101 | Actualizar servicio |
| `DELETE` | `/servicios/{id}` | 101 | Deshabilitar servicio (`activo=0`, soft-disable) |

#### Tipos de pregunta

| Método | Ruta | Gate | Uso |
|--------|------|------|-----|
| `GET` | `/tipos-pregunta` | 102, 101 | Listar tipos de pregunta (con `opciones` por defecto anidadas; Encuestas usa el mismo payload) |
| `POST` | `/tipos-pregunta` | 122 | Crear tipo (`nombre`; `slug` derivado con `Str::slug(nombre, '_')`, único). Un slug desconocido se responde como selección única |
| `POST` | `/tipos-pregunta/{idTipo}/opciones` | 122 | Crear opción por defecto (`texto`, `valor`, `orden` opcional) |
| `PUT` | `/tipos-pregunta/opciones/{id}` | 122 | Editar opción por defecto |
| `DELETE` | `/tipos-pregunta/opciones/{id}` | 122 | Eliminar opción por defecto |

Las opciones por defecto (`evaluaciones_tipos_pregunta_opciones`) solo las usa el
frontend para precargar las opciones al crear una pregunta — se copian, así que
editarlas no cambia preguntas existentes. `valor` es el puntaje de Evaluaciones;
Encuestas lo ignora.

**Tipos: solo alta desde la API** — no hay `actualizar`/`eliminar` para
`evaluaciones_tipos_pregunta`; los tipos con comportamiento especial (slugs abajo) se agregan por migración; una fila nueva
se agrega por migración (`DB::table('evaluaciones_tipos_pregunta')->insert()`,
idempotente por `slug`), siguiendo el mismo patrón que
`grant_opciones_evaluaciones_a_coordinador`. Slugs conocidos consumidos por el
frontend (ver `PreguntaField.parts.tsx` y
`opcionesPorDefecto.helpers.ts` en el repo frontend):
`escala_likert`, `escala_real`, `si_no`, `calificacion_numerica`,
`opcion_multiple`, `seleccion_multiple` (multi-selección — se guarda como
varias filas `evaluaciones_respuestas_pregunta`, una por opción marcada, no
hay columna many-to-many propia), `texto_libre` (sin opciones, usa
`valor_texto`).

#### Evaluaciones

| Método | Ruta | Gate | Uso |
|--------|------|------|-----|
| `GET` | `/` | 102, 101 | Listado paginado (admin); filtros: `id_servicio`, `activo`, `s` (busca en título/descripción), `per-page` |
| `POST` | `/` | 101 | Crear evaluación + niveles + perfiles + secciones anidadas (transacción) |
| `GET` | `/{id}` | 102, 101, 103 | Detalle completo: servicio, niveles, perfiles, secciones→preguntas→tipo→opciones, count respuestas |
| `PUT` | `/{id}` | 101 | Actualizar campos + niveles/perfiles (solo si la key existe en el payload) |
| `DELETE` | `/{id}` | 101 | Soft delete (`Evaluacion` usa `SoftDeletes`) |
| `PUT` | `/{id}/toggle-activo` | 101 | Alterna `activo` 0↔1 |
| `GET` | `/{id}/evaluables` | 101, 103 | Usuarios evaluables (perfil+nivel de la evaluación, ver scoping arriba) + flag `evaluado`/`id_respuesta` de la evaluación **más reciente** a ese usuario para ESTA evaluación (cualquier periodo, no solo el activo — así "Editar respuesta"/"Reenviar correo"/"Descargar PDF" en el frontend siempre operan sobre la última, y "Evaluar" queda libre para registrar una nueva en un periodo distinto), + `ultima_evaluacion` (mismo `id`, con periodo/año escolar, para la columna informativa del listado) |
| `GET` | `/mis-evaluaciones` | 103, 101 | Evaluaciones activas disponibles para el solicitante (ver `listarDisponiblesParaCoordinador`), con `evaluables_count`/`evaluados_count` del periodo activo |
| `GET` | `/periodos` | sin gate | Catálogo de periodos institucionales (con año escolar), filtrable por `activo`; lógica en `PeriodoServices::listar()` (ver "Periodo institucional" arriba) |
| `GET` | `/periodo-activo` | 102, 101, 103 | Periodo institucional `en_curso=1` (con año escolar); lógica en `PeriodoServices::periodoActivo()` |

#### Secciones

| Método | Ruta | Gate | Uso |
|--------|------|------|-----|
| `POST` | `/{idEvaluacion}/secciones` | 101 | Crear sección (auto-ordena) |
| `PUT` | `/secciones/{idSeccion}` | 101 | Actualizar sección |
| `DELETE` | `/secciones/{idSeccion}` | 101 | Eliminar sección (hard delete) |

#### Preguntas

| Método | Ruta | Gate | Uso |
|--------|------|------|-----|
| `POST` | `/secciones/{idSeccion}/preguntas` | 101 | Crear pregunta + opciones anidadas |
| `PUT` | `/preguntas/{idPregunta}` | 101 | Actualizar pregunta |
| `DELETE` | `/preguntas/{idPregunta}` | 101 | Eliminar pregunta (hard delete) |

#### Opciones

| Método | Ruta | Gate | Uso |
|--------|------|------|-----|
| `POST` | `/preguntas/{idPregunta}/opciones` | 101 | Crear opción |
| `PUT` | `/opciones/{idOpcion}` | 101 | Actualizar opción |
| `DELETE` | `/opciones/{idOpcion}` | 101 | Eliminar opción (hard delete) |

#### Respuestas

| Método | Ruta | Gate | Uso |
|--------|------|------|-----|
| `POST` | `/{idEvaluacion}/responder` | 103, 101 | Enviar respuesta (transacción); `id_evaluado`, `id_periodo` (**requerido**, debe ser un periodo activo — lo elige el evaluador, no hay valor por defecto, ver "Periodo institucional" arriba), opcional `anonima`/`id_nivel`, `respuestas[]` con `id_pregunta` + (`id_opcion` y/o `valor_texto`) + `comentario` opcional. Valida perfil/nivel evaluable, scoping de Coordinador, y que no exista ya una respuesta para ese evaluado+periodo. Dispara el correo con PDF al terminar (fuera de la transacción). |
| `PUT` | `/respuestas/{idRespuesta}` | 103, 101 | Reemplaza (`delete`+`create`) las `evaluaciones_respuestas_pregunta` de una respuesta ya guardada — solo el creador o Super Admin |
| `GET` | `/{idEvaluacion}/respuestas` | 102, 101, 103 | Listado paginado; filtro `anonima`, `per-page`; Coordinador solo ve las suyas |
| `GET` | `/respuestas/{idRespuesta}` | 102, 101, 103 | Detalle de respuesta con evaluacion→servicio, evaluado, nivel, periodo→año escolar, preguntas→tipo→opciones |
| `POST` | `/respuestas/{idRespuesta}/reenviar-correo` | 103, 101 | Regenera el PDF y reenvía el correo al evaluado — solo creador o Super Admin; falla con 422 si el evaluado no tiene `correo` |
| `GET` | `/respuestas/{idRespuesta}/pdf` | 103, 101 | Descarga directa del PDF (no envía correo); responde `application/pdf` binario o, si hay error de negocio, 200 con JSON (el frontend distingue por `content-type`, mismo patrón que `fetchPdfBlob` en Biblioteca) |

#### Resultados

| Método | Ruta | Gate | Uso |
|--------|------|------|-----|
| `GET` | `/{idEvaluacion}/resultados` | 102, 101 | Promedio general ponderado por sección (`porcentaje`); desglose: `puntaje_obtenido`/`puntaje_maximo`/`promedio` |

### Estructura de evaluación (creación anidada)

`POST /evaluaciones` acepta niveles, perfiles y secciones→preguntas→opciones
en una sola llamada (transacción). Nombres de campo reales (no "titulo" en
pregunta/opción — es `texto`):

```json
{
  "titulo": "Encuesta de Calidad - Cafetería 2026",
  "id_servicio": 1,
  "niveles": [1, 2],
  "perfiles": [26],
  "secciones": [
    {
      "titulo": "Atención",
      "porcentaje": 60,
      "preguntas": [
        {
          "texto": "¿Cómo calificaría la atención recibida?",
          "id_tipo_pregunta": 1,
          "permite_comentario": 1,
          "opciones": [
            { "texto": "Bajo", "valor": 1 },
            { "texto": "Insignia Real", "valor": 4 }
          ]
        }
      ]
    }
  ]
}
```

### Correo + PDF (`EvaluacionRespuestaPdfService`, `EvaluacionRespuestaMail`)

Al guardar una respuesta (`enviarRespuesta`), `enviarCorreoRespuesta` genera un
PDF simple (TCPDF, no pixel-perfect como `PazYSalvoPdfService`) con los datos
del evaluado/servicio/periodo y, por sección, cada pregunta con su respuesta y
observación; lo envía por `MailService` al correo del evaluado como adjunto.
Se llama **fuera** de la transacción de guardado — un fallo de correo/PDF
(evaluado sin `correo`, error de TCPDF, etc.) solo queda logueado
(`Log::error`), nunca hace rollback de la respuesta ya guardada. El mismo
servicio de PDF se reutiliza para el botón "Descargar PDF" del coordinador
(`generarPdf`, sin enviar correo) y para "Reenviar correo" (`reenviarCorreo`,
regenera el PDF desde el estado vigente de la respuesta).

### Score (`calcularResultados`)

Suma `opcion.valor` de cada respuesta, divide por el máximo posible (mayor
valor por pregunta), ponderado por el `porcentaje` de cada sección. Solo
cuenta respuestas con opción seleccionada (multiple choice) — respuestas
de texto libre no aportan puntaje. **Sin consumidor en el frontend todavía**
— la página `/evaluaciones/resultados` está "en construcción"
(`EvaluacionesResultadosPage`), este endpoint no se llama desde ninguna
pantalla aún.

### Quirks

- **Validación inline** — no hay FormRequest classes; todo es `Validator::make()`
  en el controller con `response()->json()` directo (no usa `$this->error()`).
- **Hard delete** en secciones/preguntas/opciones — no hay cascade explícito;
  depende de FKs en BD (no confirmable sin migraciones). `Evaluacion` y su
  tabla de respuestas sí son soft/append-only por diseño (SoftDeletes en la
  primera, nunca se borran respuestas vía API).
- **`eliminarServicio`** es soft-disable (`activo=0`) pero `eliminar` evaluación
  es soft delete vía `SoftDeletes` (no hard) — no confundir con secciones/
  preguntas/opciones, esas sí son hard delete.
- **Toggle activo** — el mensaje puede decir "activada" cuando se desactivó
  (el flip ya pasó antes del ternario). No afecta funcionalidad.
- **`evaluaciones_respuestas_evaluacion`** — `created_at` y `completada_en`
  ambos se setean; el primero ordena, el segundo indica fin real.
- **`id_nivel` en la respuesta** — se toma de `datos['id_nivel']` si viene, si
  no del `id_nivel` del evaluado, con `?:` (no `??`) para descartar también
  `0` — los perfiles de `PERFILES_SIN_NIVEL` traen `0`/`NULL` y romperían la FK.
- **Relación `tipo()` de `EvaluacionPregunta`** se serializa como `tipo`, no
  `tipo_pregunta` — el tipo TS del frontend declara ambos campos por
  compatibilidad con código viejo, pero solo `tipo` viene poblado en
  respuestas reales del API.

## Login: verificación de dispositivo por OTP y llave maestra de recuperación

Dos mecanismos nuevos alrededor de `AuthController::login` (sistema general, no toca el
login de admisiones ni el de instituciones — cada uno ya tenía su propio patrón de
OTP-por-correo, ver secciones de Admisiones/Instituciones):

### Verificación de equipo nuevo (`dispositivos_confiables`)

**En local se puede apagar**: con `APP_ENV=local` **y** `AUTH_DEVICE_OTP=false`
(`config('auth.device_otp')`), `login()` otorga la sesión directo sin pedir código (en local
`MAIL_MAILER=log` deja el código en `storage/logs/mail/`, no en el correo). Fuera de `local`
la variable se ignora y el código se pide siempre.

`login()` ya no otorga la cookie `token` directo tras validar la contraseña: revisa la
cookie `device_token` del request contra `AuthServices::dispositivoEsConfiable`
(hash sha256 del token vs. `(id_user, token_hash)` en `dispositivos_confiables`, tabla
del tenant). Con match, sigue el flujo de siempre (incluye el intento de SSO silencioso
en SAMI, que sí tiene la contraseña en claro de esta petición). Sin match (cookie ausente,
de otro usuario, o el registro fue borrado), cae a
`iniciarVerificacionLoginGeneral`: genera un `token` de pausa (15 min,
`auth_login_pending_{token}` → `{id, connection}`) y un código de 5 dígitos aparte
(5 min, `auth_login_otp_{token}`), envía el correo (`emails/sendDeviceLoginOtp.blade.php`,
con dispositivo/IP vía `AuthServices::nombreDispositivoDesdeUserAgent`) y responde 200
(no 401) con `data.requires_otp = true` — el frontend distingue este caso de un error real
mirando ese campo, no el código HTTP (ver `LoginResponse` en el frontend).

- `POST /api/auth/login/verify-otp` (`token`, `code`) — valida contra `auth_login_otp_{token}`
  (máx. 5 intentos, `auth_login_otp_attempts_{token}`), y de pasar, contra
  `auth_login_pending_{token}` para resolver `id`+`connection`. Solo entonces
  `AuthServices::registrarDispositivoConfiable` genera un token crudo, lo guarda hasheado en
  `dispositivos_confiables`, y `HasAuthCookie::makeCookie` lo setea como `device_token` con
  TTL de **90 días** (`60 * 24 * 90` minutos — nuevo segundo parámetro `$minutes` en
  `makeCookie`, antes fijo a 1 día). No repite el SSO silencioso a SAMI: la contraseña en
  claro solo viajó en la petición de `login()` original, no en esta.
- `POST /api/auth/login/resend-otp` (`token`) — mismo rate limit por usuario
  (`auth_login_otp_send_{id_user}`, máx. 3 en 5 min) que el envío inicial, reescribe
  `auth_login_otp_{token}` con un código nuevo.
- Ambos endpoints son públicos (grupo sin `auth:api` en `routes/api/auth.php`) — a
  propósito, todavía no hay sesión en este punto del flujo.

### Llave maestra (`llaves_maestras`, connection `admin_management`)

Acceso de soporte: un Super Admin (perfil `1`) genera desde el módulo Usuarios una llave
de un solo uso para "entrar como" otro usuario sin conocer su contraseña, pensada para
alguien en un equipo sin sesión previa (soporte técnico, otro admin).
`LlaveMaestraService` (`generar`/`redimir`/`listar`/`revocar`) vive en
`admin_management` — transversal a tenants, igual que `admin_conexion_activa` — porque el
usuario destino puede estar en cualquier tenant; por eso `llaves_maestras` **no tiene FK**
a `usuarios` y guarda `connection_objetivo` en texto plano para saber dónde buscarlo al
canjear. Solo el hash de la llave (sha256) persiste, igual que `dispositivos_confiables`.

- `LlaveMaestraController` (`/api/admin-management/llaves-maestras`, dentro del grupo
  autenticado de `adminManagement.php`) — `index`/`generar`/`revocar`, gateado en el
  constructor a `perfil === 1` únicamente. **No** aplica el segundo filtro de allowlist de
  correo (`adminManagementEmails`) que sí usan otros módulos de "Administración del
  sistema" (Bases de datos, Logs por dominio) y que el frontend sí replica para decidir si
  mostrar la acción "Generar llave maestra" en Usuarios — cualquier perfil 1 puede pegarle
  directo al endpoint aunque el frontend se lo oculte. Si se decide exigir la allowlist acá
  también, el patrón a copiar es el de `BasesDatosController`/`LogDominioController`.
- `POST /api/auth/master-key/redeem` (`key`) — **público**, fuera de `auth:api` (quien
  canjea no tiene sesión todavía). Rate limit por IP (`master_key_redeem_{ip}`, máx. 10 en
  10 min). A propósito no reutiliza nada del flujo de `login()`/`verifyLoginOtp` para el
  equipo: no lee ni setea `device_token`, no llama a `registrarDispositivoConfiable`, no
  intenta el SSO de SAMI (sin contraseña en claro) — la llave ya es el segundo factor, no
  debe además "recordar" el equipo. El JWT resultante lleva `via_llave_maestra: true` y
  `generado_por` como claims extra (`JwtService::generateToken` ahora acepta un tercer
  parámetro `$extraClaims`, mergeado en el payload).
- `AuthController::check()` decodifica el payload del token (`masterKeyClaims`,
  best-effort — un fallo no tumba `/check`) y devuelve `via_llave_maestra`/`generado_por`
  junto al resto de la respuesta cuando aplica, para que el frontend pinte el banner
  persistente sin decodificar el JWT él mismo (ver `MasterKeySessionBanner` en el frontend).
- `revocar()` no borra la fila — pone `expira_en = now()`, para que quede en el historial
  como "revocada" en vez de desaparecer (mismo principio que `usado_en`: append-only,
  auditoría antes que limpieza).

## Instituciones (jardines asociados — `/api/institucion` público + `/api/instituciones-admin`)

Portal de login para **jardines infantiles asociados** (no son `usuarios` — no tienen
perfil ni nivel) que diligencian una carta de recomendación digital para sus egresados
que aplican a admisión. Dos controllers: `InstitucionController` (público, autenticación
propia por NIT) e `InstitucionAdminController` (gestión desde el módulo admin general,
`auth:api`+`system:general` normal).

### Sesión propia — deliberadamente NO usa JWT/`usuarios`

`EnsureInstitucionSession` (alias `institucion.session` en `bootstrap/app.php`) es un
middleware aparte del guard `auth:api` — una institución no es un `Authenticatable`.
Mismo patrón de token opaco en caché que ya usa `AdmissionsController` para el acudiente
(`verificacion_{token}`/`register_session_{token}`):

- `institucion_session_{token}` en `Cache`, con `expires_at` guardado dentro del propio
  valor (no solo como TTL del store) para poder devolvérselo al frontend —
  `Cache::get()` no expone el TTL restante.
- TTL: **12h** normalmente, **15 minutos en producción**
  (`app()->environment('production')` en `InstitucionController::otorgarSesion()`).
- El middleware revisa `activo` en **cada request**, no solo al hacer login — si un
  admin deshabilita la institución mientras ya está logueada, la sesión cacheada deja de
  servir de inmediato (`Cache::forget` + 401), no espera a que expire sola.
- Cookie httpOnly vía el mismo trait `HasAuthCookie` que usa el resto de la app.

### Login por NIT — flujo y seguridad

`POST /api/institucion/login` (`id_institucion`, `nit`) — el NIT se guarda **hasheado**
(`Hash::make`, columna `instituciones.nit`, `$hidden` en el modelo) y actúa como
contraseña; nunca se expone en texto plano ni en `GET /instituciones` (listado público
para el selector, solo `id`+`nombre`). Rate limit `institucion_login_{ip}_{id}` (5/10min,
`Cache::increment`+TTL). Mensaje de error genérico ("Institución o NIT incorrectos") sin
importar cuál de los dos falló, para no permitir enumeración.

El NIT correcto **por sí solo otorga la sesión** — verificar el correo no es requisito de
acceso (se pide después, ver abajo) — **excepto** cuando la institución ya tiene correo
verificado y el login llega desde una IP distinta a `ultima_ip` (columna, se actualiza en
cada `otorgarSesion()`): ahí se exige un código de un solo uso enviado a ese correo antes
de otorgar la sesión (`iniciarVerificacionLogin`/`POST verify-login-otp`, mismo patrón de
dos niveles de caché de 15min/5min que el resto del flujo). Misma IP de siempre no vuelve
a pedir nada.

### Registro/verificación del correo (una sola vez)

`POST request-email-otp` / `verify-email-otp` (autenticadas, `institucion.session`) — solo
aplica al **primer** registro: si ya hay `email_verified_at`, se rechaza con 409 (cambiar
un correo ya verificado no está cubierto). Correo único reforzado dos veces: constraint
`unique` en BD + chequeo explícito contra otras instituciones antes de reenviar OTP. Al
verificar, si el dominio del correo coincide con `ConfiguracionInstituciones::dominio_play_and_learn`
(configurable), pre-asigna `tipo_documento = 'play_and_learn'` — no reemplaza el selector
manual del admin, solo lo pre-completa (mismo helper `tipoDocumentoParaCorreo()` se
reutiliza en `InstitucionAdminController::store()`/`update()` cuando el admin fija el
correo directamente sin pasar por OTP).

### Bloqueo por correo sin registrar (`Institucion::estaBloqueada()`)

`primer_ingreso_at` arranca en el **primer login exitoso** (no en la creación del
registro). `fechaBloqueo()` = `primer_ingreso_at + ConfiguracionInstituciones::dias_plazo_bloqueo_correo`
(configurable desde el admin, default 7). Pasado ese plazo sin `email_verified_at`,
`estaBloqueada()` devuelve `true` y `guardarCartaRecomendacion` rechaza con 403 — el resto
de la sesión (login, ver documentos ya enviados) sigue funcionando, solo el envío de
cartas nuevas queda bloqueado hasta registrar el correo. Si un admin **desactiva** una
institución que aún no verificó correo, `primer_ingreso_at` se resetea a `null`
(`InstitucionAdminController::cambiarEstado`) — el tiempo deshabilitada no cuenta en su
contra; el plazo vuelve a arrancar en el próximo login tras reactivarla.

### `configuracion_instituciones` — fila única (id=1), sin `.env`

Reemplaza lo que antes vivía en `config/instituciones.php`/`.env`
(`dias_plazo_bloqueo_correo`, `correo_notificacion`) para que sea editable desde el admin
sin tocar el servidor — mismo patrón de "tabla dedicada de una fila" que
`configuracion_calendario`/`configuracion_asistencia`/`configuracion_llegadas_tarde` (no
hay tabla genérica key-value en este repo). `ConfiguracionInstituciones::actual()` =
`findOrFail(1)`. `correosNotificacion()` parsea el campo separado por comas (mismo
formato que `config/adminmanagement.php` legado). Columna `dominio_play_and_learn`
(default sembrado `playandlearn.edu.co`) se agregó después, ver
`tipoDocumentoParaCorreo()` arriba.

### Carta de recomendación — dos formatos según `instituciones.tipo_documento`

`Institucion::TIPOS_DOCUMENTO = ['coordinador_psicologo', 'play_and_learn']`. La carta se
guarda como JSON libre en `cartas_recomendacion.datos` (no columnas sueltas — el
formulario tiene demasiadas preguntas SI/NO+comentarios anidadas) más `idioma` (`es`/`en`,
Play and Learn solo existe en español). `CartaRecomendacionRequest` solo valida la forma
general (`datos: required|array`) + `datos.nombre_estudiante` como mínimo indispensable,
no cada pregunta — el formato oficial no exige responder todas.

`guardarCartaRecomendacion()` guarda primero, luego llama `generarSubirYNotificar()`
**fuera** de cualquier transacción (mismo patrón que
`EvaluacionesServices::enviarCorreoRespuesta`): un fallo generando el PDF, subiéndolo a
Cloudinary o enviando el correo solo se loguea (`Log::error`), nunca hace rollback de la
carta ya guardada.

### `CartaRecomendacionPdfService` — recreación fiel de los PDFs originales

Recrea el diseño real de los formatos originales en
`src/assets/Admissions/LettersOfRecommendation` del frontend (no un reporte genérico):
`CARTA RECOMENDACION COORD-PISCOL ESP.pdf`/`COORD PSICOL ENG.pdf` para Coordinador/
Psicólogo (ES/EN, mismo helper `drawEncabezado()`/`drawTablaRespuestas()`/etc. parametrizado
por idioma) y `CARTA RECOMENDACION P AND L.pdf` para Play and Learn (comparte los mismos
helpers de tabla/footer/firmante, con sus propios campos de encabezado). Coordenadas en
`pt` (no `mm`), logo blanco (escudo+wordmark, fondo ya recortado a transparente) en
`storage/app/public/images/instituciones/logo.png`. `checkPageBreak` de TCPDF es
`protected` — no se puede llamar desde fuera de la clase, de ahí el wrapper propio
`ensureSpacio()` que replica el chequeo para las filas dibujadas con coordenadas
manuales (tablas, campos de firmante). "Información de los padres" fuerza un salto de
página (`$pdf->AddPage()` explícito) para no repartirse a la mitad entre esa tabla y lo
que le sigue. Todo campo sin diligenciar se muestra como `"-"` (no en blanco) — incluye
un helper `drawFilaFirma()` que decodifica una firma subida como data URL base64
(`firmante.firma`, PNG/JPG) y la incrusta como imagen real sobre la línea de firma; sin
firma, queda la línea en blanco como el resto del formato en papel.

### Endpoints

**Públicos** (`routes/api/institucion.php`, prefijo `/api/institucion`):

| Método | Ruta | Auth | Uso |
|--------|------|------|-----|
| `GET` | `/instituciones` | — | Selector del login: solo `id`+`nombre`, activas |
| `POST` | `/login` | — | NIT (ver flujo arriba) |
| `POST` | `/resend-login-otp` | — | Reenvía el código de verificación por IP nueva |
| `POST` | `/verify-login-otp` | — | Verifica el código, otorga sesión |
| `GET` | `/check` | `institucion.session` | Estado de sesión para el frontend (institución, tipo_documento, bloqueo, `session_expires_at`) |
| `POST` | `/logout` | `institucion.session` | Olvida la sesión cacheada |
| `POST` | `/request-email-otp` | `institucion.session` | Primer registro de correo |
| `POST` | `/verify-email-otp` | `institucion.session` | Verifica y guarda el correo |
| `POST` | `/carta-recomendacion` | `institucion.session` | Envía la carta (genera PDF, sube, notifica) |
| `GET` | `/carta-recomendacion` | `institucion.session` | Historial propio de cartas enviadas |

**Admin** (`routes/api/instituciones-admin.php`, prefijo `/api/instituciones-admin`, dentro
de `auth:api`+`system:general`; `/configuracion` está registrado **antes** del wildcard
`/{id}`, si no se interpretaría "configuracion" como un id):

| Método | Ruta | Gate | Uso |
|--------|------|------|-----|
| `GET` | `/` | 106, 111 | Listado con estado derivado (`bloqueada`, `bloqueo_fecha`, etc.) |
| `POST` | `/` | 106 | Crear (NIT hasheado; correo opcional, si se da queda verificado de una vez) |
| `PUT` | `/{id}` | 106 | Actualizar (todos los campos `sometimes` — nunca pisa con NULL lo no enviado) |
| `PUT` | `/estado` | 106 | Activar/desactivar (resetea `primer_ingreso_at` si aplica, ver arriba) |
| `GET` | `/{id}/cartas` | 106, 111 | Documentos subidos por una institución |
| `GET` | `/configuracion` | 106 | Días de plazo, correos de notificación, dominio Play and Learn |
| `PUT` | `/configuracion` | 106 | Actualiza esos tres campos |

### Permisos — 106 (gestión completa) vs 111 (solo lectura)

**Corregido el 2026-09-22** — este bloque documentaba antes 104/105 como si fueran
literales estables; no lo son (`insertGetId`, ver "Sistema de permisos" arriba) y en esta
BD terminaron siendo 104/105 = "Compras — Gestión de compras"/"Compras — Ventas" (módulo
no relacionado), no Instituciones. Los ids reales, confirmados contra `cron_opciones`:

Dos opciones separadas en `cron_opciones`, patrón (b) (`sinAcceso()` por método, no
constructor único — ver "Sistema de permisos" arriba):

| Opción | Otorgada a | Alcance |
|--------|-----------|---------|
| 106 "Gestión de Instituciones" | Super Admin (perfil 1) | Todo — CRUD, estado, configuración, ver documentos, enviar recordatorios |
| 111 "Ver Instituciones y Documentos" | Admisiones (perfil 9) | Solo `index()`/`cartas()` — sin crear/editar/activar-desactivar/configuración |

106 se sembró primero con Super Admin **y** Admisiones
(`2026_08_31_110000_seed_opcion_gestion_instituciones`), y luego se le retiró el acceso a
Admisiones (`2026_08_31_130000_restrict_opcion_gestion_instituciones_a_super_admin`) a
pedido explícito de que el módulo completo fuera exclusivo de Super Admin. 111 se agregó
después (`2026_08_31_190000_seed_opcion_ver_instituciones_documentos`) para devolverle a
Admisiones acceso de solo lectura sin reabrir la gestión completa — `index()` y `cartas()`
aceptan el OR de ambas opciones, el resto de métodos solo acepta 106. El otorgamiento a
Admisiones (perfil 9) sobre 111 se había perdido en esta BD (mismo síntoma que el de
"Gestión de Acudientes" más abajo) — restaurado por
`2026_09_22_100832_restore_perfil_admisiones_permisos_instituciones_acudientes`.

**Mismo bug en el módulo de Acudientes** (`AcudientesAdminController::OPCION_GESTION`,
`app/Http/Controllers/Admissions/AcudientesAdminController.php`): hardcodeaba `106`
asumiendo que sería el id de "Gestión de Acudientes" (`2026_09_02_120000_seed_opcion_gestion_acudientes`,
otorgada a Super Admin y Admisiones), pero en esta BD 106 ya estaba tomado por "Gestión de
Instituciones" — el id real de "Gestión de Acudientes" es **112**. Corregido junto con lo
anterior el 2026-09-22.

### Seguimiento de documentos y recordatorios (módulo admin)

- **Conteo en el listado**: `index()` agrega a cada institución `cartas_count` y
  `ultima_carta_at` (`withCount('cartas')` + `withMax('cartas', 'created_at')` sobre la
  relación `Institucion::cartas()` → `cartas_recomendacion.id_institucion`), en la misma
  consulta — el panel de Admisiones muestra quién ya envió documentos sin pedir
  `cartas()` institución por institución. `conEstadoDerivado()` siempre incluye ambos
  campos, pero solo vienen calculados desde `index()`: en `store()`/`update()` salen en
  `0`/`null` (el frontend vuelve a pedir el listado después). Es un **conteo**, no un
  "completo/incompleto": no hay un número esperado de documentos por institución.
- **Recordatorio**: `POST /api/instituciones-admin/{id}/recordatorio`
  (`enviarRecordatorio()`, solo 106 — no la de solo lectura). Envía un correo
  `RecordatorioDocumentoInstitucionMail` (vista `emails.recordatorioDocumentoInstitucion`)
  con el documento esperado según `tipo_documento`, cuántos ha enviado y el enlace al
  portal (`config('app.frontend_url')` + `/admissions?tipo=institucion`). Rechaza 404
  (no existe), 422 (inactiva o sin `email`; un correo **sin verificar** sí se acepta) y
  devuelve **429** si `MailService::send()` lanza `MailRateLimitException` (límite por
  hora del hosting — ver "Noticias"). **Un correo por llamada, a propósito**: el envío en
  lote lo hace el frontend recorriendo las seleccionadas y se detiene en el primer 429;
  no conviertas esto en un loop server-side sin conservar ese corte.

## Convenciones de código

### Naming de directorios
| Capa | Case | Ejemplo |
|------|------|---------|
| Models | Capitalized | `app/Models/Inventario/` |
| Controllers | Capitalized | `app/Http/Controllers/Inventarios/` |
| Services | **lowercase** | `app/Services/inventario/` |
| Requests | Capitalized | `app/Http/Requests/Inventario/` |
| Routes | lowercase (excepción: `Biblioteca.php`) | `routes/api/inventario.php` |

### A qué archivo pertenece una funcionalidad (ruta / controller / service)

La lógica va en el service **del dominio dueño del dato**, no en el service del
primer módulo que la necesitó. Un módulo puede *consumir* (inyectar) el
service de otro dominio y exponerlo bajo su propia ruta/endpoint — eso es
normal y no mueve la lógica —, pero no debe reimplementar ni copiar la
consulta/regla que ya vive en el service dueño.

Ejemplo real: `periodos` (tabla y concepto de periodo institucional/año
académico) es dato del dominio **año académico**, aunque el módulo de
Evaluaciones fue el primero en necesitar "listar periodos" y "cuál es el
periodo activo". La lógica vive en `App\Services\AnioEscolar\PeriodoServices`
(`listar()`, `resolverActivo()`, `periodoActivo()`); `EvaluacionesController`
solo inyecta `PeriodoServices` y expone `GET /evaluaciones/periodos` y
`GET /evaluaciones/periodo-activo` porque ahí es donde el frontend del módulo
ya los consume — el endpoint puede quedarse en la ruta del módulo consumidor,
la lógica no. `EvaluacionesServices` también inyecta `PeriodoServices` para
resolver el periodo activo internamente (`obtenerEvaluables`,
`listarDisponiblesParaCoordinador`) en vez de tener su propia copia de la
consulta.

Al agregar una funcionalidad nueva, antes de escribirla pregúntate: ¿de qué
dominio es este dato/regla realmente? Si la respuesta es "de otro módulo que
ya tiene su propio service", inyéctalo — no dupliques ni la dejes en el
service del módulo que solo la consume.

### Controllers
```php
namespace App\Http\Controllers\Inventarios;

use App\Http\Controllers\Controller;
use App\Services\inventario\InventarioServices;

class InventariosController extends Controller
{
    protected $service_inventario;

    public function __construct(InventarioServices $service_inventario)
    {
        $this->service_inventario = $service_inventario;
    }
}
```

### Base `Controller` methods (`app/Http/Controllers/Controller.php`)
| Método | Firma | Uso |
|--------|-------|-----|
| `success()` | `(string $message, mixed $data = null, int $status = 200)` | Respuesta exitosa simple |
| `error()` | `(array\|string $message, int $status = 500, ...)` | Error directo |
| `apiResponse()` | `(array $response): JsonResponse` | **Universal**: pasa el array del service, auto-detecta error tipo database/connection/logic |
| `paginatedResponse()` | `(array $response, ?string $resourceClass)` | Paginación con resource opcional |

**IMPORTANTE**: Muchos controllers NO usan `$this->apiResponse()` y hacen inline `response()->json([...])`. Cuando crees un controller nuevo, usa `apiResponse()` si el service devuelve `['error'=>bool,...]`. Si el código existente usa inline, manten el patrón existente.

### Services
```php
namespace App\Services\inventario;

class InventarioServices
{
    public function __construct(
        private CloudinaryService $cloudinary
    ) {}

    public function listar(): array
    {
        try {
            $data = Inventario::all();
            return ['error' => false, 'message' => 'ok', 'data' => $data];
        } catch (\Exception $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }
}
```

**Convención de retorno**: `['error' => bool, 'message' => string, 'data' => mixed]`

`app/Services/Service.php` es base abstracta con `sendError()`. NO es optativa.


### Models
```php
namespace App\Models\Inventario;

use Illuminate\Database\Eloquent\Model;

class Inventario extends Model
{
    protected $table = 'inventario';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = ['nombre', 'descripcion', 'estado'];
    protected $casts = ['cantidad' => 'integer'];
    protected $attributes = ['estado' => 'disponible'];
}
```

Para modelos Usuario (JWT):
```php
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Usuario extends Authenticatable implements JWTSubject
{
    protected $table = 'usuarios';
    protected $primaryKey = 'id_user';
    public $timestamps = false;
    protected $hidden = ['pass'];

    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() { return []; }
    public function getAuthPassword() { return $this->pass; }
}
```

### Form Requests
```php
namespace App\Http\Requests\Inventario;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventarioRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        $this->merge(['nombre' => trim($this->nombre)]);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'codigo' => ['required', Rule::unique('inventario', 'codigo')],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre es obligatorio',
            'codigo.unique' => 'El código ya existe',
        ];
    }
}
```

## Guía: crear un módulo nuevo

Ejemplo: módulo "Categorías"

### 1. Modelo
`app/Models/Categorias/Categoria.php`
- Extiende `Model` (o `Authenticatable` si es usuario JWT)
- Define `$table`, `$primaryKey`, `$fillable`, `$casts`, `$attributes`, `$hidden`
- Define relaciones

### 2. Servicio
`app/Services/categorias/CategoriasServices.php` (nota: lowercase)
- Constructor con DI de otros services si necesario
- Métodos que retornan `['error'=>bool, ...]`
- Try-catch en cada método

### 3. Form Request (opcional, recomendado)
`app/Http/Requests/Categorias/StoreCategoriaRequest.php`
- `authorize()` → `true`
- `prepareForValidation()` para normalizar
- `rules()` con validaciones
- `messages()` en español
- Métodos helper `toCategoriaData()` si conviene

### 4. Controlador
`app/Http/Controllers/Categorias/CategoriasController.php`
- Extiende `Controller`
- Constructor injection del service
- Métodos: validar → llamar service → `$this->apiResponse()`, `$this->success()`, `$this->error()`, o inline `response()->json()`

### 5. Rutas
Crear `routes/api/categorias.php` con las rutas del módulo.

En `routes/api.php`, dentro del grupo `system:general`, agregar:
```php
Route::prefix('categorias')->group(function () {
    require __DIR__.'/api/categorias.php';
});
```

### 6. Singletons (si aplica)
Si el service debe ser singleton, registrarlo en `AppServiceProvider::register()`:
```php
$this->app->singleton(CategoriasServices::class);
```

### Patrón de respuesta estándar
```json
// Éxito
{ "error": false, "message": "Creado correctamente", "data": {...} }

// Error
{ "error": true, "error_type": "logic|database|connection", "message": "...", "file": "...", "line": 123 }
```

## Logo en PDFs

Todo PDF institucional nuevo debe usar **siempre** este logo fijo, no el de la marca por
dominio (`MarcaDominioService::resolverRutaLocalPorCorreo`):

```php
public_path('storage/royal-school/logotipoBackground.png')   // escudo + "COLEGIO REAL / ROYAL SCHOOL", fondo transparente
```

Ejemplo: `App\Pdf\Inventario\HojaVidaPdfService` (hoja de vida de inventario,
`GET /inventario/{id}/hoja-vida/pdf`). Los PDFs anteriores que aún resuelven el logo por
dominio (p. ej. `MantenimientoChecklistPdfService`) no se tocaron.

## Cloudinary (`app/Services/Cloudinary/CloudinaryService.php`)

| Método | Uso |
|--------|-----|
| `uploadFile(UploadedFile, folder)` | Sube archivo, retorna `['error','message','data']` |
| `getFileUrl(publicId, format)` | Genera URL |
| `deleteFile(publicId, resourceType)` | Elimina |

- **PDFs** → `resource_type: 'image'` (no raw, el service actual lo trata como image)
- **Office docs** → `resource_type: 'raw'`
- `public_id` sin extensión para `raw` (Cloudinary agrega `.temp`)
- Límite 10MB, extensiones permitidas: jpg, jpeg, png, webp, pdf

## FileStorageService (`app/Services/FileStorageService.php`)

Almacenamiento local de archivos subidos (vs Cloudinary). Usado en: `DocumentosVariosService`, `BibliotecaServices`/`BibliotecaController`, `PerfilUsuarioService`.

| Método | Firma | Uso |
|--------|-------|-----|
| `uploadFile()` | `(UploadedFile $archivo, string $carpeta = 'uploads', ?string $disk = null): array` | Guarda con nombre UUID + extensión original. Retorna `['nombre_original','nombre_guardado','ruta','url']` |
| `eliminar()` | `(?string $ruta, ?string $disk = null): bool` | Borra si existe la ruta |
| `reemplazar()` | `(UploadedFile $nuevoArchivo, ?string $archivoAnterior, string $carpeta = 'uploads', ?string $disk = null): array` | Elimina el anterior y sube el nuevo (`uploadFile`) |

- Disco por defecto: `config('filesystems.uploads_disk', 'public')` → `.env` `UPLOADS_DISK=sami` (VPS) o `public` (local).
- Disco `sami` sirve `public/upload` directamente via web server del VPS (`config/filesystems.php:57`).
- Guardar la **`ruta`** devuelta en BD (ej: columna `nombre_doc`); `url` se usa para exponerla.
- Sin DI: instancia directa `app(FileStorageService::class)` o constructor injection.

## Hikvision (`app/Services/Hikvisionattendance/hikvisionattendanceService.php` ~2200 líneas)

- Protocolo ISAPI con digest auth
- Multi-terminal: `fanOut()` para operaciones en todos los dispositivos
- Captura biométrica (`capturarHuella`, `capturarTarjeta`, `capturarRostro`) → un terminal específico (`deviceId`)
- `client(string $deviceId)` memoiza client por terminal
- `GET /hikvision/devices` lista terminales
- Config en `.env`: `HIKVISION_HOST/PORT/PROTOCOL/USERNAME/PASSWORD`, `HIKVISION_HOSTS` (formato `"Nombre@host:port,Nombre2@host2:port2"`)
- **Listado de empleados (`GET /hikvision/getList`) — paralelo + caché**: los terminales
  devuelven **máx. 30 usuarios por página** de `UserInfo/Search` (pedir más igual devuelve
  30), así que ~600 personas × 3 terminales eran ~60 llamadas en serie (**~16 s**).
  `buscarEmpleadosEnTerminales()` pide la página 0 de cada terminal y luego todas las
  demás a la vez con `GuzzleHttp\Promise\Utils::settle` (**~4,5 s**, medido contra los
  terminales reales sin fallos); `obtenerEmpleadosRegistradosDeDispositivo` también lo usa.
  `obtenerEmpleadosRegistrados` cachea el resultado **300 s** (clave por tenant
  `config('database.default')` + terminales configurados: el store es compartido) y no
  guarda listados parciales (si algún terminal falló). Se invalida con el middleware
  `OlvidarEmpleadosHikvision` en todo el grupo `/hikvision` (cualquier POST/PUT/DELETE) y
  en `eliminarTodosLosUsuariosDelDispositivo` (comando `hikvision:wipe-all`); `?fresco=1`
  la ignora (el frontend lo manda al recargar). Una escritura nueva que no pase por esas
  rutas tiene que llamar `olvidarEmpleadosRegistrados()`. Cambios hechos directo en el
  terminal se ven al vencer la caché o al recargar.

## Email

### Mailables (`app/Mail/`)
| Clase | Template | Vía |
|-------|----------|-----|
| `GenericMail` | `emails.generic` | `build()` (old) |
| `PasswordRestoreEmail` | `emails.passwordResotre` (markdown) | `envelope()`+`content()` (new) |
| `RequestEmail` | `emails.sendRequestEmail` | View |
| `RequestForm` | `emails.formAdmission` (markdown) | Stub |
| `RecordatorioPrestamosEmail` | `emails.recordatorioPrestamos` | Adjunta PDF |

### MailService (`app/Services/MailService.php`)
- `sendView($to, $subject, $view, $data)` — envía view directamente
- `sendGeneric($to, $titulo, $contenido)` — envía `GenericMail`
- `send($to, Mailable $mailable)` — envía mailable
- **Filtra correos inválidos** antes de enviar (evita excepciones que bloquean todo el lote)

### Destinatarios fijos: nunca hardcodeados (`App\Enums\Mails`)
Todo destinatario interno fijo sale de `correos_institucionales` vía
`Mails::GRUPO->recipients()` — **ninguna dirección de correo en el código** (ni en
servicios ni sembrada en migraciones): cada BD/institución carga las suyas a mano con SQL.
Grupo vacío = ese aviso no se envía (`MailService` ya ignora listas vacías).

Se administran desde el frontend en "Administración del sistema › Correos institucionales"
(`/administracion/correos-institucionales`), contra `GET|POST /api/admin-management/correos-institucionales`
y `PUT|DELETE .../{id}` (`CorreosInstitucionalesController`, perfil 1 + `admin.access`). Solo
acepta grupos del enum (`Mails::cases()`, etiqueta en `Mails::label()`): al agregar un case
nuevo, dale también su `label()`. Edita la tabla de la connection activa (BD elegida en
"Bases de datos").

| Grupo | Uso |
|-------|-----|
| `COPIA_GESTOR` | BCC de `MailService::sendGeneric()` y `send(..., copiaGestor: true)` |
| `VICERRECTORIA` | `LlegadasTarde::enviarNotificacionLlegadaTarde` (escalación por límite) |
| `RESERVAS_SISTEMAS` / `RESERVAS_MAKER_LAB` / `RESERVAS_BIBLIOTECA` / `RESERVAS_CAFETERIA` | `ReservasServices::enviarCorreoAvisoReserva` (siempre sistemas + el del salón) |
| `CRONOGRAMA_SISTEMAS` | Avisos de Inventario (`InventarioServices`) y Préstamos (`PrestamosService`) |
| `ADMISIONES_ESTADO` | `AdmisionesServices::actualizarEstadoDeInscripcionAspirante` |
| `RRHH_ASISTENCIA` | `AsistenciaGestionService::cerrarAsistenciasVencidas` (si `notificar_rh`) |
| `NOTICIAS_TODOS` | También fallback de `BannerInformativoService::enviarCorreo` sin `destinatario_correo` |
| `DIRECCION_ADMINISTRATIVA`, `NOTICIAS_*` | Ver Áreas Comunes / Noticias |

### Eventos → Listeners
| Evento | Listener | Queue | Retries |
|--------|----------|-------|---------|
| `PasswordRestore` | `SendPasswordRestore` | default | 3 (backoff 10s) |
| `RequestEmailAdmission` | `SendRequestEmailAdmission` | `emails` | 3 (backoff 60s) |
| `RequestFormAdmission` (stub) | `SendRequestFormAdmission` (stub) | — | — |

**Binding manual** en `AppServiceProvider::register()` (NO existe `EventServiceProvider.php` aunque está enlistado en `bootstrap/providers.php`).

## Configuración relevante

| Archivo | Clave |
|---------|-------|
| `config/jwt.php` | `ttl` 60min, `refresh_ttl` 20160min (14d) |
| `config/auth.php` | Default guard: `api` (jwt driver) |
| `config/cors.php` | Orígenes: localhost:3000,5173,5174,4000 + Vercel + gestorsami |
| `config/queue.php` | Default: `database`, cola `emails` para admisiones |
| `config/mail.php` | Default: `log` |
| `config/services.php` | Google OAuth, Hikvision, SAMI SSO |

## Comandos útiles

```bash
php artisan migrate
php artisan make:controller Modulo/ModuloController
php artisan make:model Models/Modulo/Modulo -m
php artisan make:request Modulo/StoreModuloRequest
php artisan cache:clear && php artisan route:clear && php artisan config:clear
php artisan queue:listen --tries=1 --queue=emails,default
```

## Notas / Quirks

- `EventServiceProvider.php` no existe en disco aunque está referenciado en `bootstrap/providers.php` — hacer binding en `AppServiceProvider::register()`.
- Muchos controllers NO usan `$this->apiResponse()` — usan `response()->json()` inline. Al crear nuevo código, usa los helpers del base Controller. Si modificas existente, respeta el patrón local.
- Servicios NO siempre extienden `Service.php` — es optativo.
- `config/cloudinary.php` — PDFs se suben como `image`, no `raw` (el service actual usa `image` para PDFs).
- `app/Services/Auth/AuthServices.php` existe pero es delgado — hoy solo cubre
  registro y dispositivos confiables (ver sección "Login: verificación de dispositivo por
  OTP y llave maestra"); el resto de la lógica de auth (login, check, tokens) sigue en
  `AuthController`/`JwtService`, no está centralizada ahí.
- `Authenticatable` vs `Model`: Usuario usa `Authenticatable` + `JWTSubject`; demás modelos usan `Model`.
