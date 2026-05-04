<?php

namespace App\Http\Controllers\Admissions;

use App\Events\RequestEmailAdmission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admisiones\RegistrarAspiranteRequest;
use App\Http\Requests\Admissions\VerificationCodeRequest;
use App\Services\Admisiones\AdmisionesServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdmissionsController extends Controller
{

    protected AdmisionesServices $admisiones_services;

    public function __construct(AdmisionesServices $admisionesServices)
    {
        $this->admisiones_services = $admisionesServices;
    }

    public function requestVerification(Request $request)
    {
        $request->validate([
            'email' => 'required|email|min:10|max:140',
        ],
            [
                'email.required' => 'El correo es un campo obligatorio.',
                'email.email' => 'El correo no tiene un formato valido.',
                'email.min' => 'El correo debe tener al menos 10 caracteres',
                'email.max' => 'El correo no puede superar los 140 caracteres',
            ]);

        $email = $request->email;

        $key = "send_{$email}";
        $attempts = Cache::increment($key);

        if ($attempts === 1) {
            Cache::put($key, 1, now()->addMinutes(5));
        }

        if ($attempts > 3) {
            return $this->error('Demasiadas solicitudes', 429);
        }

        $token = Cache::get("email_token_{$email}") ?? Str::random(64);

        $code = str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        Cache::put("verificacion_{$token}", [
            'code' => $code,
            'email' => $email,
        ], now()->addMinutes(5));

        Cache::put("email_token_{$email}", $token, now()->addMinutes(5));

        event(new RequestEmailAdmission($email, $token, $code));

        return $this->success('Codigo enviado!', [
            'token' => $token,
        ]);
    }

    public function validateVerificationCode(Request $request)
    {
        $token = $request->token;

        $data = Cache::get("verificacion_{$token}");

        if (! $data) {
            return $this->error('Sesion inválida o expirada', 400);
        }

        return $this->success('Verificacion exitosa');
    }

    public function forgetVerificationCode(VerificationCodeRequest $request)
    {
        $token = $request->token;

        $key = "verificacion_{$token}";
        $attemptsKey = "attempts_{$token}";

        $data = Cache::get($key);

        if (! $data) {
            return $this->error('Token invalido o expirado', 400);
        }

        $attempts = Cache::increment($attemptsKey);

        if ($attempts === 1) {
            Cache::put($attemptsKey, 1, now()->addMinutes(5));
        }

        if ($attempts > 5) {
            Cache::forget("verify_{$request->token}");
            Cache::forget($attemptsKey);

            return $this->error('Demasiados intentos', 429);
        }

        if ($data['code'] !== $request->code) {
            return $this->error('Código inválido', 400);
        }

        Cache::forget($attemptsKey);
        Cache::forget($key);
        Cache::forget("email_token_{$data['email']}");

        return $this->success('Correo valido!');
    }

    public function registrarAspirante(RegistrarAspiranteRequest $request)
    {
        $data = $request->validated();

        $resultado = $this->admisiones_services->registrarAspirante($data);

        return $this->apiResponse($resultado);
    }

    public function mostrarInformacionAspiranteId(Request $request)
    {
        $id = $request->input('id');

        if (!$id) {
            return response()->json([
                'error' => true,
                'message' => "Debe proporcionar un ID de aspirante válido",
                'data' => []
            ]);
        }

        $resultado = $this->admisiones_services->mostrarInformacionAspiranteId($id);

        return $this->apiResponse($resultado);
    }

    public function eliminarRegistroAspirante(int $id)
    {
        $resultado = $this->admisiones_services->eliminarRegistroAspirante($id);

        return $this->apiResponse($resultado);
    }
}
