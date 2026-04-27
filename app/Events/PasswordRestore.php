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
        public string $token
    ) {}
}