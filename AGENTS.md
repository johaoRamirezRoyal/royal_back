# AGENTS.md — Royal Backend (OMNIA.)

Laravel 12 REST API (PHP ^8.2) para gestión escolar. JWT via `tymon/jwt-auth`.

## Quick start

```bash
composer run setup        # install, .env, migrate, key:generate, npm install/build
composer run dev          # concurrently: serve + queue:listen --tries=1 --queue=emails,default + npm run dev
composer run test         # config:clear + php artisan test
php artisan serve         # dev server on localhost:8000
```

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

## Gestión Académica (`/gestion-academica` — `GestionAcademicaController`)

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

## Proceso de compra (`/proveedores` + `/solicitudes`)

Módulo de compras en dos tablas paralelas: la solicitud inicial (`solicitudes_inicial`)
y la formalizada (`solicitudes`). Opciones del módulo 9 "Proceso de compra" en
`cron_opciones`: **59 Cotizaciones, 60 Listado de solicitudes, 61 Proveedores**.
La 61 y la 60 la tienen: Super Admin, Administrador, Tesorera, Asistente Contable.

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

| Estado | `solicitudes_inicial` | `solicitudes` (final) |
|--------|------------------------|------------------------|
| pendiente / formalizada | 0 | 1 (`estado`) |
| aprobada / cerrada | 1 | 2 (`estado`) |
| devuelta / devolución | 2 | 3 (`estado`) |
| rechazada | 3 | — |
| convertida | 4 | — |
| aplazada / rechazada (final) | — | `activo` 10 / 0 |

Flujo: `crear` (0) → `verificar` (aprobar 1 / devolver 2 / rechazar 3) →
`asignar-proveedor` (marca la inicial 4 y crea la `solicitudes` final estado 1) →
`aplazar`/`rechazar` (activo 10/0) → `verificar-entrega` (cerrada 2 / devolución 3).

| Endpoint | Gate | Uso |
|----------|------|-----|
| `POST /solicitudes` | No | Crea la inicial + productos (cualquier empleado) |
| `GET /solicitudes` | 60 | Paginado; filtros `per-page`, `id_user`, `id_area`, `estado`, `fecha_desde`/`fecha_hasta`, `s` (nombre/documento de usuario, ids, nombre de producto) |
| `GET /solicitudes/{id}` | 60 | Detalle con `verificacionInicial` |
| `POST /solicitudes/{id}/verificar` | 60 | Rubros Si/No + observaciones; decision `aprobar\|devolver\|rechazar` |
| `POST /solicitudes/{id}/asignar-proveedor` | 60 | Multipart: `id_proveedor` (perfil 17 activo), `iva`, `cotizacion_doc`; convierte inicial→final y copia productos |
| `PUT /solicitudes/{id}/aplazar` | 60 | `fecha_aplazado` + `activo=10` |
| `PUT /solicitudes/{id}/rechazar` | 60 | `motivo`/`observacion` + `activo=0`, limpia `fecha_aplazado` |
| `POST /solicitudes/{id}/verificar-entrega` | 60 | Multipart: rubros + `factura_doc`; decision `cerrar\|devolucion` |
| `POST /solicitudes/{id}/agregar-inventario` | 60 | Agrega los artículos de la compra al inventario |

Quirks del módulo:
- Archivos: cotización → `solicitudes/cotizaciones`, factura → `solicitudes/facturas`.
  Se guarda el `nombre_guardado` en `cotizacion_doc`/`factura_doc`; la URL de la
  cotización se expone en la respuesta como `url_cotizacion`.
- Los multipart de update usan `POST` (PHP no parsea campos en `PUT` multipart).
- Los FormRequest de subida necesitan `Accept: application/json` (si no, 302 a `/`).
- `fecha_ingreso` de proveedor no admite `'0000-00-00'` (MySQL strict) — forzar null.

### Agregar artículos al inventario (`POST /solicitudes/{id}/agregar-inventario`)

El inventario cuenta **por filas** (una unidad = una fila en `inventario`; no hay
columna `cantidad`). El endpoint (`SolicitudesController::agregarInventario` →
`InventarioServices::agregarArticulosAInventario`) crea N filas por artículo validando
que la cantidad a ingresar no supere lo solicitado:

- El rastreo de "ya ingresado" usa `inventario.id_compra` = id de la solicitud final y
  `inventario.detalles` = id del `solicitud_productos`.
- Por artículo: `disponible = solicitud_productos.cantidad`; si `yaIngresado + cantidad
  > disponible` → error 422 con el detalle. Acumula dentro del mismo request.
- Cada fila: `descripcion` = nombre del producto (máx 200), `precio` = el del artículo
  (o el enviado), `estado` = 1 por defecto, `activo`=1, `confirmado`=1, `id_compra`,
  `detalles`, y `id_area`/`id_usuario`/`id_categoria`/`fecha_compra` del request.
  Registra `inventario_log` vía `registrarLog`. Todo en una transacción.
