<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de carta de recomendación — {{ config('app.name') }}</title>
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
            margin: 0 0 24px;
        }

        .doc-box {
            border: 1px solid #e5e2d9;
            border-radius: 8px;
            padding: 18px 20px;
            background: #f9f8f5;
            margin: 0 0 24px;
        }

        .doc-label {
            font-size: 12px;
            color: #888;
            margin: 0 0 6px;
        }

        .doc-name {
            font-size: 15px;
            font-weight: 600;
            color: #1a2744;
            margin: 0;
        }

        .doc-count {
            font-size: 13px;
            color: #666;
            margin: 8px 0 0;
        }

        .cta {
            text-align: center;
            margin: 0 0 24px;
        }

        .cta a {
            display: inline-block;
            background: #1a2744;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 8px;
        }

        .note {
            font-size: 13px;
            color: #888;
            line-height: 1.6;
            margin: 0;
        }

        .footer {
            border-top: 1px solid #eeebe3;
            padding: 20px 32px;
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
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">

            <div class="header">
                <p class="header-name">{{ config('app.name') }}</p>
                <p class="header-dept">Admisiones</p>
            </div>

            <div class="gold-line"></div>

            <div class="body">
                <p class="greeting">Hola, {{ $nombreInstitucion }}:</p>
                <p class="intro">
                    Desde Admisiones te recordamos diligenciar y enviar, desde el portal de instituciones,
                    el documento de recomendación de los egresados de tu institución que aspiran a ingresar
                    a nuestro colegio.
                </p>

                <div class="doc-box">
                    <p class="doc-label">Documento pendiente</p>
                    <p class="doc-name">{{ $documento }}</p>
                    @if ($documentosEnviados > 0)
                        <p class="doc-count">
                            Hasta hoy has enviado {{ $documentosEnviados }}
                            {{ $documentosEnviados === 1 ? 'documento' : 'documentos' }}. Si tienes más aspirantes,
                            envía una carta por cada uno.
                        </p>
                    @else
                        <p class="doc-count">Aún no hemos recibido ningún documento de tu institución.</p>
                    @endif
                </div>

                <div class="cta">
                    <a href="{{ $urlPortal }}">Ir al portal de instituciones</a>
                </div>

                <p class="note">
                    Ingresa con el NIT de tu institución. Si ya enviaste todos los documentos de tus aspirantes,
                    puedes ignorar este mensaje.
                </p>
            </div>

            <div class="footer">
                <p class="footer-name">{{ config('app.name') }}</p>
                <p class="footer-sub">Admisiones</p>
            </div>

        </div>
    </div>
</body>

</html>
