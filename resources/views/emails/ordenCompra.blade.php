<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de compra — {{ config('app.name') }}</title>
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

        .body a {
            color: #1a2744;
        }

        .bloque-titulo {
            margin: 24px 0 8px;
            font-size: 13px;
            color: #1a2744;
        }

        table.datos,
        table.productos {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        table.datos td,
        table.productos td,
        table.productos th {
            padding: 6px 8px;
            border-bottom: 1px solid #e5e2d9;
            text-align: left;
            vertical-align: top;
            word-break: break-word;
        }

        table.datos td.label {
            width: 40%;
            color: #8a8a80;
        }

        table.productos th {
            background: #f4f4f0;
            font-weight: 600;
        }

        table.productos .num {
            text-align: right;
            white-space: nowrap;
        }

        table.productos tr.total td {
            font-weight: 700;
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
                <p class="header-name">Orden de compra No. {{ $numeroOrden }}</p>
            </div>
            <div class="gold-line"></div>
            <div class="body">
                {{-- Saneado con HtmlCorreoSanitizer en SolicitudesServices. --}}
                {!! $mensajeHtml !!}

                @isset($bloques['datos_generales'])
                    <h3 class="bloque-titulo">Datos de la orden</h3>
                    <table class="datos" role="presentation">
                        @foreach ($bloques['datos_generales'] as $etiqueta => $valor)
                            <tr>
                                <td class="label">{{ $etiqueta }}</td>
                                <td>{{ $valor }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endisset

                @isset($bloques['justificacion'])
                    <h3 class="bloque-titulo">Justificación</h3>
                    <p>{!! nl2br(e($bloques['justificacion'])) !!}</p>
                @endisset

                @isset($bloques['productos'])
                    @php($pesos = fn ($n) => '$'.number_format($n, 0, ',', '.'))
                    <h3 class="bloque-titulo">Productos</h3>
                    <table class="productos">
                        <thead>
                            <tr>
                                <th>Cant.</th>
                                <th>Descripción</th>
                                <th class="num">Vr. unit.</th>
                                <th class="num">Vr. total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bloques['productos']['filas'] as $fila)
                                <tr>
                                    <td>{{ $fila['cantidad'] }}</td>
                                    <td>{{ $fila['producto'] }}</td>
                                    <td class="num">{{ $pesos($fila['precio']) }}</td>
                                    <td class="num">{{ $pesos($fila['total']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="num">Subtotal</td>
                                <td class="num">{{ $pesos($bloques['productos']['subtotal']) }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="num">IVA</td>
                                <td class="num">{{ $pesos($bloques['productos']['iva']) }}</td>
                            </tr>
                            <tr class="total">
                                <td colspan="3" class="num">Total</td>
                                <td class="num">{{ $pesos($bloques['productos']['total']) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                @endisset

                @if (count($correosContacto))
                    <p>Para cualquier comunicación sobre esta orden escriba a:
                        <strong>{{ implode(', ', $correosContacto) }}</strong>
                    </p>
                @else
                    <p>Cualquier inquietud, por favor comuníquese con el área de compras.</p>
                @endif
            </div>
        </div>
        <p class="footer">{{ config('app.name') }} — correo automático.</p>
    </div>
</body>

</html>
