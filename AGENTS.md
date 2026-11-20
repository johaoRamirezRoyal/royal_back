# AGENTS.md — Royal Backend (OMNIA)

API REST en Laravel 12 (PHP ^8.2) para gestión escolar, con JWT (`tymon/jwt-auth`) en
cookie httpOnly. Aquí van las reglas generales; el contexto de cada tema está en
`docs/contexto/` (índice abajo). El frontend tiene su propio `AGENTS.md` en
`../frontend-New_S.A.M.I/S.A.M.I_frontend`.

**Siempre trabaja en la rama `main`.**

## Comandos

```bash
composer run setup   # install, .env, key:generate, migrate
composer run dev     # serve + queue:listen --tries=1 --queue=emails,default (npx concurrently)
composer run test    # config:clear + php artisan test (tests/Unit, tests/Feature)
php artisan serve    # localhost:8000
php artisan schedule:work            # el scheduler en local (composer run dev no lo corre)
php artisan cache:clear && php artisan route:clear && php artisan config:clear
# Pruebas de carga (k6 + Grafana en Docker): ver tests/carga/README.md
docker compose -f tests/carga/docker-compose.yml up -d
k6 run -o influxdb=http://localhost:8086/k6 tests/carga/portal-padres.js
```

## Reglas fijas

- **Idioma**: español en comentarios, mensajes de error, commits y documentación.
- **API 100% JSON**: no hay vistas Blade salvo las de correos.
- **La BD es externa y compartida entre entornos.** Muchas tablas core son legacy, sin
  migración en el repo (`cron_opciones`, `anio_escolar`, `usuarios`, ...): no las borres
  ni las recrees. Si algo falla con "Base table doesn't exist" en una tabla que el código
  ya usa, revisa primero si tiene migración.
- **Hay tablas MyISAM** (`asistencia_mensaje`, `periodo_academico`,
  `academico_asignatura`, `evaluaciones_preguntas`, `evaluaciones_opciones_pregunta` y, en
  algunas BD, `curso`):
  - No soportan transacciones: un `rollBack()` no deshace nada, ni siquiera en una
    prueba.
  - No admiten FK: un `Schema::create` con FK hacia una de ellas falla con `errno: 150`.
    Antes de crear la FK, consulta `information_schema.TABLES`.
  - Valida todo antes de escribir.
- **Cada institución tiene su propia base** (connections `mysql`, `sami_hebreo`,
  `sami_play` en `config/database.php`; lista centralizada en
  `BasesDatosService::connectionsConUsuarios()`). Una migración no se da por aplicada
  con un solo `php artisan migrate`: corre `php artisan migrate:colegios` (o `composer
  run migrate-colegios`), que la aplica en la base de cada colegio. En producción (FTP,
  sin SSH) esto lo dispara el workflow de deploy contra
  `POST /api/deploy/migrar-colegios` (`MigrarColegiosController`, token
  `DEPLOY_MIGRACION_TOKEN`). Las migraciones de `admin_management` declaran su propia
  `$connection` en la clase, así que ese loop no las duplica.
- **Toda migración nueva se registra en `migraciones_sql.sql`**: al final del archivo va
  el SQL real de su `up()`, con un encabezado `-- ---` con el nombre del archivo y una
  línea de qué hace y por qué. Para obtenerlo sin aplicarla:
  ```bash
  php artisan tinker --execute="
  \$m = require database_path('migrations/NOMBRE.php');
  \$q = DB::connection()->pretend(function() use (\$m) { \$m->up(); });
  foreach (\$q as \$x) { echo \$x['query'] . ';' . PHP_EOL; }"
  ```
  Con `insertGetId`, reescribe esa parte con `SET @v = LAST_INSERT_ID();` y anota al final
  los ids reales. Ese archivo es solo bitácora/auditoría del DDL: nunca se ejecuta y no
  reemplaza a `migrate:colegios`.
