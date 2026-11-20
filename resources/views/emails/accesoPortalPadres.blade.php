{{-- Acceso al portal para padres (AccesoPortalPadresMail). Tablas y estilos en línea para que se vea
     igual en Gmail, Outlook y el celular. La marca del colegio pone logo, color e imagen. --}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tu acceso al portal para padres de {{ $colegio }}</title>
</head>

<body style="margin:0; padding:0; background-color:#e9f5ee; font-family:'Segoe UI', Arial, sans-serif; color:#0f172a;">
    <div style="display:none; max-height:0; overflow:hidden;">Crea tu contraseña y consulta la semana de tus hijos en el colegio.</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#e9f5ee;">
        <tr>
            <td align="center" style="padding:28px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px; background-color:#ffffff; border-radius:14px; overflow:hidden; border:1px solid #d9e7df;">

                    {{-- Encabezado: logo y nombre del colegio --}}
                    <tr>
                        <td style="background-color:{{ $color }}; padding:22px 28px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    @if($logo)
                                        <td style="padding-right:12px;">
                                            <img src="{{ $logo }}" alt="" width="44" height="44" style="display:block; width:44px; height:44px; border-radius:10px; background:#ffffff; object-fit:contain;">
                                        </td>
                                    @endif
                                    <td style="color:#ffffff;">
                                        <div style="font-size:17px; font-weight:700;">{{ $colegio }}</div>
                                        <div style="font-size:13px; opacity:.85;">Portal para padres</div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if($imagen)
                        <tr>
                            <td style="line-height:0;">
                                <img src="{{ $imagen }}" alt="" width="600" style="display:block; width:100%; max-width:600px; height:160px; object-fit:cover;">
                            </td>
                        </tr>
                    @endif

                    {{-- Cuerpo --}}
                    <tr>
                        <td style="padding:32px 28px 8px;">
                            <h1 style="margin:0 0 12px; font-size:24px; line-height:1.25; color:#0f172a;">Hola, {{ $nombre }}</h1>
                            <p style="margin:0 0 20px; font-size:16px; line-height:1.6; color:#334155;">
                                {{ $colegio }} te dio acceso al portal para padres. Allí consultas desde el celular o el computador la semana de tus hijos en el colegio.
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px; background-color:#f4faf6; border-radius:10px;">
                                <tr>
                                    <td style="padding:16px 18px; font-size:15px; line-height:1.7; color:#334155;">
                                        <strong style="color:#0f172a;">Qué vas a encontrar</strong><br>
                                        &bull; Llegadas al colegio, día por día.<br>
                                        &bull; Novedades de asistencia a clases.<br>
                                        &bull; Fotos de las actividades del curso.
                                    </td>
                                </tr>
                            </table>

                            @php
                                // documento: entra con su documento y debe cambiarlo · sin_contrasena: debe crearla · propia: ya tiene una
                                $textoBoton = match ($contrasena) {
                                    'documento' => 'Cambiar mi contraseña',
                                    'propia' => 'Crear una nueva contraseña',
                                    default => 'Crear mi contraseña',
                                };
                            @endphp

                            {{-- Datos para entrar --}}
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; border:1px solid #d9e7df; border-radius:10px;">
                                <tr>
                                    <td style="padding:16px 18px; font-size:14px; line-height:1.7; color:#334155;">
                                        <strong style="color:#0f172a;">Tus datos para entrar</strong><br>
                                        Portal: <a href="{{ $urlPortal }}" target="_blank" style="color:{{ $color }}; font-weight:600; word-break:break-all;">{{ $urlPortal }}</a><br>
                                        Usuario: <span style="font-weight:600; color:#0f172a; word-break:break-all;">{{ $correo }}</span>
                                        @if($contrasena === 'documento')
                                            <br>Contraseña inicial: <span style="font-weight:600; color:#0f172a;">tu número de documento</span>, sin puntos ni espacios
                                        @endif
                                    </td>
                                </tr>
                            </table>

                            @if($contrasena === 'documento')
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 20px; background-color:#fff7e6; border-radius:10px;">
                                    <tr>
                                        <td style="padding:14px 18px; font-size:15px; line-height:1.6; color:#7a4b00;">
                                            <strong>Cambia esa contraseña por una tuya.</strong> Tu número de documento lo conocen otras personas: por tu seguridad y la de tus hijos, cámbiala con el botón de abajo.
                                        </td>
                                    </tr>
                                </table>
                            @elseif($contrasena === 'propia')
                                <p style="margin:0 0 18px; font-size:16px; line-height:1.6; color:#334155;">
                                    Ya tienes una contraseña: entra con ella. Si no la recuerdas, crea una nueva con el botón.
                                </p>
                            @else
                                <p style="margin:0 0 18px; font-size:16px; line-height:1.6; color:#334155;">
                                    Para empezar, crea tu contraseña con el botón. Solo tú la vas a conocer.
                                </p>
                            @endif

                            {{-- Botón --}}
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 12px;">
                                <tr>
                                    <td style="background-color:{{ $color }}; border-radius:8px;">
                                        <a href="{{ $urlCrear }}" target="_blank" style="display:inline-block; padding:14px 28px; color:#ffffff; text-decoration:none; font-weight:700; font-size:16px;">{{ $textoBoton }}</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0 0 28px; font-size:13px; color:#64748b;">
                                El enlace vence en {{ $diasVigencia }} días. Si vence, pide en el colegio que te lo envíen de nuevo.
                            </p>

                            <p style="margin:0 0 28px; font-size:13px; line-height:1.6; color:#64748b;">
                                Si el botón no funciona, copia este enlace en tu navegador:<br>
                                <span style="word-break:break-all; color:#475569;">{{ $urlCrear }}</span>
                            </p>
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td style="background-color:#f4faf6; padding:16px 28px; font-size:12px; line-height:1.6; color:#64748b; text-align:center;">
                            {{ $colegio }} · Portal para padres<br>
                            Mensaje automático: no respondas a este correo. Si no esperabas este acceso, ignóralo.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>
