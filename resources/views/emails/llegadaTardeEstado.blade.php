<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $tipo === 'justificada' ? 'Llegada tarde justificada' : 'Revocación de llegada tarde' }}</title>
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
            padding: 32px 32px 24px;
            text-align: center;
        }

        .header-name {
            font-size: 13px;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            margin: 0;
        }

        .header-dept {
            font-size: 11px;
            color: rgba(255, 255, 255, 0.45);
            letter-spacing: 0.1em;
            text-transform: uppercase;
            margin: 5px 0 0;
        }

        .gold-line {
            height: 3px;
            background: linear-gradient(90deg, #1a2744 0%, #c9a84c 50%, #1a2744 100%);
        }

        .body {
            padding: 32px 32px 24px;
        }

        .greeting {
            font-size: 15px;
            color: #666;
            margin: 0 0 12px;
        }

        .intro {
            font-size: 15px;
            color: #1a1a1a;
            line-height: 1.7;
            margin: 0 0 18px;
        }

        .signature {
            font-size: 14px;
            color: #1a1a1a;
            line-height: 1.6;
            margin: 24px 0 0;
        }

        .footer {
            border-top: 1px solid #eeebe3;
            padding: 20px 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .footer-name {
            font-size: 13px;
            font-weight: 600;
            color: #333;
            margin: 0;
        }

        .footer-sub {
            font-size: 12px;
            color: #aaa;
            margin: 3px 0 0;
        }

        .footer-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #c9a84c;
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">

            <div class="header">
                <p class="header-name">Colegio Real Royal School</p>
                <p class="header-dept">Vicerrectoría</p>
            </div>

            <div class="gold-line"></div>

            <div class="body">
                <p class="greeting">Estimado padre de familia:</p>

                @if ($tipo === 'justificada')
                    <p class="intro">Le informamos que la llegada tarde registrada el día <strong>{{ $fecha }}</strong>
                        del estudiante <strong>{{ $nombreEstudiante }}</strong>, del grado {{ $grado }}, ha quedado
                        <strong>justificada</strong> y no será tenida en cuenta dentro del conteo de llegadas tarde
                        del período.</p>

                    @if ($observacion)
                        <p class="intro">Motivo registrado: {{ $observacion }}</p>
                    @endif
                @else
                    <p class="intro">Le informamos que la llegada tarde registrada el día <strong>{{ $fecha }}</strong>
                        del estudiante <strong>{{ $nombreEstudiante }}</strong>, del grado {{ $grado }}, ha sido
                        <strong>revocada</strong> y no será tenida en cuenta dentro del conteo de llegadas tarde del
                        período.</p>

                    @if ($observacion)
                        <p class="intro">Motivo de la revocación: {{ $observacion }}</p>
                    @endif
                @endif

                <p class="signature">Atentamente,<br>ALFREDO CAÑAS H.<br>Vicerrector</p>
            </div>

            <div class="footer">
                <div>
                    <p class="footer-name">Colegio Real Royal School</p>
                    <p class="footer-sub">Vicerrectoría</p>
                </div>
                <div class="footer-dot"></div>
            </div>

        </div>
    </div>
</body>

</html>
