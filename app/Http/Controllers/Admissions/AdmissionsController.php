<?php

namespace App\Http\Controllers\Admissions;

use Illuminate\Support\Facades\Cache;
use App\Events\RequestEmailAdmission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\VerificationCodeRequest;
use Illuminate\Http\Request;

class AdmissionsController extends Controller {
    function requestVerification(Request $request) {
        $request->validate([
            'email' => 'required|email|min:10|max:140',
        ],
            [
                'email.required' => 'El correo es un campo obligatorio.',
                'email.email' => 'El correo no tiene un formato valido.',
                'email.min' => 'El correo debe tener al menos 10 caracteres',
                'email.max' => 'El correo no puede superar los 140 caracteres',
            ]);


        $codigo = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        event(new RequestEmailAdmission($request->email, $codigo));

        Cache::put("verificacion_{$request->email}", $codigo, now()->addMinutes(30));

        return $this->success('Codigo enviado!');
    }

    function validateVerificationCode(VerificationCodeRequest $request) {
        $codigoGuardado = Cache::get("verificacion_{$request->email}");

        if (!$codigoGuardado || $codigoGuardado !== $request->codigo) {
            return response()->json(['message' => 'Código inválido o expirado'], 422);
        }

        return $this->success('Verificacion exitosa');
    }

    function forgetVerificationCode(VerificationCodeRequest $request) {
        $codigoGuardado = Cache::get("verificacion_{$request->email}");

        if (!$codigoGuardado || $codigoGuardado !== $request->codigo) {
            return response()->json(['message' => 'Código inválido o expirado'], 422);
        }

        Cache::forget("verificacion_{$request->email}");
    }
}