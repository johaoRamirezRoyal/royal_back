---
name: revision-codigo
description: "Lista de revisión propia del backend de OMNIA (royal_back): las trampas de este repo que una revisión genérica no conoce (permisos en ruta y controller, MyISAM, migraciones_sql.sql, varias conexiones, correos y PDFs fuera de la transacción, doble hash de contraseña). Úsala al revisar un diff, un PR o un cambio terminado en este repo, junto con /code-review, que busca bugs generales."
---

# Revisión de código: backend OMNIA

`/code-review` busca bugs en general. Esta lista cubre lo que **solo pasa aquí**. Revisa el
diff (`git diff`, o el rango de commits) y reporta cada hallazgo con `archivo:línea`, qué
falla y en qué caso concreto. Para Eloquent y Laravel en general, `laravel-best-practices`;
para consultas lentas, `sql-optimization`.

## Permisos y seguridad

- [ ] Endpoint protegido: permiso **en la ruta** (middleware `permiso`) **y en el
  controller** (`HasMiddleware` o `sinAcceso()`), nunca en el constructor.
- [ ] Ids de opción solo desde `App\Support\Permiso`; ningún número a mano.
- [ ] Opción nueva: buscada por nombre o insertada con id explícito (no `insertGetId`) y
  otorgada en la misma migración.
- [ ] "Quién hizo esto" sale de `$request->user()->id_user`, nunca del body.
- [ ] `DB::raw`/`whereRaw`/`selectRaw` con bindings, sin concatenar input.

## Base de datos

- [ ] Migración nueva registrada al final de `migraciones_sql.sql` con su SQL real y su
  encabezado `-- ---`.
- [ ] No borra ni recrea tablas legacy sin migración (`usuarios`, `cron_opciones`,
  `anio_escolar`, ...).
- [ ] Tablas MyISAM (`asistencia_mensaje`, `periodo_academico`, `academico_asignatura`,
  `evaluaciones_preguntas`, `evaluaciones_opciones_pregunta`, a veces `curso`): nada
  depende de un `rollBack()`, no hay FK hacia ellas y se valida todo antes de escribir.
- [ ] Si el cambio aplica a todos los colegios, contempla las conexiones `mysql` y
  `sami_hebreo` (los comandos programados hoy solo usan la por defecto).
- [ ] Fechas con `Carbon::today()`/`now()` (zona `America/Bogota`), no `CURDATE()`.
- [ ] Sin N+1 nuevo en listados (eager loading) ni historiales completos sin acotar.

## Efectos secundarios

- [ ] Correos y PDFs **fuera** de la transacción de guardado; si fallan, solo log.
- [ ] Destinatarios internos con `Mails::GRUPO->recipients()`, ningún correo hardcodeado.
- [ ] Envíos masivos por lista de distribución y respetando `MailRateLimitException`.
- [ ] PDF nuevo o cambiado ⇒ definido en `src/documentos/` del frontend y generado con
  `DocumentoPdfService`, no TCPDF; tras tocarlo, `pnpm build:documentos` y commit de
  `render.cjs`.

## Trampas conocidas

- [ ] Contraseña asignada al modelo `Usuario` en claro (el mutator ya hashea); con
  `update()` masivo del query builder, sí `Hash::make`.
- [ ] Actualización multipart por `POST`, no `PUT`.
- [ ] Eventos enlazados en `AppServiceProvider::register()` (no hay `EventServiceProvider`).
- [ ] No se editó a mano el bloque `<laravel-boost-guidelines>` de `CLAUDE.md`.

## Convenciones

- [ ] Capas ruta → controller → service → modelo; la lógica en el service del dominio
  dueño del dato.
- [ ] Services devuelven `['error' => bool, 'message' => string, 'data' => mixed]`.
- [ ] Controller nuevo con `apiResponse()`/`success()`/`error()`/`paginatedResponse()`.
- [ ] Comentarios, mensajes de error y commits en español.

## Cierre

- [ ] `composer run test` pasa; si hay lógica nueva no trivial, tiene su prueba.
- [ ] Si el cambio es no obvio, quedó documentado en su `docs/contexto/`.
