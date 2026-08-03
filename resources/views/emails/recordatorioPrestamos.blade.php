<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recordatorio de préstamos — {{ config('app.name') }}</title>
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

        .code-box {
            border: 1px solid #e5e2d9;
            border-radius: 8px;
            padding: 24px;
            text-align: center;
            background: #f9f8f5;
            margin: 0 0 24px;
        }

        .code-label {
            font-size: 11px;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #999;
            margin: 0 0 12px;
        }

        .code-number {
            font-family: 'Courier New', monospace;
            font-size: 38px;
            font-weight: 600;
            color: #1a2744;
            margin: 0;
        }

        .code-expiry {
            font-size: 12px;
            color: #888;
            margin: 10px 0 0;
        }

        .note {
            font-size: 13px;
            color: #999;
            line-height: 1.6;
            margin: 0;
            padding: 14px 16px;
            border-left: 2px solid #e0ddd5;
            background: #f9f8f5;
            border-radius: 0 6px 6px 0;
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
                <p class="header-name">{{ config('app.name') }}</p>
                <p class="header-dept">Biblioteca</p>
            </div>

            <div class="gold-line"></div>

            <div class="body">
                <p class="greeting">Hola {{ $nombre }},</p>
                @if ($cantidadLibros > 0 || $cantidadPaquetes > 0)
                    <p class="intro">Este es un recordatorio de tus préstamos de biblioteca pendientes por devolver.
                        Adjunto encontrarás el PDF con el detalle de cada préstamo y su fecha límite de devolución.</p>

                    @if ($cantidadLibros > 0)
                        <div class="code-box">
                            <p class="code-label">Libros pendientes por devolver</p>
                            <p class="code-number">{{ $cantidadLibros }}</p>
                        </div>
                    @endif

                    @if ($cantidadPaquetes > 0)
                        <div class="code-box">
                            <p class="code-label">Paquetes pendientes por devolver</p>
                            <p class="code-number">{{ $cantidadPaquetes }}</p>
                        </div>
                    @endif

                    <p class="note">Por favor acércate a la biblioteca para gestionar la devolución antes de la fecha
                        límite indicada en el documento adjunto.</p>
                @else
                    <p class="intro">No tienes préstamos de biblioteca pendientes por devolver. Adjunto encontrarás tu
                        paz y salvo.</p>
                @endif
            </div>

            <div class="footer">
                <div>
                    <p class="footer-name">{{ config('app.name') }}</p>
                    <p class="footer-sub">Biblioteca</p>
                </div>
                <div class="footer-dot"></div>
            </div>

        </div>
    </div>
</body>

</html>
