<?php

namespace App\Http\Controllers\PasswordReset;

use App\Events\PasswordRestore;
use App\Http\Controllers\Controller;
use App\Models\PasswordResetTokens;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

use function Symfony\Component\Clock\now;

class PasswordResetController extends Controller
{
    public function createToken(Request $request)
    {
        $request->validate([
            'email' => 'required|string|exists:usuarios,correo|min:10|max:140',
        ],
            [
                'email.required' => 'El correo es un campo obligatorio.',
                'email.email' => 'El correo no tiene un formato valido.',
                'email.min' => 'El correo debe tener al menos 10 caracteres',
                'email.max' => 'El correo no puede superar los 140 caracteres',
                'email.exists' => 'No existe una cuenta con este correo',
            ]);

        $user = Usuario::where('correo', $request->email)->first();
        $token = Str::random(64);

        PasswordResetTokens::updateOrInsert(
            ['email' => $user->correo],
            [
                'token' => \bcrypt($token),
                'created_at' => now(),
                'expires_at' => Carbon::now()->addMinutes(config('auth.passwords.users.expire')),  // <- expira en 60 minutos
            ]
        );

        event(new PasswordRestore($user, $token));

        return $this->success('Te enviamos un enlace de recuperacion');
    }
}
