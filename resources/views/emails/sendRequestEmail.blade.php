@extends('emails.layouts.admisiones')

@section('title', 'Verificación de correo')

@section('content')
    <p class="greeting">Estimado acudiente,</p>
    <p class="intro">Hemos recibido tu solicitud de admisión. Para continuar con el proceso, por favor
        verifica tu correo electrónico con el siguiente código:</p>

    <div class="code-box">
        <p class="code-label">Código de verificación</p>
        <p class="code-number">{{ $verificationCode }}</p>
        <p class="code-expiry">Válido por 5 minutos</p>
    </div>

    <p class="note">Si no realizaste esta solicitud, puedes ignorar este mensaje. Nadie más puede usar
        este código sin acceso a tu correo.</p>
@endsection
