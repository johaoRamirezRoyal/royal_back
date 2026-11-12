# Documentos con pdfmake en navegador y servidor (2026-11-11 a 2026-11-12)

Cambio en `royal_back` y en el frontend (`S.A.M.I_frontend`). Las reglas vigentes están en
[`contexto/infraestructura.md`](contexto/infraestructura.md) («Documentos de src/documentos») y,
del lado del frontend, en `docs/contexto/documentos.md`. Este documento explica qué se cambió,
por qué y qué falta hacer en el deploy.

## Por qué

Los PDF se hacían con tres librerías: TCPDF en el backend (`app/Pdf`), pdfmake y pdf-lib en el
frontend. Cada documento armaba su propio encabezado y pie, varios usaban un logo fijo en vez
del de la marca, y la carta de entrega existía dos veces (TCPDF en Inventario y pdfmake en
Compras). Ninguno permitía que un colegio tuviera documentos propios.

## La solución

- **Una sola librería: pdfmake.** Cada documento se define una vez en TypeScript, en
  `src/documentos/` del frontend, y se genera igual en el navegador o en el servidor.
- **Componentes comunes** (`componentes.ts`): hoja con logo y número de página, título, fecha,
  párrafo, tabla y firmas.
- **Una carpeta por institución.** `instituciones/comun/` tiene lo que sirve a cualquier colegio
  con su marca; `instituciones/royal-school/` tiene lo propio de Royal School. La institución
  sale del dominio del correo (`registro.ts`). Un documento propio de un colegio no se genera
  para otro: falla con un error claro.
- **En el servidor**, `App\Services\Documentos\DocumentoPdfService` ejecuta con Node
  `resources/documentos/render.cjs`, que se compila en el frontend con `pnpm build:documentos`
  (un solo archivo con pdfmake y las fuentes). Recibe JSON por stdin y devuelve el PDF por stdout.

## Documentos migrados

| Documento | Carpeta | Dónde se genera | Reemplaza a |
|---|---|---|---|
| Carta de entrega (`cartaEntrega`) | `comun` | navegador (Compras) y servidor (`InventarioServices::generarCartaEntregaPdf`) | `CartaEntregaPdfService` y `CartaEntregaPDF.ts` |
| Paz y salvo y listados de préstamos (`pazYSalvo`, `listadoPrestamos`, `listadoPrestamosPaquetes`) | `comun` | servidor (`BibliotecaServices`; el paz y salvo también va adjunto en los recordatorios) | `PazYSalvoPdfService` |
| Carta de recomendación (`cartaRecomendacion`) | `royal-school` | servidor (`InstitucionController`) | `CartaRecomendacionPdfService` |
| Certificado de capacitación (`certificadoCapacitacion`) | `royal-school` | servidor (`CapacitacionesController::certificado`) | `CertificadoCapacitacionPdfService` |

Se compararon contra la versión TCPDF con los mismos datos. Mantienen la estructura y los textos;
cambia la letra (Roboto en vez de Helvetica/Times) y algún espaciado. Se corrigieron de paso: la
carta de entrega de Inventario usaba un logo fijo (ahora el de la marca), el paz y salvo decía
`,,` después del nombre y no llevaba tildes, y a un usuario de Hebreo Unión se le daba el
certificado de Royal School (ahora recibe un 422).

## Trampas encontradas

- **Tablas que se salen de la hoja.** Con anchos `auto` o `*`, pdfmake toma como mínimo la
  palabra más larga (un correo, un serial) y deja que la tabla se salga. `tabla()` usa anchos en
  porcentaje (`peso` por columna) y no parte filas entre páginas.
- **Node en Windows sin `SystemRoot`.** Lanzado desde el servidor web, Node abortaba al arrancar
  (`Assertion failed: ncrypto::CSPRNG`). `DocumentoPdfService::entornoNode()` pasa la variable.
- **`__dirname` en el bundle.** pdfmake lo usa, así que `render.cjs` sale en CommonJS y el script
  no usa `await` de nivel superior.
- **Vite copiaba `public/` del frontend** dentro de `resources/documentos`: la configuración tiene
  `publicDir: false`.
- **Imágenes que faltaban en Cloudinary.** El sello del paz y salvo (`royal-school/paz-salvo/sello`)
  no existía: ni la versión TCPDF lo mostraba. Se subió el 2026-11-12 como privado desde
  `storage/app/public/images/paz&salvo/sello.webp` y se invalidó la caché del CDN, que tenía
  guardado el 404. Siguen faltando `royal-school/instituciones/logo.png` (logo blanco de la carta
  de recomendación, que sale sin logo; se decidió no subirlo por ahora) y
  `royal-school/paz-salvo/logo.webp` (ya no lo usa nada).

## Cambios relacionados

- **Cartas de recomendación privadas.** Se subían públicas a
  `<colegio>/Instituciones/CartasRecomendacion/`. Ver
  [`ARCHIVOS_PRIVADOS_CLOUDINARY_2026_11_03.md`](ARCHIVOS_PRIVADOS_CLOUDINARY_2026_11_03.md),
  «Cartas de recomendación privadas».
- `CapacitacionesServices::datosCertificado` trae el `correo` del usuario, para resolver su marca.
- `DocumentoPdfService::dominioDelColegio()` traduce la connection activa a su dominio, para los
  documentos que no salen de un usuario con correo (la carta de recomendación).

## Deploy

1. **Instalar Node 20 o superior en el servidor de producción.** Sin Node fallan la carta de
   entrega de Inventario, el paz y salvo (y los recordatorios de préstamos), los listados de
   préstamos, la carta de recomendación y el certificado de capacitación. Si el usuario del
   servidor web no tiene `node` en el PATH, poner la ruta en `NODE_BINARY` (`.env`).
2. Subir backend y frontend juntos.
3. En el mismo momento, `php artisan cloudinary:privatizar` (por las cartas de recomendación que
   se hayan subido públicas antes del deploy; el 2026-11-12 no había ninguna).
4. Probar con sesión: carta de entrega (Compras y Inventario), paz y salvo de alguien sin
   préstamos pendientes (debe salir el sello), certificado de capacitación y el envío de una
   carta de recomendación desde el portal de un jardín.

## Pendientes

- **Faltan por migrar** 12 documentos: en el backend, Historial de checks y Permiso de alturas
  (Áreas), Respuesta de evaluación, Hoja de vida, Checklist de mantenimiento y Reporte operativo
  (Inventario); en el frontend, Acta, Orden de compra y Reserva (pdfmake suelto) e Inscripción,
  Historia clínica y Recibo de enfermería (pdf-lib).
- **Cada cambio de un documento que genera el servidor exige `pnpm build:documentos`** y
  commitear `render.cjs` en este repo. No hay test que avise si se olvida.
- Cada documento lanza un proceso de Node (~0,3 s); en los envíos masivos se suma por usuario.
- Hebreo Unión no tiene carta de recomendación ni certificado de capacitación propios.
