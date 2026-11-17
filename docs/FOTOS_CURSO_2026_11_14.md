# Fotos del curso y mejoras del portal para padres (2026-11-14)

Cambio en `royal_back` y en el frontend, sobre la rama `feat/portal-padres` (sigue a
[`PORTAL_PADRES_Y_MARCAS_2026_11_13.md`](PORTAL_PADRES_Y_MARCAS_2026_11_13.md)). Las reglas
vigentes están en [`contexto/modulos/fotos-curso.md`](contexto/modulos/fotos-curso.md) y, del lado
del frontend, en `docs/contexto/modulos/portal-padres.md` y `DESIGN.md` («Portal para padres»).
Este documento explica qué se hizo, por qué y qué falta en el despliegue.

## Por qué

Era la primera fase pendiente del portal: que las familias vean fotos de las clases y actividades
de sus hijos. Las decisiones (2026-11-14):

- Publica cualquier docente del curso, sin aprobación previa; borra el autor o quien tenga la
  opción 172.
- Autorización de imagen: el docente la confirma al publicar (no hay campo por estudiante).
- Las fotos se ven durante el año escolar de la publicación.
- Cada publicación es de una asignatura (o «Actividad del curso») y puede ir a varios cursos.

## Qué cambió

### Backend

- Tablas `fotos_curso_publicaciones` (año, docente, asignatura o null, fecha, descripción,
  `confirma_autorizacion`), `fotos_curso_publicacion_curso` (publicación ↔ curso) y
  `fotos_curso_fotos` (rutas). Opción **172** «Fotos del curso — Ver y borrar las de todos los
  cursos» (`Permiso::FOTOS_CURSO_GESTIONAR`), otorgada por nombre a Coordinador, Vicerrector y
  Directivo Docente.
- `FotosCursoService` / `FotosCursoController` (`/api/fotos-curso`: `cursos`, `opciones`, listar
  paginado, publicar, borrar). Valida en cada publicación que el docente dicte esa asignatura en
  cada curso (o, sin asignatura, que sean sus cursos).
- Carpeta privada nueva `fotos-curso/` en `ArchivosPrivados::PATRON` y en `esFoto` (y en
  `esPrivado` de `src/utils/fileUrl.ts` del frontend): son fotos de menores, van firmadas.
- Portal (`PortalPadresController`): `GET …/hijos/{id}/semana` ya **no** trae fotos; se agregan
  `GET …/hijos/{id}/fotos?mes=` / `?q=` y `GET …/hijos/{id}/fotos/calendario` (resumen del año).
- Rendimiento: cada foto llega con `urls` firmadas del CDN (`miniatura`, `media`, `grande`); el
  navegador ya no pide cada miniatura a Laravel. Ver «Rendimiento» en el contexto del módulo.
- `2026_11_13_100000_add_index_correo_to_usuarios_table` ahora salta (con un aviso) una base de
  colegio que no existe (error 1049): en local no siempre están todas. Cualquier otro error la
  sigue deteniendo.

### Frontend

- Página **«Fotos del curso»** (`/gestion-academica/fotos-curso`, tipo DOCENTE o 172): elegir
  curso, publicar (asignatura, cursos, fecha, descripción, hasta 10 fotos reducidas en el
  navegador, confirmación de imagen), listar de a 10 («Cargar más») y borrar.
- Portal: año escolar en el friso; fotos como novedad del día; **carrusel** del mes de la semana
  (avanza solo en bucle, con pausa); **«Ver todas»** abre un calendario con búsqueda; visor a
  pantalla completa compartido (`VisorFotos`).
- Componentes y utilidades nuevos: `VisorFotos`, `comprimirFoto`, `urlFoto` (ver
  `docs/contexto/componentes.md` del frontend).

### Frontend, 2026-11-16 (sin cambios de backend)

- **El portal pasa a secciones**: antes todo iba en una columna (semana, carrusel y novedades) y
  no escalaba con notas y noticias. Ahora el friso y, en la placa, una sección a la vez: **Inicio**
  (resumen: la semana de un vistazo y el carrusel del mes en curso), **Semana** (día por día) y
  **Fotos** (el calendario con búsqueda, que deja de ser un modal). En el celular se navega con una
  barra inferior y desde 640px con pestañas; la sección va en la URL (`?ver=`).
