# Gestión Convivencial

Es el módulo 15 en `cron_modulos`. Sus controllers viven en el namespace
`GestionAcademica` porque reusan la carga académica.

## Observaciones (`/gestion-convivencial/observaciones`)

`ObservacionEstudianteController`, `ObservacionEstudianteService`, `AccesoObservaciones`.
Tabla `academico_observacion_estudiante`.

**Modelo**
- Una fila es una observación de un día: `fecha`, `tipo` (POSITIVA o NEGATIVA), `motivo`,
  `id_asignatura` (nullable), `id_docente`, `id_usuario_registra`, `id_curso` (el del
  estudiante **al registrar**) e `id_periodo_academico` (`periodo_academico`, no la tabla
  legacy `periodos`).
- No hay FK hacia `periodo_academico` ni `academico_asignatura` (son MyISAM); la FK a
  `curso` solo se crea si es InnoDB.

**Permisos**

| Quién | Registra a | Ve |
|---|---|---|
| Docente (3, 14) | estudiantes de sus cursos con carga activa | las suyas |
| 133 o 99 | cualquier estudiante activo | las suyas |
| 134 | nadie (sola es solo lectura) | las de todos |
| 135 (restrictiva) | **nadie**, aunque sea docente o tenga la 133/99 | las de todos |
| 139 | permite elegir el docente (`eligeDocente()` = puede registrar + 139) | — |

- Sin la 139, el `id_docente` que manda el cliente se ignora.
- Nadie edita ni elimina la observación de otro. La observación de otro ni se encuentra
  (404).
- La 133 y la 134 están otorgadas a los perfiles 1, 21, 24, 25 y 26. La 135 y la 139 no
  se otorgan por migración.
- `AccesoObservaciones` se resuelve una sola vez: el service recibe ese objeto y nunca
  lee permisos del request.
- Sin poder registrar y sin la 134 → 403.

**Reglas**
- El periodo sale de la fecha (400 si no cae en ninguno).
- **Cerrado = `fecha_fin < hoy`**: no se crea, edita, mueve ni elimina nada en él (403).
  Se compara con `Carbon::today()`, no con `CURDATE()`.
- El comando `periodos:desactivar-vencidos` pone `activo=0` a los periodos vencidos. Por
  eso las consultas usan `activo = 1 OR fecha_fin < hoy` (`periodosVisibles()`): sin eso,
  las actas desaparecían al día siguiente de cerrar el periodo.
- Asignatura: la de quien registra en el curso del estudiante. Si dicta varias es
  obligatoria (422); si dicta una se asigna sola; si no dicta ninguna va null. Al editar
  se valida contra el `id_curso` guardado.
- Una fecha futura se permite (el aviso es solo del frontend).

**Endpoints**
- `GET /`: una fila por estudiante con su última observación (`ROW_NUMBER()`, requiere
  MariaDB 10.2+), `total_rango`, `positivas_rango` y `negativas_rango`, y un `resumen` de
  **todo** el alcance calculado sobre la misma consulta (`fromSub`). Usa el contrato
  DataTable + `id_periodo`, `fecha_inicio`, `fecha_fin`, `id_curso`, `tipo` e
  `id_estudiante`. El tipo se filtra antes de agrupar.
- `GET /catalogos`: cursos, periodos (con `cerrado` y `actual`), `ver_todas`,
  `puede_registrar` y `elige_docente`.
- `GET /estudiantes?s=`: búsqueda de hasta 20 resultados, para abrir el acta de quien aún
  no tiene observaciones. Con `?todos=1` devuelve todos los del alcance, sin ese tope (lo
  usa el listado de la hoja de vida).
- `GET /estudiantes/{id}/acta?id_periodo=`: el estudiante, el periodo, las observaciones
  con `editable`, `resumen` (propias), `resumen_global` (de todos los docentes) y
  `mis_asignaturas`.
- `GET /estudiantes/{id}/dia?fecha=`: las observaciones de **otros** docentes ese día,
  completas y con `editable: false`. Usa `<=>` para no perder filas con
  `id_usuario_registra` nulo.
- `POST /`, `PUT /{id}` y `DELETE /{id}`.

La tabla y el seed se reescribieron en el lugar (hubo tres versiones el 2026-10-14). En
una BD con una versión intermedia, borra la tabla, la opción 133 y sus registros en
`migrations` antes de migrar.

## Registro de actas (`/gestion-academica/actas`)

`ActasEstudianteController`, `ActasEstudianteService`, tabla `actas_estudiante`. El
docente hace el acta en papel y alguien con permiso la registra.

**Permisos**
- **136** ve; **137** registra, edita y elimina.
- **138** ve solo los estudiantes que **hoy** están en los cursos con carga activa del
  usuario, más las actas que él mismo solicitó.
- Docente (3, 14) sin 136/137 ve solo las actas que él solicitó; esta regla gana sobre la
  138.
- Las cuatro lecturas pasan por `sinLectura()`, que llama a `limitarACursos($cursos,
  $idDocente)`. `conteo()` responde 403 si el estudiante queda fuera del alcance.

**Datos**
- Periodo y año salen de la `fecha` (422 si no cae en un periodo).
- `id_curso` es el curso del estudiante al registrar (las gráficas no cambian cuando el
  estudiante pasa de curso).
- `asunto` (máximo 200) es obligatorio en la API y nullable en la tabla solo por las
  actas anteriores.
- `id_docente_solicita` (opcional, un docente activo de los perfiles 3/14) es distinto de
  `id_usuario_registra`.
- `docente_asociado` (`DOCENTE_ASOCIADO_SQL`): si el docente que solicita tiene carga
  activa en el curso del acta. El agrupado trae `no_asociadas_total`.
- Máximo 3 actas de diálogo formativo por estudiante y periodo
  (`MAX_DIALOGO_FORMATIVO`): **no bloquea**, devuelve `advertencia`.

**Endpoints**
- `GET /`: contrato DataTable + `id_anio_escolar` (por defecto el activo),
  `id_periodo_academico`, `id_nivel`, `id_curso`, `id_estudiante`, `tipo`,
  `id_docente_solicita` y `asociacion`. Con `agrupar=1` da una fila por estudiante
  (ordenable por `total_actas`). Siempre trae `resumen` de todo el alcance.
- `GET /estadisticas`: `por_nivel`, `por_curso` y `por_periodo` (cada uno con su reparto
  por nivel), `por_anio` (histórico) y `top_estudiantes`.
- `GET /filtros`: años, periodos, niveles, cursos, estudiantes y `docentes` (con
  `foto_carnet` y `perfil`).
- `GET /conteo?id_estudiante=&fecha=[&excluir_id=]`.
- `POST /` es un **lote** (`{ actas: [...] }`, hasta 50, en una transacción: todo o
  nada); `PUT /{id}`; `DELETE /{id}`.
