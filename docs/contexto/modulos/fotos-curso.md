# Fotos del curso

Los docentes publican fotos de una clase o actividad y los acudientes las ven en la semana de
su hijo del portal para padres (`PortalPadresController::semana`). Frontend: página
`/gestion-academica/fotos-curso` y `docs/contexto/modulos/portal-padres.md`.

## Reglas (decisión del 2026-11-14)

- **Por asignatura y para varios cursos** (decisión del 2026-11-14): cada publicación es de una
  asignatura que el docente dicta (`id_asignatura`; null = «Actividad del curso», para salidas,
  actos o dirección de grupo) y va a uno o varios cursos (`fotos_curso_publicacion_curso`). Las
  fotos se suben una sola vez y se ven en todos esos cursos; borrarla la quita de todos.
- **Publica el docente** (tipo DOCENTE) solo donde corresponde (`opciones` / `puedePublicarEn`):
  con asignatura, en los cursos donde la dicta según su carga académica activa; sin asignatura, en
  cualquiera de sus cursos (carga o el que dirige, `CargaAcademicaService::obtenerCursosDocente`).
  El backend lo valida en cada publicación, sin importar lo que mande el cliente.
- **Sin aprobación**: las familias las ven al publicar. Borra el autor o quien tenga la
  **opción 174** («Fotos del curso — Ver y borrar las de todos los cursos», otorgada por nombre
  a Coordinador, Vicerrector y Directivo Docente). La 174 ve y borra en todos los cursos, pero
  **no publica** en uno donde no es docente (`puede_publicar` en `/cursos`).
- **Autorización de imagen**: no existe un campo por estudiante. Al publicar, el docente
  confirma que no aparece ningún estudiante sin autorización (`confirma_autorizacion`,
  obligatorio con `accepted`; queda guardado como registro).
- **Visibles durante el año escolar**: cada publicación guarda el `id_anio_escolar` activo
  (`AnioEscolarServices::obtenerUltimoAnioEscolar`) y el portal muestra solo las del año
  activo y del curso **actual** del hijo (`usuarios.id_curso`). Al cambiar de año dejan de
  verse, pero no se borran.
- La fecha es la de la actividad: no puede ser futura ni anterior al inicio del año activo.

## Datos y archivos

- `fotos_curso_publicaciones` (año, docente, asignatura o null, fecha, descripción hasta 500,
  `confirma_autorizacion`), `fotos_curso_publicacion_curso` (publicación ↔ curso) y
  `fotos_curso_fotos` (ruta, orden); las dos últimas con FK en cascada a la publicación. Sin FK a
  `curso` ni a `academico_asignatura` (MyISAM en algunas bases) ni a `usuarios`.
- Las fotos van con `FileStorageService::uploadFile` a `fotos-curso/<uuid>.<ext>`: **privadas**
  (`ArchivosPrivados::PATRON`) y tratadas como **fotos** (`esFoto`: van firmadas al CDN, se ven
  en listas). Al cliente llega la `ruta` y el frontend la abre por `/api/public/{ruta}?w=`.
- Hasta 10 fotos por publicación, imágenes jpg/png/webp de hasta 10 MB cada una. El frontend
  las reduce a 2048 px en el navegador antes de subirlas (`src/utils/comprimirFoto.ts`), así que
  normalmente llegan de ~0,5 MB. Aun así, el `post_max_size` del servidor debe aguantar varias
  fotos en una petición.
- Los archivos se suben antes de escribir en la BD; si la BD falla, se borran. Al borrar una
  publicación se borran primero las filas y después los archivos (un fallo del disco solo queda
  en el log).

## Endpoints (`routes/api/fotosCurso.php`)

Todos con `Permiso::ruta([174], [TipoPerfil::DOCENTE])`; el controller repite la regla y limita
al docente sin la 174 a sus cursos.

| Método | Ruta | Qué hace |
|---|---|---|
| GET | `/api/fotos-curso/cursos` | cursos del docente (con la 174, todos los activos), cada uno con `puede_publicar` |
| GET | `/api/fotos-curso/opciones` | para publicar: cada asignatura que dicta con sus cursos, y la de `id_asignatura` null con todos sus cursos |
| GET | `/api/fotos-curso?id_curso=&pagina=` | publicaciones del año activo del curso, de a 10 (`POR_PAGINA`), con `puede_borrar`; `{ publicaciones, hay_mas, total_publicaciones, total_fotos }` |
| POST | `/api/fotos-curso` | multipart: `id_asignatura?`, `id_cursos[]`, `fecha`, `descripcion?`, `fotos[]`, `confirma_autorizacion` |
| DELETE | `/api/fotos-curso/{id}` | el autor o la 174 |

El portal (PortalPadresController, después de validar el vínculo) **nunca pide el año entero**:

| Ruta | Qué devuelve |
|---|---|
| `GET /api/portal-padres/hijos/{id}/fotos?mes=YYYY-MM` | publicaciones de ese mes (semana y carrusel) |
| `GET /api/portal-padres/hijos/{id}/fotos?q=texto` | búsqueda por descripción, docente o nombre del mes (máximo 30 publicaciones) |
| `GET /api/portal-padres/hijos/{id}/fotos/calendario` | resumen del año: por día, `total` y `portada`; y el `total` del año |

## Rendimiento (muchas fotos al año)

- **URLs firmadas del CDN en el JSON**: cada foto trae `urls` (`miniatura` 256 cuadrada, `media`
  512, `grande` 1600; `f_auto,q_auto`) firmadas con `CloudinaryUploadsAdapter::recurso`. El
  navegador las pide directo a Cloudinary y las guarda en caché: antes, cada miniatura era una
  petición a Laravel por `/api/public/{ruta}` (JWT, 302 y `no-store`). En un disco local `urls` es
  null y el frontend usa `/api/public` (`src/utils/fotoUrl.ts`). Son fotos (`esFoto`), con la misma
  política que las de carnet: una URL firmada no vence.
- Por mes y con caché en el frontend (`useFotosHijo`), resumen liviano para el calendario,
  búsqueda en el servidor y paginación en la vista del docente.

## Pruebas de carga

`tests/carga/portal-padres.js` (k6 + Grafana) carga estos endpoints y los del portal. Resultado y
hallazgos: `docs/PRUEBAS_CARGA_2026_11_16.md`. Sin carga, `fotos/calendario` tarda ~200–270 ms y
`fotos?mes=` ~120 ms; si el calendario crece, cachearlo por curso. Repite la prueba tras cambios de
rendimiento.

## Pendiente

- Campo de autorización de uso de imagen por estudiante, para avisar al docente al publicar.
- Aviso a las familias (correo o notificación) cuando hay fotos nuevas.
