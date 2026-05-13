<?php

namespace App\Http\Controllers\Admissions;

use App\Events\RequestEmailAdmission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admisiones\AdmisionesDocumentoRequest;
use App\Http\Requests\Admisiones\RegistrarAspiranteRequest;
use App\Http\Requests\Admisiones\RegistrarFamiliarRequest;
use App\Http\Requests\Admisiones\RegistrarInformacionMedicaRequest;
use App\Http\Requests\Admisiones\RegistrarInscripcionRequest;
use App\Http\Requests\Admissions\FamilyRegisterRequest;
use App\Http\Requests\Admissions\VerificationCodeRequest;
use App\Http\Traits\HasAuthCookie;
use App\Services\Admisiones\AdmisionesServices;
use App\Services\AnioEscolar\AnioEscolarServices;
use App\Services\Auth\AuthServices;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\JwtService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AdmissionsController extends Controller
{
    use HasAuthCookie;

    protected AdmisionesServices $admisiones_services;

    protected CloudinaryService $cloudinary_service;

    protected AuthServices $service_auth;

    protected AnioEscolarServices $anio_escolar_services;

    public function __construct(AdmisionesServices $admisionesServices, CloudinaryService $cloudinaryService, AuthServices $service_auth, AnioEscolarServices $anio_escolar_services, private JwtService $jwt, private UsuariosServices $usuarios_services)
    {
        $this->admisiones_services = $admisionesServices;
        $this->cloudinary_service = $cloudinaryService;
        $this->service_auth = $service_auth;
        $this->anio_escolar_services = $anio_escolar_services;
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
        $request->validate([
            'token' => 'required|string|size:64',
        ],
            ['token.required' => 'El token es obligatorio.',
                'token.string' => 'El token debe ser una cadena válida.',
                'token.size' => 'El token no es válido.']);

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

        $email = $data['email'];

        Cache::forget($attemptsKey);
        Cache::forget($key);
        Cache::forget("email_token_{$email}");

        $userExists = $this->usuarios_services->infoUserWhitEmail($email);

        if ($userExists) {
            return $this->success('Cuenta existente. Redirigiendo...', [
                'cookie_token' => true,
            ])
                ->withCookie(
                    $this->makeCookie($this->jwt->generateAdmissionsToken($userExists), 'admissions_token')
                );
        }

        $registerToken = Str::random(64);

        Cache::put("register_session_{$registerToken}", [
            'email' => $email,
        ], now()->addMinute(15));

        return $this->success('Correo valido!', [
            'register_token' => $registerToken,
        ]);
    }

    public function familyRegister(FamilyRegisterRequest $request)
    {
        $token = $request->token;
        $registerKey = "register_session_{$token}";

        $validation = Cache::get($registerKey);
        $data = $request->except('token');

        if (! $validation) {
            return $this->error('Tu sesión de registro ha expirado o no es válida. Inicia el proceso nuevamente.', 401);
        }

        if ($validation['email'] !== $request->correo) {
            return $this->error('El correo no coincide con la sesión de registro.', 403);
        }

        try {
            $this->service_auth->registrarUsuario($data);

            Cache::forget($registerKey);

            return $this->success('Registro completado exitosamente.', 201);
        } catch (\Exception $e) {
            return $this->error('Ocurrió un error al procesar el registro. Intenta de nuevo.', 500);
        }
    }

    public function registrarInscripcion(RegistrarInscripcionRequest $request)
    {
        $data = $request->validated();

        $data['anio_academico'] = $this->anio_escolar_services->obtenerUltimoAnioEscolar()['data']->id;

        $resultado = $this->admisiones_services->registrarInscripcion($data);

        return $this->apiResponse($resultado);
    }

    public function mostrarTodasIncripcionesAcudiente(Request $request){
        $id_acudiente = $request->input("id_acudiente");

        $response = $this->admisiones_services->mostrarTodasIncripcionesAcudiente($id_acudiente);

        return $this->apiResponse($response);
    }

    public function obtenerInformacionCompletaDeInscripcionMedianteCodigo(Request $request)
    {
        $codigo = $request->input('codigo');

        if (! $codigo) {
            return response()->json([
                'error' => true,
                'message' => 'Debe proporcionar un código de inscripción válido.',
                'data' => [],
            ]);
        }

        $resultado = $this->admisiones_services->obtenerInformacionCompletaDeInscripcionMedianteCodigo($codigo);

        return $this->apiResponse($resultado);
    }

    public function registrarAspirante(RegistrarAspiranteRequest $request)
    {
        $data = $request->validated();

        $data['anio_academico'] = $this->anio_escolar_services->obtenerUltimoAnioEscolar()['data']->id;

        $resultado = $this->admisiones_services->registrarAspirante($data);

        return $this->apiResponse($resultado);
    }

    public function mostrarInformacionAspiranteId(Request $request)
    {
        $id = $request->input('id');

        if (! $id) {
            return response()->json([
                'error' => true,
                'message' => 'Debe proporcionar un ID de aspirante válido',
                'data' => [],
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

    public function testArchivoGuardar(Request $request)
    {
        $file = $request->file('archivo');

        if (! $file) {
            return response()->json([
                'error' => true,
                'message' => 'No se ha proporcionado ningún archivo.',
                'data' => [],
            ]);
        }
        $resultado = $this->cloudinary_service->uploadFile($file, 'Admisiones/Test');

        return $this->apiResponse($resultado);
    }

    public function testArchivoEliminar(Request $request)
    {
        $publicId = $request->input('public_id');

        if (! $publicId) {
            return response()->json([
                'error' => true,
                'message' => 'No se ha proporcionado ningún public_id.',
                'data' => [],
            ]);
        }

        $resultado = $this->cloudinary_service->deleteFile($publicId);

        return $this->apiResponse($resultado);
    }

    public function actualizarRegistroAspirante(RegistrarAspiranteRequest $request)
    {
        $id = $request->input('id');
        if (! $id) {
            return response()->json([
                'error' => true,
                'message' => 'Debe proporcionar un ID de aspirante válido',
                'data' => [],
            ]);
        }

        $data = $request->validated();
        $resultado = $this->admisiones_services->actualizarRegistroAspirante($id, $data);

        return $this->apiResponse($resultado);
    }

    public function correoInformativoSolicitudInicial(Request $request)
    {
        $email = $request->input('email');
        $id_solicitud = $request->input('id_solicitud');

        if (! $email || ! $id_solicitud) {
            return response()->json([
                'error' => true,
                'message' => 'Debe proporcionar un correo electrónico y un ID de solicitud.',
                'data' => [],
            ]);
        }

        $this->admisiones_services->correoInformativoSolicitudInicial($id_solicitud, $email);

        return response()->json([
            'error' => false,
            'message' => 'Correo informativo enviado correctamente.',
            'data' => [],
        ]);
    }

    public function agregarFamiliarAspirante(RegistrarFamiliarRequest $request)
    {
        $data = $request->validated();
        $id_aspirante = $request->input('id_aspirante');

        $response = $this->admisiones_services->agregarFamiliarAspirante($id_aspirante, $data);

        return $this->apiResponse($response);
    }

    public function actualizarFamiliarAspirante(RegistrarFamiliarRequest $request)
    {
        $data = $request->validated();
        $id_familiar = $request->input('id_familiar');

        $response = $this->admisiones_services->actualizarFamiliarAspirante($id_familiar, $data);

        return $this->apiResponse($response);
    }

    public function agregarInformacionMedicaAspirante(RegistrarInformacionMedicaRequest $request)
    {
        $id_aspirante = $request->input('aspirante_id');
        $id_inscripcion = $request->input('id_inscripcion');

        $data = $request->safe()->except([
            'aspirante_id',
            'id_inscripcion',
        ]);

        $response = $this->admisiones_services->agregarInformacionMedicaAspirante($id_aspirante, $id_inscripcion, $data);

        return $this->apiResponse($response);
    }

    public function actualizarInformacionMedicaAspirante(RegistrarInformacionMedicaRequest $request)
    {
        $id_informacion = $request->input('id_informacion');
        $data = $request->safe()->except('id_informacion');

        $response = $this->admisiones_services->actualizarInformacionMedicaAspirante($id_informacion, $data);

        return $this->apiResponse($response);
    }

    public function eliminarInformacionMedicaAspirante(Request $request)
    {
        $id_informacion = $request->input('id_informacion');

        $response = $this->admisiones_services->eliminarInformacionMedicaAspirante($id_informacion);

        return $this->apiResponse($response);
    }

    public function subirDocumentoInscripcion(AdmisionesDocumentoRequest $request)
    {
        $file = $request->file('archivo');

        if (! $file) {
            return response()->json([
                'error' => true,
                'message' => 'No se ha proporcionado ningún archivo.',
                'data' => [],
            ]);
        }

        $id_inscripcion = $request->input('id_inscripcion');

        $data = $request->safe()->except(['archivo']);

        $resultado = $this->cloudinary_service->uploadFile($file, 'Admisiones/documentos');

        if ($resultado['error']) {
            return $this->apiResponse($resultado);
        }

        $cloudinary = $resultado['data'];

        $response = $this->admisiones_services->subirDocumentoInscripcion($id_inscripcion,
            [
                ...$data,
                'nombre_original' => $file->getClientOriginalName(),
                'url_archivo' => $cloudinary['url'],
                'public_id' => $cloudinary['public_id'],
                'formato' => $cloudinary['format'],
                'peso' => $cloudinary['size'],
            ]
        );

        return $this->apiResponse($response);
    }

    public function actualizarEstadoDeInscripcionAspirante(Request $request){
        $id_inscripcion = $request->input('id_inscripcion');
        $estado = $request->input("estado");
        $correo_acudiente = $request->input("email") ?? null;

        $response = $this->admisiones_services->actualizarEstadoDeInscripcionAspirante($id_inscripcion, $estado, $correo_acudiente);

        return $this->apiResponse($response);
    }
}
