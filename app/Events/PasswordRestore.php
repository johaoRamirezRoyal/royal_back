<?php

namespace App\Events;

use App\Models\PasswordResetTokens;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PasswordRestore
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PasswordResetTokens $pass, public string $token) {}
}
