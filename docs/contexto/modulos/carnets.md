# Carnets institucionales (`/carnets`)

Carnets de estudiantes (perfil 16) y trabajadores (todo perfil activo salvo acudiente 6,
proveedor 17 y egresado 28: `CarnetService::PERFILES_SIN_CARNET`). Controller
`Carnets\CarnetController`, service `Carnets\CarnetService`, rutas `routes/api/carnets.php`.

- **`detalle`** (la línea bajo el nombre): el **nivel** del usuario, estudiante o trabajador
  (`usuarios.id_nivel` → `nivel.nombre`: Preescolar, Primaria, Secundaria, Administrativo…).
  No es el curso ni el perfil. El filtro por curso sigue usando `usuarios.id_curso`.

- **Permiso**: opción 144 "Carnets institucionales" (módulo 10, Carnet), en la ruta y en el
  controller (`HasMiddleware`, `except: mio`). La migración `seed_opcion_carnets` no la otorga
  a nadie: se asigna desde `/permisos`. Usa id explícito y falla si el 144 está ocupado.
- **Tabla `carnets`**: una fila por carnet impreso (`version` 1, 2, ...), con `estado`
  `IMPRESO` (o `REIMPRESO`) → `ENTREGADO` → `PERDIDO`/`ANULADO` (los dos últimos son finales; reglas en
  `CarnetService::TRANSICIONES`, test en `tests/Unit/Carnets`). Sin FK a `usuarios`.
  - `REIMPRESO`: la versión 2 en adelante (quien ya tenía carnet). Se comporta igual que
    `IMPRESO`; `CarnetService::POR_ENTREGAR` agrupa los dos. Migración
    `add_reimpreso_to_carnets_estado` (amplía el enum).
  - `POST /emitir {ids}`: crea la versión siguiente de cada uno (IMPRESO si es la primera,
    REIMPRESO si no) y anula la vigente (por entregar o ENTREGADO). Todos deben tener foto (si no, 422 con los nombres). Devuelve lo
    que se dibuja en cada carnet; el frontend imprime con eso.
  - `PUT /{id}/estado` solo sobre el último carnet del usuario. `PUT /entregar {ids}`
    entrega en lote el último carnet por entregar (IMPRESO o REIMPRESO) de cada `id_user` (los demás se omiten).
- **Foto**: es `usuarios.foto_carnet` (la legada). `POST /usuario/{id}/foto` y
  `POST /fotos` (lote, `fotos[]`, máx. 20 por petición por `max_file_uploads`) suben a
  `carnets/` en el disco de uploads. El lote asigna por nombre de archivo = documento
  (`documentoDeArchivo` quita extensión y separadores); si el documento no existe o lo
  tienen varios usuarios activos, lo reporta en `sin_asignar`. Al reemplazar, solo se borra
  la foto anterior si estaba en `carnets/`: las legadas pueden usarse en otra parte.
- **Código de barras**: CODE 128 del documento, el mismo que lee "Escanear carnets" de
  Llegadas tarde.
- `POST /vista-previa {ids}`: lo que saldría impreso, **sin emitir**. Mismas validaciones que
  `emitir` (`usuariosParaImprimir`: activos, que lleven carnet y con foto; si no, 422) y mismos
  datos, con `carnet` = el último de cada persona.
- `GET /mio` (sin opción): carnet digital del usuario en sesión, `data: null` si su perfil
  no lleva carnet. La vigencia sale de `AnioEscolarServices::obtenerUltimoAnioEscolar`.

## Plantillas (`/carnets/plantillas`, `CarnetPlantillaController`)

Mismo modelo que las plantillas de cumpleaños (`docs/contexto/modulos/noticias.md`): imagen de
fondo + `elementos` en fracciones de la imagen. Tabla `carnet_plantillas`, service
`CarnetPlantillaService`.

- **Una en uso por tipo** (`estudiante` | `trabajador`): activar una desactiva las demás de su
  tipo (`activo` = en uso). Sin plantilla en uso, el frontend imprime el diseño por defecto.
- **El servidor no compone imágenes** (a diferencia de cumpleaños): el frontend dibuja el
  carnet en SVG con estas medidas. `CarnetService` manda en cada carnet (`emitir`, `mio`) la
  plantilla en uso de su tipo (`enUso()`) y `nombres` (el nombre en cada formato, con
  `CumpleanosImagen::formatearNombre`, que limpia los apellidos "." legados).
- `elementos`: `nombre`, `detalle`, `documento`, `vigencia` (textos con las reglas del nombre
  de cumpleaños + `visible`, `prefijo` de hasta 30 caracteres y `ajustar`, que lo parte en
  líneas en el frontend), `foto` (la de cumpleaños) y
  `barras` (centro, ancho/alto en fracción del ancho, color, `fondo` blanco, `visible`).
  `normalizar` reutiliza `CumpleanosImagen::normalizar` para textos y foto (test en
  `tests/Unit/Carnets`).
- Imagen: vertical con la proporción del carnet (54 × 85,6 mm, ±3 %), 400–3000 px, hasta 5 MB,
  en `carnets/plantillas/`.
- `GET /carnets/fuentes` y `/fuentes/{archivo}`: las tipografías de cumpleaños **sin opción**
  (el carnet digital de cualquiera las necesita); las de `/noticias/cumpleanos` piden la 69.
