<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva de salón — {{ config('app.name') }}</title>
</head>

<body style="margin:0;padding:32px 16px;background:#f4f4f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
    <div style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #e5e2d9;border-radius:12px;overflow:hidden;">
        <div style="background:#1a2744;padding:24px 32px;text-align:center;">
            <p style="margin:0;font-size:15px;font-weight:600;color:#fff;">Nueva reserva de salón</p>
        </div>
        <div style="height:3px;background:#c9a84c;"></div>
        <div style="padding:28px 32px;color:#2c2c2c;font-size:14px;line-height:1.6;">
            @if($requiereAprobacion)
                <p style="background:#fff3cd;border:1px solid #ffeeba;color:#856404;padding:14px;border-radius:8px;">
                    <strong>⚠ Aviso importante:</strong> el salón <strong>{{ $salon }}</strong> requiere aprobación
                    previa antes de poder confirmar y validar la reserva.
                </p>
            @endif

            <p>El usuario <strong>{{ $usuario }}</strong> ha realizado una reserva:</p>
            <ul>
                <li><strong>Salón:</strong> {{ $salon }}</li>
                <li><strong>Portátiles:</strong> {{ $portatil }}</li>
                <li><strong>Sonido:</strong> {{ $sonido }}</li>
                <li><strong>Fecha:</strong> {{ implode(', ', $fechas) }}</li>
                <li><strong>Horas:</strong> {{ implode(', ', $horas) }}</li>
            </ul>
            @if($detalle)
                <p><strong>Detalle de la reserva:</strong> {{ $detalle }}</p>
            @endif
        </div>
        <p style="margin:0;padding:0 32px 20px;font-size:12px;color:#8a8a80;text-align:center;">
            {{ config('app.name') }} — correo automático, no responder.
        </p>
    </div>
</body>

</html>
