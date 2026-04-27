<?php

namespace App\Http\Controllers\PasswordReset;

use App\Events\PasswordRestore;
use App\Http\Controllers\Controller;
use App\Models\PasswordResetTokens;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\JsonResponse;

use function Symfony\Component\Clock\now;

class PasswordResetController extends Controller
{
    public function createToken(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:usuarios,correo|min:10|max:140|ends_with:@royalschool.edu.co',
        ],
            [
                'email.required' => 'El correo es un campo obligatorio.',
                'email.email' => 'El correo no tiene un formato valido.',
                'email.min' => 'El correo debe tener al menos 10 caracteres',
                'email.max' => 'El correo no puede superar los 140 caracteres',
                'email.ends_with' => "Asegurate de digitar un correo institucional",
                'email.exists' => 'No existe una cuenta con este correo',
            ]);

        $user = Usuario::where('correo', $request->email)->first();
        $token = Str::random(64);

        PasswordResetTokens::updateOrInsert(
            ['email' => $user->correo],
            [
                'token' => \bcrypt($token),
                'used' => false,
                'created_at' => now(),
                'expires_at' => Carbon::now()->addMinutes(config('auth.passwords.users.expire')),  // <- expira en 60 minutos
            ]
        );

        event(new PasswordRestore($user, $token));

        return $this->success('Te enviamos un enlace de recuperacion');
    }

    public function validateToken(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string|size:64',
            'email' => 'required|email',
            'email.email' => 'El correo no tiene un formato valido.',
            'email.min' => 'El correo debe tener al menos 10 caracteres',
            'email.max' => 'El correo no puede superar los 140 caracteres',
            'email.exists' => 'No existe una cuenta con este correo',
        ]);

        $record = PasswordResetTokens::where('email', $request->email)
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Token inválido.'], 404);
        }

        if (Carbon::now()->isAfter($record->expires_at)) {
            return response()->json(['message' => 'Token expirado.'], 410);
        }

        if (! Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'Token inválido.'], 404);
        }

        return $this->success('Token válido.', 200);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $record = PasswordResetTokens::where('email', $request->email)
            ->where('used', false)
            ->latest()
            ->first();

        if (! $record || ! Hash::check($request->token, $record->token)) {
            return $this->error('Token inválido.', 404);
        }

        if (Carbon::now()->isAfter($record->expires_at)) {
            return $this->error('El enlace ha expirado.', 410);
        }

        if ($record->used) {
            return $this->error('El token ha sido usado anteriormente. Solicita un nuevo token', 410);
        }

        $record->update(['used' => true]);

        // Actualizar contraseña
        Usuario::where('correo', $request->email)
            ->update(['pass' => Hash::make($request->password)]);

        return $this->success('Contraseña actualizada.', 200);
    }
}