- **No hardcodees correos**: los destinatarios internos salen de
  `Mails::GRUPO->recipients()` (`correos_institucionales`). Ver
  [`docs/contexto/infraestructura.md`](docs/contexto/infraestructura.md).
- **Los envíos masivos de correo pasan por lista de distribución** y respetan
  `MailRateLimitException` (ver [`modulos/noticias.md`](docs/contexto/modulos/noticias.md)).
- **Los correos y PDFs van fuera de la transacción de guardado**: si fallan, solo se
  registran en el log y nunca revierten lo guardado.
- **PDFs: los documentos se están migrando de TCPDF a pdfmake.** No crees documentos nuevos
  con TCPDF (`app/Pdf`): se definen en `src/documentos/` del frontend y aquí se generan con
  `DocumentoPdfService`. Si vas a cambiar el contenido o el diseño de uno que sigue en TCPDF,
  mígralo primero. Guía y pasos: `docs/contexto/documentos.md` del frontend; qué se migró,
  deploy y pendientes: [`docs/DOCUMENTOS_PDFMAKE_2026_11_12.md`](docs/DOCUMENTOS_PDFMAKE_2026_11_12.md).
  Tras tocar un documento, `pnpm build:documentos` en el frontend y commit de `render.cjs`.
- **Fechas**: usa siempre la fecha de la app (`Carbon::today()`/`now()`, zona
  `America/Bogota`), no `CURDATE()`. "Quién hizo esto" sale de
  `$request->user()->id_user`, nunca del body.

## Permisos: lo mínimo

- Cada endpoint protegido declara su permiso **en la ruta** (middleware `permiso`) **y en
  el controller** (`HasMiddleware` o `sinAcceso()`), nunca en el constructor.
- Los ids van **solo** en `App\Support\Permiso`; nunca escribas un número a mano.
- Al crear una opción, búscala por nombre o inserta su id de forma explícita, porque
  `insertGetId` ya dio ids equivocados varias veces. Otórgala en la misma migración.
- Detalle: [`docs/contexto/permisos.md`](docs/contexto/permisos.md).

## Convenciones de código

- **El cambio más chico que resuelva todo.** Entiende el problema completo y luego reusa lo
  del repo → Laravel/PHP → paquete instalado → código nuevo mínimo, sin abstracciones ni
  paquetes de Composer que nadie pidió. Un atajo deliberado lleva un comentario `shortcut:`
  con su límite y cuándo mejorarlo. Si tienes el plugin Ponytail, invoca `ponytail:ponytail`
  al empezar una tarea de código y `ponytail:ponytail-review` al revisar.
- **Contexto mínimo.** Busca (`grep`/`glob`) antes de abrir archivos y lee solo los que toca
  la tarea; nada de auditorías globales para cambios chicos. Un archivo grande
  (`storage/logs/laravel.log` pesa decenas de MB, `migraciones_sql.sql`, JSON/CSV) nunca se
  lee entero: mide, busca y lee el rango; con el plugin Headroom, `headroom:headroom` (en
  Windows su script corre con `py`).
- **Agentes** (`.claude/agents/`, todos de solo lectura): `laravel-reviewer` (revisar un
  cambio mediano o grande), `database-analyst` (consultas e índices; nunca escribe en la
  base), `security-auditor` (auth, permisos, vulnerabilidades) y `log-analyzer` (logs y
  archivos grandes). Lo chico lo resuelve el agente principal; no encadenes delegaciones.
  Instalación y qué es personal: [`docs/contexto/claude-code.md`](docs/contexto/claude-code.md).
- Capas: ruta (`routes/api/<modulo>.php`, incluida en `routes/api.php`) → controller
  (`app/Http/Controllers/<Modulo>/`) → service (`app/Services/<modulo>/`) → modelo
  (`app/Models/<Modulo>/`). Las carpetas de services mezclan mayúscula y minúscula:
  sigue la del módulo que tocas.
