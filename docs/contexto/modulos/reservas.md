# Reservas de salones

- **Estados (`reservas.confirmado`)**: `1` finalizada, `2` reservada (default), `3` pendiente de
  autorización, `4` rechazada (además `activo = 0` y `fecha_cancelado`). Constantes
  `Reservas::CONFIRMADO_*`.
- **Salones con autorización**: `salones.id_responsable` (migración
  `2026_11_12_100000_add_id_responsable_to_salones_table`). Si tiene valor, `crearReserva` guarda
  en `3` (la franja queda ocupada) y **no presta portátiles** hasta aprobar.
  `enviarCorreoAvisoReserva` además le envía al responsable un `sendGeneric` con enlace a
  `/reservas/autorizaciones` del frontend.
- `GET /api/reservas/pendientes` y `PUT /api/reservas/decision` (`ids[]`, `aprobar`, `motivo?`):
  sin opción de `cron_opciones`, la autorización es por dato (`ReservasServices::puedeDecidir`:
  responsable del salón de todas las franjas, o perfil 1). `decidirReservas` es todo o nada,
  presta los portátiles al aprobar y avisa por correo al solicitante fuera de la transacción.
- Editar una reserva pendiente no la vuelve a someter a autorización (pendiente si hace falta).
