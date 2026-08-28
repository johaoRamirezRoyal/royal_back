<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluación de desempeño — {{ config('app.name') }}</title>
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
                <p class="header-name">Evaluación de desempeño</p>
            </div>
            <div class="gold-line"></div>
            <div class="body">
                <p>Hola {{ $nombreEvaluado ?: 'usuario' }},</p>
                <p>
                    Se registró tu evaluación de desempeño <strong>{{ $tituloEvaluacion }}</strong>
                    @if($periodo)
                        correspondiente al periodo <strong>{{ $periodo->numero }}</strong>.
                    @else
                        .
                    @endif
                </p>
                <p>Adjunto encontrarás el PDF con el detalle completo de las preguntas y respuestas.</p>
                <p>Si tienes dudas sobre el resultado, comunícate con tu coordinador.</p>
            </div>
        </div>
        <p class="footer">{{ config('app.name') }} — correo automático, no responder.</p>
    </div>
</body>

</html>