- **La lógica va en el service del dominio dueño del dato**, no en el del primer módulo
  que la necesitó. Otro módulo lo inyecta y puede exponerlo bajo su propia ruta. Ejemplo:
  los periodos viven en `PeriodoServices` aunque los use Evaluaciones.
- Los services devuelven `['error' => bool, 'message' => string, 'data' => mixed]`.
  Extender `Service.php` es opcional.
- En un controller nuevo, usa `$this->apiResponse()`, `success()`, `error()` y
  `paginatedResponse()`. En código existente con `response()->json()` inline, respeta el
  patrón local.
- Plantillas de modelo, Form Request y controller, y los pasos para crear un módulo
  nuevo: [`docs/contexto/convenciones.md`](docs/contexto/convenciones.md).

## Trampas generales

- `EventServiceProvider.php` no existe aunque aparece en `bootstrap/providers.php`. Los
  eventos se enlazan en `AppServiceProvider::register()`.
- **`Usuario::setPassAttribute` ya hashea**: pasa la contraseña en claro, porque un
  `Hash::make` previo la deja con doble hash. El `update()` masivo del query builder no
  pasa por el mutator: ahí sí va `Hash::make` (test en
  `tests/Unit/Usuarios/UsuarioPassTest.php`).
- Un multipart de actualización va por `POST`, porque PHP no parsea campos en un `PUT`
  multipart. Los FormRequest de subida necesitan `Accept: application/json`; sin él,
  responden un 302 a `/`.
- `CLAUDE.md` tiene un bloque `<laravel-boost-guidelines>` que reescribe
  `boost:update`: no lo edites a mano. Si contradice una convención de este archivo, gana
  la de este archivo.
- Los comandos programados (`asistencia:cerrar-vencidas`, `periodos:desactivar-vencidos`,
  `anio-escolar:cerrar-abrir`, `periodos-administrativos:sincronizar-en-curso`) ya
  recorren todos los colegios vía el trait `EnCadaColegio`
  (`app/Console/Commands/Concerns/EnCadaColegio.php`). Un comando nuevo que deba tocar
  cada colegio reutiliza ese trait, no un loop manual.

## Contexto por tema (`docs/contexto/`)

**Antes de tocar un área, lee su archivo.** Si agregas algo no obvio, documéntalo ahí, no
aquí. Un módulo nuevo lleva su propio `docs/contexto/modulos/<modulo>.md` y una fila en
esta tabla.

