<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $asunto }}</title>
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

        .alert {
            font-size: 14px;
            color: #8a5a00;
            line-height: 1.7;
            margin: 0 0 18px;
            padding: 14px 16px;
            border-left: 2px solid #c9a84c;
            background: #fdf8ee;
            border-radius: 0 6px 6px 0;
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
                <p class="greeting">Estimado padre de familia y estudiante:</p>

                @if ($limiteAlcanzado)
                    <p class="intro">Por medio de la presente se informa que el estudiante
                        <strong>{{ $nombreEstudiante }}</strong>, del grado {{ $grado }}, ha registrado
                        <strong>{{ $totalEnPeriodo }}</strong> llegadas tarde durante el presente trimestre.</p>

                    <p class="intro">De acuerdo con lo establecido en el Manual de Convivencia y Reglamento
                        Institucional, y conforme a las disposiciones socializadas previamente con los padres de
                        familia y estudiantes, el límite establecido es de {{ $cantidadLimite }} llegadas tarde por
                        trimestre.</p>

                    <p class="alert">En consecuencia, al haberse superado este límite, el estudiante no podrá
                        ingresar a la jornada escolar el día {{ $fecha }}, debiendo asumir las consecuencias
                        académicas derivadas de su inasistencia, conforme a lo establecido en el reglamento
                        institucional.</p>

                    <p class="intro">Recordamos la importancia de cumplir con los horarios establecidos por la
                        institución y reiteramos que esta medida corresponde a los compromisos y normas previamente
                        socializados y aceptados por la comunidad educativa.</p>

                    <p class="intro">Agradecemos su comprensión y compromiso con el cumplimiento de las normas
                        institucionales.</p>
                @elseif ($advertencia)
                    <p class="intro">Se informa que <strong>{{ $nombreEstudiante }}</strong>, del grado {{ $grado }},
                        registró una llegada tarde el día {{ $fecha }}.</p>

                    <p class="intro">Con este registro, el estudiante acumula <strong>{{ $totalEnPeriodo }}</strong>
                        llegadas tarde durante el Periodo {{ $periodo }}, alcanzando el límite establecido de
                        {{ $cantidadLimite }} llegadas tarde.</p>

                    <p class="alert">De acuerdo con lo establecido en el Reglamento Escolar, se informa que, en caso
                        de registrarse una nueva llegada tarde durante este mismo periodo, el estudiante no podrá
                        ingresar a la jornada escolar correspondiente y deberá asumir las consecuencias académicas
                        derivadas de su inasistencia.</p>

                    <p class="intro">Agradecemos su atención y el compromiso con el cumplimiento del horario
                        establecido.</p>
                @else
                    <p class="intro">Se informa que <strong>{{ $nombreEstudiante }}</strong>, del grado {{ $grado }},
                        registró una llegada tarde el día {{ $fecha }}.</p>

                    <p class="intro">Con este registro, acumula <strong>{{ $totalEnPeriodo }}</strong> llegada(s)
                        tarde(s) durante el Periodo {{ $periodo }}.</p>

                    <p class="intro">Agradecemos tomar las medidas necesarias para garantizar el cumplimiento del
                        horario escolar a las 7:00 a.m.</p>
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
