<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
</head>

<body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Arial, sans-serif;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:20px;">
        <tr>
            <td align="center">
                <table width="600" style="background:#ffffff; border-radius:10px; overflow:hidden;">

                    <!-- HEADER -->
                    <tr>
                        <td style="background:#2563eb; color:white; padding:20px; text-align:center;">
                            <p style="margin:0 0 4px; font-size:12px; letter-spacing:0.05em; text-transform:uppercase; opacity:0.85;">
                                {{ config('app.name') }}
                            </p>
                            <h2 style="margin:0;">{{ $titulo }}</h2>
                        </td>
                    </tr>

                    <!-- BODY -->
                    @if($mensaje)
                    <tr>
                        <td style="padding:30px; color:#333;">
                            <div style="font-size:15px; line-height:1.6;">
                                {!! \App\Support\NoticiaTextoFormatter::aHtml($mensaje) !!}
                            </div>
                        </td>
                    </tr>
                    @endif

                    <!-- LINK -->
                    @if($url)
                    <tr>
                        <td style="padding:0 30px 30px; text-align:center;">
                            <a href="{{ $url }}"
                                style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none; padding:12px 24px; border-radius:6px; font-size:14px;">
                                Ver más
                            </a>
                        </td>
                    </tr>
                    @endif

                    <!-- FOOTER -->
                    <tr>
                        <td style="background:#f1f5f9; padding:15px; text-align:center; font-size:12px; color:#666;">
                            {{ config('app.name') }} · Este es un mensaje automático del sistema.<br>
                            Por favor no responder a este correo.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>

</html>
