<?php

namespace App\Http\Controllers;

use App\Events\PasswordRestore;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'token' => \bcrypt($token),
                'created_at' => now(),
            ]
        );

        event(new PasswordRestore($user, $token));

        return $this->success('Te enviamos un enlace de recuperacion');
    }
}
