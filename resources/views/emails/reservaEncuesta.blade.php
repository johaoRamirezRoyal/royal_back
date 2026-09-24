<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reserva de salón — {{ config('app.name') }}</title>
    <style>
        body {
            margin: 0;
            padding: 32px 16px;
            background: #f4f4f0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        .wrap {
            max-width: 560px;
            margin: 0 auto;
        }

        .card {
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e5e2d9;
        }

        .header {
            background: #1a2744;
            padding: 28px 32px;
            text-align: center;
        }

        .header-name {
            font-size: 15px;
            font-weight: 600;
            color: #ffffff;
            margin: 0;
        }

        .gold-line {
            height: 3px;
            background: linear-gradient(90deg, #1a2744 0%, #c9a84c 50%, #1a2744 100%);
        }

        .body {
            padding: 28px 32px;
            color: #2c2c2c;
            font-size: 14px;
            line-height: 1.6;
        }

        table.info {
            width: 100%;
            border-collapse: collapse;
            margin: 16px 0;
        }

        table.info td {
            padding: 8px 0;
            border-bottom: 1px solid #eeebe3;
            vertical-align: top;
        }

        table.info td.label {
            width: 110px;
            color: #8a8a80;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .qr {
            display: block;
            width: 100%;
            max-width: 496px;
            margin: 20px auto 0;
            border-radius: 8px;
            border: 1px solid #e5e2d9;
        }

        .footer {
            padding: 16px 32px 24px;
            font-size: 12px;
            color: #8a8a80;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">
            <div class="header">
                <p class="header-name">Reserva de salón confirmada</p>
            </div>
            <div class="gold-line"></div>
            <div class="body">
                <p>Hola {{ $reserva['nombre'] ?: 'usuario' }},</p>
                <p>Tu reserva quedó registrada. Este salón tiene la encuesta <strong>{{ $reserva['encuesta'] }}</strong>
                    para que los asistentes la respondan.</p>

                <table class="info">
                    <tr><td class="label">Salón</td><td>{{ $reserva['salon'] }}</td></tr>
                    @if($reserva['titulo'])
                        <tr><td class="label">Título</td><td>{{ $reserva['titulo'] }}</td></tr>
                    @endif
                    <tr><td class="label">Fecha</td><td>{{ implode(', ', $reserva['fechas']) }}</td></tr>
                    <tr><td class="label">Horas</td><td>{!! implode('<br>', array_map('e', $reserva['horas'])) !!}</td></tr>
                    @if($reserva['detalle'])
                        <tr><td class="label">Detalle</td><td>{{ $reserva['detalle'] }}</td></tr>
                    @endif
                </table>

                <p>Comparte la siguiente imagen con los asistentes (también va adjunta a este correo):
                    al escanear el QR podrán responder la encuesta.</p>
                <img class="qr" src="{{ $message->embedData($jpg, 'qr-encuesta.jpg', 'image/jpeg') }}" alt="Información de la reserva y código QR de la encuesta">
            </div>
        </div>
        <p class="footer">{{ config('app.name') }} — correo automático, no responder.</p>
    </div>
</body>

</html>
