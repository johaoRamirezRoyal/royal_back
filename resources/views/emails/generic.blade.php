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
                            <h2 style="margin:0;">{{ $titulo }}</h2>
                        </td>
                    </tr>

                    <!-- BODY -->
                    <tr>
                        <td style="padding:30px; color:#333;">
                            <p style="font-size:15px; line-height:1.6;">
                                {!! nl2br(e($contenido)) !!}
                            </p>
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style="background:#f1f5f9; padding:15px; text-align:center; font-size:12px; color:#666;">
                            Este es un mensaje automático del sistema.<br>
                            Por favor no responder a este correo.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>

</html>