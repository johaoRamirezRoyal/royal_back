---
name: sql-optimization
description: "Optimización de consultas SQL en este backend (Laravel 12, MariaDB 10.4 —driver mysql— con tablas legacy y MyISAM, varias conexiones por colegio). Úsala cuando un endpoint esté lento, al revisar o escribir consultas pesadas (reportes, listados grandes, agregados, DB::raw/selectRaw/whereRaw), al diagnosticar N+1, al leer un EXPLAIN o al proponer o crear índices. Para patrones de Eloquent (eager loading, chunk, withCount, subconsultas) remite a laravel-best-practices (rules/db-performance.md y rules/advanced-queries.md)."
---

# Optimización de SQL en Royal Backend

Primero **medir**, luego cambiar y volver a medir. Nada de índices ni reescrituras "por si
acaso". Los patrones de Eloquent ya están en `laravel-best-practices`
(`rules/db-performance.md`, `rules/advanced-queries.md`): esta skill cubre lo que es propio
de esta base y de MariaDB (local: 10.4.32; confirma la de producción con `SELECT VERSION()`).

## Lo que hace distinta a esta base

- **La BD es externa y compartida entre entornos**, con muchas tablas legacy sin migración
  (`usuarios`, `cron_opciones`, `anio_escolar`, ...). Nunca hagas `ALTER` a mano ni recrees
  tablas: todo cambio de esquema (incluido un índice) va en una **migración** y se registra
  en `migraciones_sql.sql` (ver `AGENTS.md`).
- **Varias conexiones**: `mysql` (por defecto), `sami_hebreo` (segundo colegio) y
  `admin_management`. Un índice que hace falta en una casi siempre hace falta en todas;
  revisa que la tabla exista en cada base antes de migrar.
- **Tablas MyISAM** (`asistencia_mensaje`, `periodo_academico`, `academico_asignatura`,
  `evaluaciones_preguntas`, `evaluaciones_opciones_pregunta` y, a veces, `curso`): bloqueo por
  tabla completa en cada escritura, sin transacciones ni FK. Un `UPDATE` largo sobre ellas
  bloquea todas las lecturas. Confirma el motor:
  ```sql
  SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'curso';
  ```
- **Fechas**: filtra con valores calculados en PHP (`Carbon::today()`, zona
  `America/Bogota`), no con `CURDATE()`/`NOW()`. Además así la columna queda sin función
  encima y puede usar el índice.

## Cómo medir

1. **Cuántas consultas y cuánto tardan** en una petición:
   ```php
   DB::enableQueryLog();
   // ... llamar al service ...
   collect(DB::getQueryLog())->sortByDesc('time')->take(10);
   ```
   Muchas consultas casi iguales = N+1. Una sola que tarda = índice o forma de la consulta.
2. **El SQL real**: `$query->toRawSql()` (Laravel 12).
3. **El plan**: `DB::select('EXPLAIN ' . $query->toRawSql())` o, mejor,
   `ANALYZE SELECT ...` en tinker (sintaxis de MariaDB: ejecuta la consulta y agrega `r_rows`/`r_filtered` reales; `EXPLAIN ANALYZE` es de MySQL 8 y aquí falla). Ignora el tiempo de `php artisan serve`:
   atiende una petición a la vez (ver `docs/PRUEBAS_CARGA_2026_11_16.md`).
4. **Bajo carga**: k6 en `tests/carga/` y comparar contra la tabla del documento anterior.

## Leer un EXPLAIN

| Señal | Qué suele significar |
|---|---|
| `type = ALL` en una tabla grande | escaneo completo: falta índice o la condición no lo puede usar |
| `key = NULL` con `possible_keys` lleno | el optimizador descartó el índice (poca selectividad, función o cast sobre la columna) |
| `rows` muy por encima de lo que devuelve | el índice filtra poco; revisa el orden de columnas del compuesto |
| `Using filesort` / `Using temporary` | `ORDER BY`/`GROUP BY` que el índice no cubre |
| `Using index` | índice cubriente: no toca la tabla (bien) |
| `DEPENDENT SUBQUERY` | se ejecuta por cada fila; prueba `JOIN` o `whereIn` con subconsulta |

## Qué deja a una columna sin índice

- Funciones o casts sobre la columna: `WHERE YEAR(fecha) = 2026`, `DATE(created_at) = ?`,
  `LOWER(nombre) = ?`. Usa rangos: `whereBetween('fecha', [$inicio, $fin])`.
- Tipos distintos: comparar un `VARCHAR` con un número (pasa en ids legacy guardados como
  texto) hace cast fila por fila. Pasa el valor con el tipo de la columna.
- `LIKE '%texto%'`: no usa índice B-tree. Si es búsqueda real, `LIKE 'texto%'` o FULLTEXT.
- `OR` entre columnas distintas: a menudo rinde mejor como `UNION` o dos consultas.
- Collations distintas en un `JOIN` entre tablas legacy y nuevas: compara
  `information_schema.COLUMNS.COLLATION_NAME`.

## Índices

- Créalos a partir de una consulta medida, no por columna "importante".
- Compuestos: igualdad primero, luego rango, luego `ORDER BY`. Ejemplo:
  `WHERE id_curso = ? AND fecha BETWEEN ? AND ? ORDER BY fecha` → `(id_curso, fecha)`.
- Antes de crear, mira los existentes: `SHOW INDEX FROM tabla;`. No dupliques un prefijo de
  un índice compuesto que ya existe (ej.: `llegadas_tardes` ya tiene el único
  `(id_alumno, fecha)`, que sirve para filtrar por `id_alumno` solo).
- Cada índice encarece las escrituras; en tablas MyISAM de mucho `INSERT` (asistencia) pesa más.
- En la migración, nómbralo explícitamente y que `down()` lo quite:
  ```php
  // up()
  Schema::table('tabla', function (Blueprint $table) {
      $table->index(['id_curso', 'fecha'], 'tabla_curso_fecha_idx');
  });
  // down()
  Schema::table('tabla', fn (Blueprint $table) => $table->dropIndex('tabla_curso_fecha_idx'));
  ```
- En tablas grandes de producción, un `ALTER` puede bloquear: acuerda la ventana de deploy.

## Forma de la consulta

- Pagina o acota por periodo/año activo en vez de traer historiales completos (caso real:
  `LlegadasTarde::obtenerLlegadasTarde` en el endpoint `semana` del portal).
- Agrega en SQL (`COUNT`, `SUM`, `GROUP BY`) en lugar de traer filas y sumarlas en PHP.
- Un dato caro que cambia poco (calendarios, catálogos) puede ir a caché con invalidación al
  escribir; documenta dónde se invalida.
- `DB::raw`/`selectRaw`/`whereRaw` siempre con bindings (`?`), nunca concatenando input.

## Cerrar el cambio

- Compara antes/después: número de consultas, tiempo y `EXPLAIN`.
- Si agregaste un índice: migración + entrada en `migraciones_sql.sql` + aplicarlo en cada
  conexión que tenga la tabla.
- Si el hallazgo no es obvio, anótalo en el `docs/contexto/` del módulo.
