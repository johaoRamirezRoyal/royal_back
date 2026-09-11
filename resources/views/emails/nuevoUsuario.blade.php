@component('mail::message')
    # ¡Bienvenido a OMNIA!

    Hola, **{{ $nombre }}**.

    Se ha creado una cuenta para ti en el sistema. Estos son tus datos de acceso:

    @component('mail::panel')
        Usuario: **{{ $usuario }}**
        Contraseña temporal: **{{ $pass }}**
    @endcomponent

    Por seguridad, **debes cambiar esta contraseña** la primera vez que ingreses.

    @component('mail::button', ['url' => $url, 'color' => 'primary'])
        Iniciar sesión
    @endcomponent

    Si no esperabas este correo, comunícate con el administrador del sistema.
@endcomponent