- **Recorrido guiado** al primer ingreso (`useTutorial` + `SpotlightTour`, como los del panel) y un
  botón de ayuda en el friso para repetirlo.
- `SpotlightTour` (compartido con el panel) ubica la tarjeta según su alto real: antes podía tapar
  el elemento señalado.
- Refactor: un solo visor de fotos en la página y props uniformes entre secciones.
- Detalle: `docs/contexto/modulos/portal-padres.md` y `DESIGN.md` del frontend.

### Pruebas de estrés, 2026-11-16

k6 + Grafana sobre los endpoints del portal y de «Fotos del curso»: resultados, hallazgos y
pendientes en [`PRUEBAS_CARGA_2026_11_16.md`](PRUEBAS_CARGA_2026_11_16.md).

## Despliegue

1. Subir backend y frontend juntos: el frontend nuevo usa `…/fotos`, `…/fotos/calendario` y la
   respuesta paginada de `/api/fotos-curso`; el backend nuevo ya no manda fotos en `…/semana`.
2. `php artisan migrate` **en cada base de colegio** (`2026_11_14_100000_create_fotos_curso_tables`
   y `2026_11_14_100100_seed_opcion_fotos_curso`). SQL en `migraciones_sql.sql`. Estas migraciones
   se reescribieron durante el desarrollo (antes la publicación tenía `id_curso`): en producción se
   aplican por primera vez, así que no hay datos que migrar.
3. Revisar en `/permisos` a quién se dio la 172 en cada colegio (se otorga por nombre de perfil;
   en un colegio con otros nombres puede quedar sin otorgar).
4. Con `UPLOADS_DISK=cloudinary`, las fotos nuevas suben como `authenticated` solas (la carpeta es
   nueva, no hace falta `cloudinary:privatizar`).
5. El servidor debe aceptar subidas de varias fotos: `upload_max_filesize` ≥ 10M y `post_max_size`
   ≥ 20M (el frontend las reduce a ~0,5 MB, pero una de 10 MB sin reducir debe poder entrar).

**Las URLs firmadas no vencen**: quien tenga el enlace de una foto la abre sin sesión. Es la misma
política de las fotos de carnet y es lo que permite servirlas desde el CDN con caché. Si se decide
que estas fotos nunca salgan sin sesión, se quitan las `urls` del JSON y vuelven a pasar por
`/api/public` (más lento con muchas fotos).

## Pendientes

- Autorización de uso de imagen por estudiante (hoy solo la confirmación del docente).
- Avisar a las familias (correo o notificación) cuando hay fotos nuevas.
- Un mes ya abierto en el portal no se refresca solo si un docente publica mientras tanto (se ve al
  recargar).
- **Notas / boletín** en el portal: depende de `feat/notas-cualitativas`.
- Rendimiento: `semana` trae el historial completo de llegadas tarde y es el endpoint más caro del
  portal (~280 ms sin carga); medir la capacidad real en un servidor de pruebas. Ver
  [`PRUEBAS_CARGA_2026_11_16.md`](PRUEBAS_CARGA_2026_11_16.md).

## Pruebas en local

En `sami_royal` quedaron datos de prueba que no van a producción: `docente.prueba@royalschool.edu.co`
(id 11274; dicta Sociales en Quinto B y Noveno A y Economía en Décimo A), `padre.prueba@gmail.com`
(id 11276) y la estudiante Sofía Prueba (id 11275, Quinto B), y las publicaciones que haya hecho
ese docente. Se borran con:

```sql
DELETE f FROM fotos_curso_fotos f JOIN fotos_curso_publicaciones p ON p.id = f.id_publicacion WHERE p.id_docente = 11274;
DELETE FROM fotos_curso_publicaciones WHERE id_docente = 11274;
DELETE FROM academico_carga_academica WHERE id IN (158, 159, 160, 161);
DELETE FROM academico_docente_asignatura WHERE id IN (34, 35);
DELETE FROM estudiantes_padres WHERE id_acudiente = 11276;
DELETE FROM usuarios WHERE id_user IN (11274, 11275, 11276);
```

Los archivos de esas fotos (`storage/app/public/fotos-curso/`) se borran aparte, o antes desde la
página del docente.
