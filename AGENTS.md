# AGENTS.md — Royal Backend (OMNIA)

API REST en Laravel 12 (PHP ^8.2) para gestión escolar, con JWT (`tymon/jwt-auth`) en
cookie httpOnly. Aquí van las reglas generales; el contexto de cada tema está en
`docs/contexto/` (índice abajo). El frontend tiene su propio `AGENTS.md` en
`../frontend-New_S.A.M.I/S.A.M.I_frontend`.

**Siempre trabaja en la rama `main`.**

## Comandos

```bash
composer run setup   # install, .env, migrate, key:generate, npm install/build
composer run dev     # serve + queue:listen --tries=1 --queue=emails,default + npm run dev
composer run test    # config:clear + php artisan test (tests/Unit, tests/Feature)
php artisan serve    # localhost:8000
php artisan schedule:work            # el scheduler en local (composer run dev no lo corre)
php artisan cache:clear && php artisan route:clear && php artisan config:clear
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
  los ids reales. Ese archivo es solo documentación: nunca se ejecuta y no reemplaza a
  `migrate`.
- **No hardcodees correos**: los destinatarios internos salen de
  `Mails::GRUPO->recipients()` (`correos_institucionales`). Ver
  [`docs/contexto/infraestructura.md`](docs/contexto/infraestructura.md).
- **Los envíos masivos de correo pasan por lista de distribución** y respetan
  `MailRateLimitException` (ver [`modulos/noticias.md`](docs/contexto/modulos/noticias.md)).
- **Los correos y PDFs van fuera de la transacción de guardado**: si fallan, solo se
  registran en el log y nunca revierten lo guardado.
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
- **Pendiente**: los comandos programados (`asistencia:cerrar-vencidas`,
  `periodos:desactivar-vencidos`, `anio-escolar:cerrar-abrir`) solo usan la conexión por
  defecto y no recorren todos los colegios (`mysql`, `sami_hebreo`).

## Contexto por tema (`docs/contexto/`)

**Antes de tocar un área, lee su archivo.** Si agregas algo no obvio, documéntalo ahí, no
aquí. Un módulo nuevo lleva su propio `docs/contexto/modulos/<modulo>.md` y una fila en
esta tabla.

| Archivo | Léelo cuando toques... |
|---|---|
| [`permisos.md`](docs/contexto/permisos.md) | `cron_opciones`/`cron_permisos`, middleware `permiso`, `Permiso`, `/permisos` |
| [`autenticacion.md`](docs/contexto/autenticacion.md) | JWT, cookies, middlewares de sistema, OTP de dispositivo, llave maestra |
| [`infraestructura.md`](docs/contexto/infraestructura.md) | mapa de rutas, archivos y miniaturas, Cloudinary, FileStorage, logo de PDFs, correo, config, Boost |
| [`convenciones.md`](docs/contexto/convenciones.md) | plantillas de código y cómo crear un módulo nuevo |
| [`modulos/asistencia-hikvision.md`](docs/contexto/modulos/asistencia-hikvision.md) | terminales Hikvision, webhook de marcaciones, asistencia de trabajadores, QR de portería, escaneo de carnets |
| [`modulos/gestion-academica.md`](docs/contexto/modulos/gestion-academica.md) | autoservicio del docente, horarios, esquemas, franjas, años escolares, consulta de asistencias, vinculación de acudientes, seeders |
| [`modulos/gestion-convivencial.md`](docs/contexto/modulos/gestion-convivencial.md) | Observaciones, Registro de actas |
| [`modulos/noticias.md`](docs/contexto/modulos/noticias.md) | Noticias, cumpleaños, correos de distribución, rate-limit |
| [`modulos/compras.md`](docs/contexto/modulos/compras.md) | proveedores, solicitudes, agregar al inventario |
| [`modulos/inventario.md`](docs/contexto/modulos/inventario.md) | reportes, visto bueno, listados, Áreas Comunes |
| [`modulos/evaluaciones.md`](docs/contexto/modulos/evaluaciones.md) | Evaluaciones (Gestor de Calidad) y Encuestas |
| [`modulos/instituciones.md`](docs/contexto/modulos/instituciones.md) | portal de jardines asociados y su admin |

Otros documentos en `docs/`: `PERMISOS_MIDDLEWARE_2026_10_20.md`,
`HIKVISION_TERMINALES_2026_10_26.md` (terminales del `.env` a la BD),
`GESTION_ACADEMICA_HORARIOS_DOCENTE_2026_08_24.md` y `whatsapp-llegadas-tarde.md`.
