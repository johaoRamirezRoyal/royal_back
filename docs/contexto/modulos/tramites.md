# Trámites

Ruta `routes/api/tramites.php` → `TramitesController` → `TramitesServices` (`app/Services/tramites/`).

## Finalizar con documentos (`POST /api/tramites/{id}/finalizar`)

1. Valida `archivos[]` (hasta 10) y el `archivo` legado; mimes pdf/jpg/jpeg/png/doc/docx/xls/xlsx, 10 MB.
2. `cambiarEstado` actualiza el estado, sube cada archivo con `FileStorageService::uploadFile`
   (disco de uploads: Cloudinary en producción, `public` en local) y crea su `TramiteDocumento`.
   Los discos tienen `throw => false`: si la subida devuelve una ruta vacía se lanza excepción
   antes de guardar la fila.
3. `notificarCambioEstado` envía el correo **fuera de la transacción** con los archivos como
   adjuntos reales: `MailService::sendGeneric(..., $adjuntos)` → `GenericMail`, que resuelve cada
   ruta con `FileStorageService::rutaLocal()` (en Cloudinary la descarga). Un adjunto ilegible se
   omite y queda en el log (`Adjunto de correo no disponible`); el correo sale con los demás.
4. La respuesta trae `correo_enviado`. Si es `false` el trámite sigue finalizado.

Destinatarios: el solicitante (`usuario.correo`) más el grupo `GESTION_HUMANA` de
`correos_institucionales` (`Mails::GESTION_HUMANA->recipients()`). Además, el usuario que finaliza o
rechaza recibe un correo aparte, con el aviso "copia del correo enviado al solicitante" (no se envía si
ya está entre los destinatarios; su resultado no afecta `correo_enviado`). Con `MAIL_MAILER=log` (el default) nada sale: producción
debe tener `smtp`.

## Bitácora `tramite_correos`

Un registro por intento: `id_tramite`, `destinatarios`, `asunto`, `adjuntos` (nombres), `estado`
(`pendiente` → `enviado` | `error`), `error` (causa), `intentado_at`, `enviado_at`. `enviado` significa
aceptado por el servidor SMTP, no entregado. Para ver qué reenviar:
`select * from tramite_correos where estado = 'error'`. No hay reintento automático (el envío es
síncrono; pasarlo a cola exigiría un worker en producción).

## Pruebas

`tests/Feature/Tramites/GenericMailAdjuntosTest.php` cubre el adjuntado. El flujo completo no tiene
prueba: `tramite` y `usuario` son tablas legacy sin migración y la suite corre en sqlite en memoria.
