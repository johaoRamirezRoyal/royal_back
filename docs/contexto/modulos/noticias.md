# Noticias (`NoticiasController` / `NoticiasService`, opción 69)

Hay dos tipos de contenido:
- El **mensaje general**: una sola fila siempre vigente, en `asistencia_mensaje_general`.
- Las **noticias programadas**: en `asistencia_mensaje`, por `fecha`.

`noticias:enviar-diarias` corre **cada hora** (`withoutOverlapping`) y envía lo que esté
activo y no se haya enviado. Antes corría a diario y se perdían las noticias creadas
después de medianoche. Repetirlo es seguro gracias a `enviado_at`/`ultimo_envio_fecha`.

- Las noticias `normal` no se envían por correo: solo se muestran en el Home durante
  `fecha` + `dias_visualizacion`.
- Un cumpleaños se envía **una vez, en su fecha exacta**, y en el Home se muestra **solo
  ese día**: sin `dias_visualizacion` propio, su ventana es 0 días
  (`programadasVisiblesDeTipo`); las noticias usan 10. Antes tomaba los 10 días y seguía
  en el Home después del cumpleaños. `crearProgramado` siempre crea noticias `normal` y
  `actualizarProgramado` no cambia el tipo.
- Interruptor de correos (`configuracion_noticias.enviar_correos_programadas`,
  `GET|PUT /api/noticias/configuracion`): apagado, no envía **ni marca `enviado_at`**. No
  afecta al mensaje general.
- **`asistencia_mensaje` es MyISAM**: las transacciones no deshacen nada, y unas filas de
  prueba quedaron guardadas. Para probar la lógica, usa instancias en memoria; si creas
  filas, ponles un título reconocible y bórralas en un `finally`.
- Para probar el envío: `php artisan noticias:enviar-diarias`. Con `MAIL_MAILER=log` no
  sale ningún correo real.

## Rate-limit y listas de distribución

- **Incidente del 2026-09-16**: el mensaje general se enviaba a cada usuario por separado
  (~2.149 correos). Se agotó el límite por hora del hosting (1.967 fallos con
  `452-4.5.3 too many recipients`) y el comando siguió reintentando durante 1 h 22 min.
- `MailService` detecta esa firma exacta y lanza `MailRateLimitException`;
  `NoticiasService` corta el lote al recibirla.
- **La solución de fondo son las listas de distribución**: cada nivel se asocia a una lista
  real (`nivel.grupo_correo_distribucion` → grupos `NOTICIAS_*` en
  `correos_institucionales`) y se envía **un** correo a la lista
  (`resolverDestinatariosDistribucion`). Un nivel sin lista vuelve al envío individual,
  con el corte como red de seguridad.
- El nivel "Secundaria" de la BD es en realidad 10°–11° ("Bachillerato"); por eso apunta a
  `midhigh@`.
- `/api/noticias/correos-distribucion*` (opción 128) solo toca los grupos `NOTICIAS_*`.

## Cumpleaños automáticos (`CumpleanosController`, `/api/noticias/cumpleanos/*`)

`CumpleanosService` + `CumpleanosImagen`, con las tablas `cumpleanos_plantillas`,
`cumpleanos_generados` y `cumpleanos_excluidos`, y las columnas `cumpleanos_*` en
`configuracion_noticias`.

- **Plantillas por género** (masculino y femenino):
  - La imagen base mide entre 500 y 3000 px por lado; ancho, alto y transparencia se
    leen del archivo, no del cliente.
  - Los `elementos` (JSON) van en fracciones de la imagen y `normalizar` acota lo que
    manda el cliente.
- **Composición con GD**:
  - El nombre va con FreeType (`resources/fonts/cumpleanos/`, px = pt·96/72).
  - La foto se recorta en círculo o en rectángulo redondeado y puede ir encima o
    **debajo** de la plantilla (un PNG con hueco transparente).
  - La vista previa (`POST .../vista-previa`) es exactamente la imagen que se publica
    (~0,3 s).
- **Personal**: usuarios activos fuera de `PERFILES_EXCLUIDOS_ASISTENCIA`.
  - Fecha y género salen de `info_adicional_usuarios`.
  - La foto sale de `foto_perfil` o, si no hay, de `foto_carnet`, y solo cuenta si el
    archivo existe. En local casi ninguna existe.
- **Generación** (`generarPendientes`, antes de cada envío horario):
  - Crea una fila `asistencia_mensaje` de tipo cumpleaños para quien tiene datos
    completos y le faltan `cumpleanos_dias_anticipacion` días o menos.
  - Es única por persona y año (`cumpleanos_generados`).
  - **Sin plantilla de su género, no genera**; nunca usa la del otro género.
  - Un error queda como `estado=error` y se reintenta a la hora siguiente. `regenerar`
    vuelve a componer una felicitación que aún no se envió.
- La repetición anual manual (`repetir_anual`) se retiró el 2026-10-16, porque con la
  generación automática habría felicitaciones dobles. La columna sigue existiendo,
  siempre en 0.

## Despliegue

**Migra antes de subir el código**, en cada base de colegio que use Noticias:
`create_configuracion_noticias_table`, `add_repetir_anual_to_asistencia_mensaje_table`,
`create_cumpleanos_plantillas_tables` y `disable_repetir_anual_cumpleanos` (todas del
2026-10-16). Sin ellas fallan el envío y `para-mostrar`, y con ellos la sección de
noticias del Home para todos. Si las aplicas a mano, regístralas en `migrations`.