- Request: `articulos[]` con `id_producto` (debe pertenecer a la solicitud), `cantidad`
  ≥ 1, `id_area` activa, `id_usuario` activo, `id_categoria`; opcionales `estado`,
  `precio`, `fecha_compra`.
- Respuesta: `articulos_creados` + `resumen[]` con `solicitado`/`ingresado`/`restante`.

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
| `evaluaciones_respuestas_evaluacion` | `EvaluacionRespuestaEvaluacion` | `id` | sí | columnas `id_evaluado`/`id_periodo` añadidas vía migración (ver abajo) |
| `evaluaciones_respuestas_pregunta` | `EvaluacionRespuestaPregunta` | `id` | sí | no |

`evaluaciones_respuestas_evaluacion` empezó sin `id_evaluado`/`id_periodo`
(migraciones `2026_08_26_100100_add_id_evaluado_...` y
`2026_08_28_100000_add_id_periodo_...`); el índice único
`uq_resp_eval_evaluado_periodo` (`id_evaluacion`+`id_evaluado`+`id_periodo`)
es el mecanismo real que impide evaluar dos veces al mismo usuario en el mismo
periodo — MySQL permite múltiples `NULL` en un unique, así que no rompe filas
viejas sin periodo. `id_periodo` referencia `periodos` (periodo institucional
general, **no** `periodo_academico` — eso es solo para lo académico).

### Periodo institucional activo

`EvaluacionesServices::resolverPeriodoActivo()` lee `periodos` filtrando
`en_curso = 1` (columna explícita, no derivada de `periodos.activo` ni del año
escolar activo — puede haber varios "activos" a la vez, `en_curso` es la única
fuente confiable de "cuál es el vigente ahora"). No hay CRUD para `periodos`
todavía, se marca a mano en BD. Sin periodo activo, `enviarRespuesta` rechaza
con 422 ("No hay un periodo activo configurado").

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
| `GET` | `/tipos-pregunta` | 102, 101 | Listar tipos de pregunta |

**Catálogo de solo lectura desde la API** — no hay `crear`/`actualizar`/
`eliminar` para `evaluaciones_tipos_pregunta` en el controller; una fila nueva
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
| `GET` | `/{id}/evaluables` | 101, 103 | Usuarios evaluables (perfil+nivel de la evaluación, ver scoping arriba) + flag `evaluado`/`id_respuesta` respecto al periodo activo |
| `GET` | `/mis-evaluaciones` | 103, 101 | Evaluaciones activas disponibles para el solicitante (ver `listarDisponiblesParaCoordinador`), con `evaluables_count`/`evaluados_count` del periodo activo |
| `GET` | `/periodo-activo` | 102, 101, 103 | Periodo institucional `en_curso=1` (con año escolar) |

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
| `POST` | `/{idEvaluacion}/responder` | 103, 101 | Enviar respuesta (transacción); `id_evaluado`, opcional `anonima`/`id_nivel`, `respuestas[]` con `id_pregunta` + (`id_opcion` y/o `valor_texto`) + `comentario` opcional. Valida perfil/nivel evaluable, scoping de Coordinador, y que no exista ya una respuesta para ese evaluado+periodo. Dispara el correo con PDF al terminar (fuera de la transacción). |
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

## Convenciones de código

### Naming de directorios
| Capa | Case | Ejemplo |
|------|------|---------|
| Models | Capitalized | `app/Models/Inventario/` |
| Controllers | Capitalized | `app/Http/Controllers/Inventarios/` |
| Services | **lowercase** | `app/Services/inventario/` |
| Requests | Capitalized | `app/Http/Requests/Inventario/` |
| Routes | lowercase (excepción: `Biblioteca.php`) | `routes/api/inventario.php` |

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

`app/Services/Service.php` es base abstracta con `sendError()`. La mayoría de services NO la extienden — es optativa.

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

## Hikvision (`app/Services/Hikvisionattendance/hikvisionattendanceService.php` ~2200 líneas)

- Protocolo ISAPI con digest auth
- Multi-terminal: `fanOut()` para operaciones en todos los dispositivos
- Captura biométrica (`capturarHuella`, `capturarTarjeta`, `capturarRostro`) → un terminal específico (`deviceId`)
- `client(string $deviceId)` memoiza client por terminal
- `GET /hikvision/devices` lista terminales
- Config en `.env`: `HIKVISION_HOST/PORT/PROTOCOL/USERNAME/PASSWORD`, `HIKVISION_HOSTS` (formato `"Nombre@host:port,Nombre2@host2:port2"`)

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
- No hay `app/Services/Auth/AuthServices.php` — la lógica de auth vive en `AuthController` y `JwtService`.
- `Authenticatable` vs `Model`: Usuario usa `Authenticatable` + `JWTSubject`; demás modelos usan `Model`.
