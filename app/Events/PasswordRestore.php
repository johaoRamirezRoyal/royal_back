<?php

namespace App\Events;

use App\Models\Usuarios\Usuario;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PasswordRestore
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Usuario $user,
        public string $token,
        // Slug del portal para padres: el enlace del correo lo lleva para volver a esa base.
        public ?string $colegio = null,
    ) {}
}