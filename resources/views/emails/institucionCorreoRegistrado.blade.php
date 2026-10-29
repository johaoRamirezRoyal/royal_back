@extends('emails.layouts.admisiones')

@section('title', 'Correo registrado')

@section('dept', 'Instituciones asociadas — Admisiones')

@section('content')
    <p class="greeting">Estimados,</p>
    <p class="intro">El correo de <strong>{{ $nombreInstitucion }}</strong> quedó registrado en el portal de
        instituciones asociadas. Desde ahora, las solicitudes sobre las cartas de recomendación llegarán a esta
        dirección.</p>

    <div class="code-box">
        <p class="code-label">Correo registrado</p>
        {{-- Si no cabe, que parta antes de la @ y no en mitad del dominio. --}}
        <p class="code-value">{!! str_replace('@', '<wbr>@', e($email)) !!}</p>
        <p class="code-expiry">Registrado el {{ $fecha }}</p>
    </div>

    <p class="note">Si no reconocen este registro, comuníquense con el Departamento de Admisiones.</p>
@endsection
