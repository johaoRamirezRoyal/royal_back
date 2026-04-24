@component('mail::message')
    # Recuperación de contraseña

    Hola, **{{ $name }}**.

    Recibimos una solicitud para restablecer la contraseña de tu cuenta. Haz clic en el botón para continuar.

    @component('mail::button', ['url' => $url, 'color' => 'primary'])
        Restablecer contraseña
    @endcomponent

    Este enlace expirará en **{{ $expires }}** minutos. Si no solicitaste esto, ignora este correo.

    @slot('subcopy')
        Si tienes problemas con el botón, copia y pega este enlace en tu navegador:
        {{ $url }}
    @endslot
@endcomponent