| Archivo | Léelo cuando toques... |
|---|---|
| [`permisos.md`](docs/contexto/permisos.md) | `cron_opciones`/`cron_permisos`, middleware `permiso`, `Permiso`, `/permisos` |
| [`autenticacion.md`](docs/contexto/autenticacion.md) | JWT, cookies, middlewares de sistema, OTP de dispositivo, llave maestra |
| [`infraestructura.md`](docs/contexto/infraestructura.md) | mapa de rutas, archivos y miniaturas, Cloudinary, FileStorage, logo de PDFs, documentos pdfmake (`DocumentoPdfService`, requiere Node), correo, config, Boost |
| [`convenciones.md`](docs/contexto/convenciones.md) | plantillas de código y cómo crear un módulo nuevo |
| [`claude-code.md`](docs/contexto/claude-code.md) | agentes, skills, MCP de Boost y plugins de Claude Code: qué es compartido, qué es personal y qué instalar |
| [`modulos/asistencia-hikvision.md`](docs/contexto/modulos/asistencia-hikvision.md) | terminales Hikvision, webhook de marcaciones, asistencia de trabajadores, QR de portería, escaneo de carnets |
| [`modulos/gestion-academica.md`](docs/contexto/modulos/gestion-academica.md) | autoservicio del docente, cursos, niveles académicos (crear y renombrar) y catálogo de grados (`curso.id_grado`; el nivel del grado se copia a sus cursos), horarios, esquemas, franjas, años escolares, consulta de asistencias, vinculación de acudientes, seeders |
| [`modulos/gestion-convivencial.md`](docs/contexto/modulos/gestion-convivencial.md) | Observaciones, Registro de actas |
| [`modulos/noticias.md`](docs/contexto/modulos/noticias.md) | Noticias, cumpleaños, correos de distribución, rate-limit |
| [`modulos/compras.md`](docs/contexto/modulos/compras.md) | proveedores, solicitudes, agregar al inventario |
| [`modulos/inventario.md`](docs/contexto/modulos/inventario.md) | reportes, visto bueno, listados, Áreas Comunes |
| [`modulos/evaluaciones.md`](docs/contexto/modulos/evaluaciones.md) | Evaluaciones (Gestor de Calidad) y Encuestas |
| [`modulos/instituciones.md`](docs/contexto/modulos/instituciones.md) | portal de jardines asociados y su admin |
| [`modulos/carnets.md`](docs/contexto/modulos/carnets.md) | carnets institucionales: impresión, fotos, entregas y carnet digital |
| [`modulos/notas.md`](docs/contexto/modulos/notas.md) | notas: método por nivel, escala de desempeños, propósitos, referentes por grado y asignatura (con subreferentes), envío por asignatura y aprobación (opciones 152–154), periodos pasados y registro fuera de plazo, evaluación descriptiva (solo el director), planilla (el director califica sus asignaturas en la vista del curso), asistencia del boletín (a mano), título del propósito en el boletín, cierre y boletín, registro de boletines (172–173), plazo de corrección, migraciones |
| [`modulos/reservas.md`](docs/contexto/modulos/reservas.md) | reservas de salones: estados, salones con autorización del responsable |
| [`modulos/acudientes.md`](docs/contexto/modulos/acudientes.md) | acudientes (opción 175): crear, editar, estado, enviar acceso al portal para padres |
| [`modulos/fotos-curso.md`](docs/contexto/modulos/fotos-curso.md) | fotos de clases y actividades que publican los docentes para el portal para padres |

Otros documentos en `docs/`: `ARCHIVOS_PRIVADOS_CLOUDINARY_2026_11_03.md` (archivos privados
en Cloudinary: qué se cambió, deploy y pendientes), `DOCUMENTOS_PDFMAKE_2026_11_12.md` (PDFs con
pdfmake en navegador y servidor: qué se migró, deploy y pendientes), `PERMISOS_MIDDLEWARE_2026_10_20.md`,
`HIKVISION_TERMINALES_2026_10_26.md` (terminales del `.env` a la BD),
`GESTION_ACADEMICA_HORARIOS_DOCENTE_2026_08_24.md`, `PORTAL_PADRES_Y_MARCAS_2026_11_13.md`
(portal para padres, login por colegio y marcas unificadas: qué se cambió, deploy y pendientes),
`PRUEBAS_CARGA_2026_11_16.md` (prueba de estrés del portal con k6 y Grafana: resultados,
hallazgos y pendientes), `FOTOS_CURSO_2026_11_14.md` (fotos del curso, carrusel y calendario del portal: qué se cambió,
deploy y pendientes), `ACUDIENTES_Y_LOGIN_2026_11_20.md` (módulo Acudientes, creación masiva, contraseña inicial con el documento,
límite de correos, dominios institucionales por colegio y arreglos del login: qué se cambió,
deploy y pendientes) y `whatsapp-llegadas-tarde.md`.

Skills propias (`.claude/skills/`):
- `sql-optimization`: medir con query log y `EXPLAIN`, índices vía migración en todas las
  conexiones, trampas de MyISAM y tablas legacy. Complementa a `laravel-best-practices`,
  que cubre los patrones de Eloquent.
- `revision-codigo`: lista de revisión con las trampas de este repo; se usa junto con
  `/code-review` (la general de Claude Code). Si agregas una regla o trampa aquí, súmala allá.
