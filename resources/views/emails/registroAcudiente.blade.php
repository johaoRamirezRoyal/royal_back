@extends('emails.layouts.admisiones')

@section('title', 'Registro confirmado')

@section('content')
    <p class="greeting">Estimado(a) {{ $nombre }},</p>
    <p class="intro">Tu registro como acudiente quedó confirmado. Ya puedes continuar con el proceso de
        admisión del aspirante.</p>

    <div class="code-box">
        <p class="code-label">Correo de acceso</p>
        {{-- Si no cabe, que parta antes de la @ y no en mitad del dominio. --}}
        <p class="code-value">{!! str_replace('@', '<wbr>@', e($email)) !!}</p>
        <p class="code-expiry">Las próximas veces entra con este correo y la contraseña que creaste.</p>
    </div>

    @if ($url)
        <a href="{{ $url }}" class="btn">Continuar con el proceso de admisión</a>
    @endif

    <p class="note">Si no realizaste este registro, comunícate con el Departamento de Admisiones.</p>
@endsection
