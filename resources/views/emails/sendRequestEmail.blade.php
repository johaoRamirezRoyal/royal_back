<x-mail::message>
    # Código de Verificación - Admisiones

    Estimado **acudiente**

    Hemos recibido tu solicitud de admisión. Usa el siguiente código para verificar tu correo electrónico:

    <x-mail::panel>
        # {{ $verificationCode }}
    </x-mail::panel>

    Este código es **válido por 5 minutos**. Si no solicitaste este código, ignora este mensaje.

    <x-mail::button :url="$url">
        Verificar mi correo
    </x-mail::button>

    Gracias por tu interés en unirte a nuestra institución.<br>
    {{ config('app.name') }} — Departamento de Admisiones
</x-mail::message>
