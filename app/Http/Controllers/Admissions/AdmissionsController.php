<?php

namespace App\Http\Controllers\Admissions;

use App\Events\RequestEmailAdmission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admisiones\AdmisionesDocumentoRequest;
use App\Http\Requests\Admisiones\ReferenciaFamiliarRequest;
use App\Http\Requests\Admisiones\RegistrarAspiranteRequest;
use App\Http\Requests\Admisiones\RegistrarFamiliaresRequest;
use App\Http\Requests\Admisiones\RegistrarInformacionMedicaRequest;
use App\Http\Requests\Admisiones\RegistrarInscripcionRequest;
use App\Http\Requests\Admissions\FamilyRegisterRequest;
use App\Http\Requests\Admissions\VerificationCodeRequest;
use App\Http\Traits\HasAuthCookie;
use App\Models\Usuarios\Usuario;
use App\Services\Admisiones\AdmisionesServices;
use App\Services\AnioEscolar\AnioEscolarServices;
use App\Services\Auth\AuthServices;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\JwtService;
use App\Services\MailService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AdmissionsController extends Controller
{
    use HasAuthCookie;

    protected AdmisionesServices $admisiones_services;

    protected CloudinaryService $cloudinary_service;

    protected AuthServices $service_auth;

    protected AnioEscolarServices $anio_escolar_services;

    protected MailService $mail_service;

    public function __construct(AdmisionesServices $admisionesServices, CloudinaryService $cloudinaryService, AuthServices $service_auth, AnioEscolarServices $anio_escolar_services, private JwtService $jwt, private UsuariosServices $usuarios_services, MailService $mail_service)
    {
        $this->admisiones_services = $admisionesServices;
        $this->cloudinary_service = $cloudinaryService;
        $this->service_auth = $service_auth;
        $this->anio_escolar_services = $anio_escolar_services;
        $this->mail_service = $mail_service;
    }

    public function testEmail(Request $request)
    {
        //$message = $request->input("message");

        $mail = $this->mail_service->sendGeneric(['casaloboblanco@gmail.com', 'djhoniersamir@gmail.com'], "Probando el Email aquí jeje", "Contenido del correo xd");

        return $this->apiResponse($mail);
    }

    public function requestVerification(Request $request)
    {
        $request->validate(
            [
                'email' => 'required|email|min:10|max:140',
            ],
            [
                'email.required' => 'El correo es un campo obligatorio.',
                'email.email' => 'El correo no tiene un formato valido.',
                'email.min' => 'El correo debe tener al menos 10 caracteres',
                'email.max' => 'El correo no puede superar los 140 caracteres',
            ]
        );

        return $this->enviarCodigoRegistro($request->email);
    }

    /**
     * Envía el código de verificación por correo del flujo histórico (primera
     * inscripción, o acudiente ya registrado pero sin contraseña todavía — ver
     * iniciarAcceso). Extraído de requestVerification para que iniciarAcceso pueda
     * dispararlo sin pasar por la validación de esa ruta pública (que exige `email`,
     * no `correo`).
     */
    private function enviarCodigoRegistro(string $email)
    {
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

        $this->mail_service->sendView($email, 'Verifica tu correo — Admisiones Royal School', 'emails.sendRequestEmail', [
            'verificationCode' => $code,
            'email'           => $email,
        ]);

        Log::info("Enviando correo de verificacion a {$email}");

        return $this->success('Codigo enviado!', [
            'modo' => 'registro',
            'token' => $token,
        ]);
    }

    public function validateVerificationCode(Request $request)
    {
        $request->validate(
            [
                'token' => 'required|string|size:64',
            ],
            [
                'token.required' => 'El token es obligatorio.',
                'token.string' => 'El token debe ser una cadena válida.',
                'token.size' => 'El token no es válido.'
            ]
        );

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
            // Igual que el login por contraseña (ver otorgarSesionAcudiente): se deja
            // registrada la IP de este acceso, sin importar por cuál de los dos flujos
            // haya entrado, para que loginConPassword sepa comparar contra la más
            // reciente sin importar si vino de acá o de ahí.
            $userExists->update(['ultima_ip' => $request->ip(), 'ultima_conexion' => now()]);

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
        ], now()->addMinutes(15));

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

        // Se asigna el perfil de "Acudiente"
        $data['perfil'] = 6;

        // Marca explícita de origen — reemplaza la heurística anterior basada en
        // user_log (quién creó el registro), que no era confiable (ver migración
        // add_origen_registro_to_usuarios_table). Este es el único lugar de la app que
        // debe escribir 'admisiones' acá.
        $data['origen_registro'] = 'admisiones';

        // Usuario::setPassAttribute ya se encarga de hashear — se le pasa el valor plano,
        // no un Hash::make() (eso duplicaría el hasheo y rompería el login).
        $data['pass'] = $data['password'];
        unset($data['password'], $data['password_confirmation']);

        if (! $validation) {
            return $this->error('Tu sesión de registro ha expirado o no es válida. Inicia el proceso nuevamente.', 401);
        }

        if ($validation['email'] !== $request->correo) {
            return $this->error('El correo no coincide con la sesión de registro.', 403);
        }

        try {
            $userCreated = $this->service_auth->registrarUsuario($data);

            Cache::forget($registerKey);

            $lastYear = $this->anio_escolar_services->obtenerUltimoAnioEscolar()['data']->id;

            $usuario = $userCreated['data'];

            $this->admisiones_services->registrarInscripcion([
                'id_usuario_registro' => $usuario->id_user,
                'anio_academico' => $lastYear,
            ]);

            // Deja registrada la IP de este primer acceso, igual que el resto de flujos
            // de login (ver otorgarSesionAcudiente/forgetVerificationCode) — para que un
            // futuro loginConPassword desde el mismo lugar no la trate como IP nueva.
            $usuario->update(['ultima_ip' => $request->ip(), 'ultima_conexion' => now()]);

            // El registro por sí solo no otorgaba sesión — el usuario quedaba creado
            // pero sin cookie, así que el redirect posterior a registrationProcess
            // rebotaba por falta de autenticación. Mismo mecanismo que el resto de
            // AdmissionsController (otorgarSesionAcudiente/forgetVerificationCode).
            return $this->success('Registro completado exitosamente.', 201)
                ->withCookie($this->makeCookie($this->jwt->generateAdmissionsToken($usuario), 'admissions_token'));
        } catch (\Exception $e) {
            return $this->error('Ocurrió un error al procesar el registro. Intenta de nuevo.', 500);
        }
    }

    /**
     * Login por contraseña para acudientes que ya la registraron (ver
     * FamilyRegisterRequest::password) — alternativa al flujo de solo-OTP existente
     * (requestVerification/forgetVerificationCode), que sigue disponible sin cambios
     * para quien todavía no tiene contraseña. Mismo patrón de InstitucionController::
     * login(): la contraseña sola basta si la IP coincide con la del último login
     * exitoso (usuarios.ultima_ip); si es distinta (o es la primera vez), se exige
     * además el código enviado al correo antes de otorgar la sesión.
     */
    public function loginConPassword(Request $request)
    {
        $request->validate(
            [
                'correo' => 'required|email',
                'password' => 'required|string',
            ],
            [
                'correo.required' => 'El correo es obligatorio.',
                'correo.email' => 'El correo no tiene un formato válido.',
                'password.required' => 'La contraseña es obligatoria.',
            ]
        );

        $usuario = $this->usuarios_services->infoUserWhitEmail($request->correo);

        if (! $usuario || ! $usuario->pass) {
            return $this->error('Correo o contraseña incorrectos.', 401);
        }

        return $this->intentarLoginConPassword($usuario, $request->password, $request->ip());
    }

    /**
     * Punto de entrada único de /admissions: un solo formulario correo+contraseña que
     * decide por sí mismo qué flujo aplica, en vez de dos pantallas separadas.
     * - Correo ya registrado y con contraseña → intenta login por contraseña (con el
     *   paso de IP nueva de intentarLoginConPassword).
     * - Correo nuevo, o registrado antes de que existiera contraseña → cae al flujo
     *   histórico de solo-OTP (registro o, si ya tiene cuenta, entra directo al
     *   verificar el código — ver forgetVerificationCode).
     * El frontend distingue el resultado por `modo` ("login" vs "registro") para saber
     * contra qué endpoint verificar el código si `requires_otp` viene en true.
     */
    public function iniciarAcceso(Request $request)
    {
        $request->validate(
            [
                'correo' => 'required|email|min:10|max:140',
                'password' => 'nullable|string',
            ],
            [
                'correo.required' => 'El correo es un campo obligatorio.',
                'correo.email' => 'El correo no tiene un formato valido.',
                'correo.min' => 'El correo debe tener al menos 10 caracteres',
                'correo.max' => 'El correo no puede superar los 140 caracteres',
            ]
        );

        $correo = $request->correo;
        $usuario = $this->usuarios_services->infoUserWhitEmail($correo);

        if ($usuario && $usuario->pass) {
            if (! $request->filled('password')) {
                return $this->error('Ingresa tu contraseña.', 422);
            }

            return $this->intentarLoginConPassword($usuario, $request->password, $request->ip());
        }

        return $this->enviarCodigoRegistro($correo);
    }

    private function intentarLoginConPassword(Usuario $usuario, string $password, string $ip)
    {
        $correo = $usuario->correo;

        $rateLimitKey = "admisiones_login_{$ip}_{$correo}";
        $attempts = Cache::increment($rateLimitKey);

        if ($attempts === 1) {
            Cache::put($rateLimitKey, 1, now()->addMinutes(10));
        }

        if ($attempts > 5) {
            return $this->error('Demasiados intentos. Intenta de nuevo más tarde.', 429);
        }

        if (! Hash::check($password, $usuario->pass)) {
            Log::warning('Intento de login de acudiente fallido', ['correo' => $correo, 'ip' => $ip]);

            return $this->error('Correo o contraseña incorrectos.', 401);
        }

        Cache::forget($rateLimitKey);

        if ($usuario->ultima_ip && $usuario->ultima_ip === $ip) {
            return $this->otorgarSesionAcudiente($usuario, $ip);
        }

        return $this->iniciarVerificacionLoginAcudiente($usuario);
    }

    /**
     * Envía el código de verificación de un login desde una IP nueva. Token de corta
     * vida (15 min) que solo identifica al usuario en pausa de verificación — el
     * código en sí vive en una entrada separada de 5 min, mismo patrón de dos niveles
     * que InstitucionController::iniciarVerificacionLogin.
     */
    private function iniciarVerificacionLoginAcudiente(Usuario $usuario)
    {
        $rateLimitKey = "admisiones_login_otp_send_{$usuario->id_user}";
        $attempts = Cache::increment($rateLimitKey);

        if ($attempts === 1) {
            Cache::put($rateLimitKey, 1, now()->addMinutes(5));
        }

        if ($attempts > 3) {
            return $this->error('Demasiadas solicitudes. Intenta de nuevo más tarde.', 429);
        }

        $token = Str::random(64);
        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        Cache::put("admisiones_login_pending_{$token}", ['id' => $usuario->id_user], now()->addMinutes(15));
        Cache::put("admisiones_login_otp_{$token}", ['code' => $code, 'id' => $usuario->id_user], now()->addMinutes(5));

        $this->mail_service->sendView($usuario->correo, 'Nuevo inicio de sesión — Admisiones Royal School', 'emails.sendAcudienteLoginOtp', [
            'verificationCode' => $code,
            'nombre' => $usuario->nombre,
        ]);

        Log::info("Login desde IP nueva para acudiente {$usuario->id_user}, verificación enviada");

        return $this->success('Verificación requerida', [
            'modo' => 'login',
            'requires_otp' => true,
            'token' => $token,
        ]);
    }

    public function resendLoginOtpAcudiente(Request $request)
    {
        $request->validate(
            ['token' => 'required|string|size:64'],
            ['token.required' => 'El token es obligatorio.', 'token.size' => 'El token no es válido.']
        );

        $pending = Cache::get("admisiones_login_pending_{$request->token}");

        if (! $pending) {
            return $this->error('Sesión inválida o expirada. Inicia sesión nuevamente.', 401);
        }

        $usuario = Usuario::find($pending['id']);

        if (! $usuario) {
            return $this->error('Usuario no encontrado.', 404);
        }

        $rateLimitKey = "admisiones_login_otp_send_{$usuario->id_user}";
        $attempts = Cache::increment($rateLimitKey);

        if ($attempts === 1) {
            Cache::put($rateLimitKey, 1, now()->addMinutes(5));
        }

        if ($attempts > 3) {
            return $this->error('Demasiadas solicitudes. Intenta de nuevo más tarde.', 429);
        }

        $code = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        Cache::put("admisiones_login_otp_{$request->token}", ['code' => $code, 'id' => $usuario->id_user], now()->addMinutes(5));

        $this->mail_service->sendView($usuario->correo, 'Nuevo inicio de sesión — Admisiones Royal School', 'emails.sendAcudienteLoginOtp', [
            'verificationCode' => $code,
            'nombre' => $usuario->nombre,
        ]);

        return $this->success('Código reenviado');
    }

    public function verifyLoginOtpAcudiente(Request $request)
    {
        $request->validate(
            ['token' => 'required|string|size:64', 'code' => 'required|digits:5'],
            [
                'token.required' => 'El token es obligatorio.',
                'token.size' => 'El token no es válido.',
                'code.required' => 'El código de verificación es obligatorio.',
                'code.digits' => 'El código debe tener 5 dígitos.',
            ]
        );

        $token = $request->token;
        $key = "admisiones_login_otp_{$token}";
        $attemptsKey = "admisiones_login_otp_attempts_{$token}";

        $data = Cache::get($key);

        if (! $data) {
            return $this->error('Token inválido o expirado.', 400);
        }

        $attempts = Cache::increment($attemptsKey);

        if ($attempts === 1) {
            Cache::put($attemptsKey, 1, now()->addMinutes(5));
        }

        if ($attempts > 5) {
            Cache::forget($key);
            Cache::forget($attemptsKey);
            Cache::forget("admisiones_login_pending_{$token}");

            return $this->error('Demasiados intentos.', 429);
        }

        if ($data['code'] !== $request->code) {
            return $this->error('Código inválido.', 400);
        }

        $usuario = Usuario::find($data['id']);

        if (! $usuario) {
            return $this->error('Usuario no encontrado.', 404);
        }

        Cache::forget($key);
        Cache::forget($attemptsKey);
        Cache::forget("admisiones_login_pending_{$token}");

        return $this->otorgarSesionAcudiente($usuario, $request->ip());
    }

    private function otorgarSesionAcudiente(Usuario $usuario, string $ip)
    {
        $usuario->update(['ultima_ip' => $ip, 'ultima_conexion' => now()]);

        return $this->success('Sesión iniciada', ['redirect' => true])
            ->withCookie($this->makeCookie($this->jwt->generateAdmissionsToken($usuario), 'admissions_token'));
    }

    public function registrarInscripcion(RegistrarInscripcionRequest $request)
    {
        $data = $request->validated();


        $data['anio_academico'] = $data['anio_academico']
            ?? $this->anio_escolar_services->obtenerUltimoAnioEscolar()['data']->id;

        $resultado = $this->admisiones_services->registrarInscripcion($data);

        return $this->apiResponse($resultado);
    }

    public function actualizarDatosInscripcion(Request $request)
    {
        $id = $request->input('id');
        $data = $request->validate(
            [
                'anio_academico' => 'required|integer|exists:anio_escolar,id'
            ]
        );

        $resultado = $this->admisiones_services->actualizarDatosInscripcion($id, $data);

        return $this->apiResponse($resultado);
    }

    public function mostrarTodasIncripcionesAcudiente(Request $request)
    {
        $id_acudiente = $request->input('id_acudiente');

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

        $data['anio_academico'] = $data['anio_academico']
            ?? $this->anio_escolar_services->obtenerUltimoAnioEscolar()['data']->id;

        $resultado = $this->admisiones_services->registrarAspirante($data);

        return $this->apiResponse($resultado);
    }

    public function eliminarInscripcion(Request $request)
    {
        $id_inscripcion = $request->input('id');

        if (! $id_inscripcion) {
            return response()->json([
                'error' => true,
                'message' => 'Debe proporcionar un ID de inscripción válido.',
                'data' => [],
            ]);
        }

        $resultado = $this->admisiones_services->eliminarInscripcion($id_inscripcion);

        return $this->apiResponse($resultado);
    }

    public function eliminarDatoInscripcion(Request $request)
    {
        $id_inscripcion = $request->input('id');

        $response = $this->admisiones_services->eliminarDatoInscripcion($id_inscripcion);

        return $this->apiResponse($response);
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

    public function eliminarRegistroAspirante(Request $request)
    {
        $id = $request->input('id');

        if (! $id) {
            return response()->json([
                'error' => true,
                'message' => 'Debe proporcionar un ID de aspirante válido',
                'data' => [],
            ]);
        }

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

    public function agregarFamiliarAspirante(RegistrarFamiliaresRequest $request)
    {
        $data = $request->validated();

        $response = $this->admisiones_services->agregarFamiliarAspirante($data);

        return $this->apiResponse($response);
    }

    public function actualizarFamiliarAspirante(RegistrarFamiliaresRequest $request)
    {
        $data = $request->validated();
        $id_familiar = $request->input('id_familiar');

        $response = $this->admisiones_services->actualizarFamiliarAspirante($id_familiar, $data);

        return $this->apiResponse($response);
    }

    public function eliminarFamiliarAspirante(Request $request)
    {
        $id_familiar = $request->input("id_acudiente");

        $response = $this->admisiones_services->eliminarFamiliarAspirante($id_familiar);

        return $this->apiResponse($response);
    }

    public function agregarInformacionMedicaAspirante(RegistrarInformacionMedicaRequest $request)
    {
        $data = $request->validated();

        $response = $this->admisiones_services->agregarInformacionMedicaAspirante($data);

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

    public function eliminarDocumentos(Request $request)
    {
        $ids = $request->input('ids');

        if (empty($ids) || ! is_array($ids)) {
            return $this->apiResponse([
                'error' => true,
                'message' => 'Debe enviar un arreglo de IDs',
                'data' => [],
            ]);
        }

        $documentos = $this->admisiones_services
            ->verInfoDocumentos($ids);

        if ($documentos['error']) {
            return $this->apiResponse($documentos);
        }

        $imageFormats = ['png', 'jpg', 'jpeg', 'webp'];

        foreach ($documentos['data'] as $docs) {

            if (! empty($docs['public_id'])) {

                $resourceType = in_array(strtolower($docs['formato'] ?? ''), $imageFormats, true)
                    ? 'image'
                    : 'raw';

                $eliminar_cloud = $this->cloudinary_service
                    ->deleteFile($docs['public_id'], $resourceType);

                if ($eliminar_cloud['error']) {
                    return $this->apiResponse([
                        'error' => true,
                        'message' => $eliminar_cloud['message'] ?? 'Error eliminando archivo en Cloudinary',
                        'data' => [
                            'documento' => $docs,
                            'resource_type_usado' => $resourceType,
                        ],
                    ]);
                }
            }
        }

        $eliminar_db = $this->admisiones_services
            ->eliminarDocumentos($ids);

        return $this->apiResponse($eliminar_db);
    }

    public function subirDocumentoInscripcion(AdmisionesDocumentoRequest $request)
    {
        // Obtenemos el arreglo de documentos (cada uno con su archivo y datos)
        $documentos = $request->input('documentos');
        $id_inscripcion = $request->input('id_inscripcion');

        if (! is_array($documentos) || empty($documentos)) {
            return response()->json([
                'error' => true,
                'message' => 'No se ha proporcionado ningún documento.',
                'data' => [],
            ]);
        }

        $respuestas_archivos = [];

        // Recorremos el arreglo usando el índice ($key) para poder extraer el archivo correcto
        foreach ($documentos as $key => $datos_documento) {

            // Recuperamos el archivo binario usando la llave del array
            // Ejemplo en el request: documentos[archivo], documentos[archivo]
            $file = $request->file("documentos.{$key}.archivo");

            if (! $file) {
                $respuestas_archivos[] = [
                    'estado' => 'error',
                    'detalle' => "No se encontró el archivo físico para el elemento indexado en {$key}.",
                ];

                continue;
            }

            // 1. Subir archivo a Cloudinary
            $resultado = $this->cloudinary_service->uploadFile($file, 'Admisiones/documentos');

            if ($resultado['error']) {
                $respuestas_archivos[] = [
                    'nombre_original' => $file->getClientOriginalName(),
                    'estado' => 'error',
                    'detalle' => $resultado['message'] ?? 'Error al subir a Cloudinary',
                ];

                continue;
            }

            $cloudinary = $resultado['data'];

            // Limpiamos los datos de este documento específico para no enviar el archivo binario al servicio
            // Esto remueve el objeto del archivo y te deja solo con la metadata (ej: tipo_doc, descripcion, etc.)
            unset($datos_documento['archivo']);

            // 2. Guardar en la base de datos combinando la metadata específica de este archivo
            $response = $this->admisiones_services->subirDocumentoInscripcion(
                $id_inscripcion,
                [
                    ...$datos_documento, // Aquí van los datos únicos de ESTE archivo
                    'nombre_original' => $file->getClientOriginalName(),
                    'url_archivo' => $cloudinary['url'],
                    'public_id' => $cloudinary['public_id'],
                    'formato' => $cloudinary['format'],
                    'peso' => $cloudinary['size'],
                ]
            );

            $respuestas_archivos[] = [
                'nombre_original' => $file->getClientOriginalName(),
                'estado' => 'exito',
                'detalle' => $response,
            ];
        }

        return response()->json([
            'error' => false,
            'message' => 'Procesamiento de documentos finalizado.',
            'data' => $respuestas_archivos,
        ]);
    }

    public function visualizarDocumentosInscripcion(Request $request)
    {
        $publicId = $request->input('public_id');
        $format = $request->input('format');

        if (! $publicId || ! $format) {
            return response()->json([
                'error' => true,
                'message' => 'Debe proporcionar un ID de inscripción válido.',
                'data' => [],
            ]);
        }

        $response = $this->cloudinary_service->getFileUrl($publicId, $format);

        return $this->apiResponse($response);
    }

    public function subirReferenciasFamiliaresAspirante(ReferenciaFamiliarRequest $request)
    {
        $id_inscripcion = $request->input('id_inscripcion');
        $body = $request->validated();

        unset($body['id_inscripcion']);

        $response = $this->admisiones_services->subirReferenciasFamiliaresAspirante($id_inscripcion, $body);

        return $this->apiResponse($response);
    }

    public function actualizarReferenciasFamiliaresAspirante(ReferenciaFamiliarRequest $request)
    {
        $id_referencia_familiar = $request->input('id');
        $body = $request->validated();
        unset($body['id']);

        $response = $this->admisiones_services->actualizarReferenciasFamiliaresAspirante($id_referencia_familiar, $body);

        return $this->apiResponse($response);
    }

    public function eliminarReferenciaFamiliarAspirante(Request $request)
    {
        $id_referencia = $request->input('id_referencia');
        $response = $this->admisiones_services->eliminarReferenciaFamiliarAspirante($id_referencia);

        return $this->apiResponse($response);
    }

    public function mostrarTodosLosEstadosDeInscripcion(Request $request)
    {

        $activo = $request->input('activo');
        $activo_bool = (!is_null($activo)) ? filter_var($activo, FILTER_VALIDATE_BOOL) : $activo_bool = null;

        $response = $this->admisiones_services->mostrarTodosLosEstadosDeInscripcion($activo_bool);

        return $this->apiResponse($response);
    }

    public function actualizarEstadoDeInscripcionAspirante(Request $request)
    {
        $data = $request->validate([
            'estado' => 'required|integer|exists:admisiones_estados,id',
            'id_inscripcion' => 'required|integer|exists:admisiones_inscripciones,id',
            'updated_by' => 'required|integer|exists:usuarios,id_user',
            'email' => 'nullable|email',
        ]);

        $response = $this->admisiones_services->actualizarEstadoDeInscripcionAspirante(
            $data['id_inscripcion'],
            $data['estado'],
            $data['email'] ?? null,
            $data['updated_by']
        );

        return $this->apiResponse($response);
    }

    public function mostrarAspirantesAPsicologa(Request $request){
        $perfil_psicologa = (int) $request->input('perfil');

        if(!$perfil_psicologa){
            return response()->json([
                'error' => true,
                'message' => 'Debe proporcionar un perfil de psicologa válido',
                'data' => []
            ]);
        }

        $response = $this->admisiones_services->mostrarAspirantesAPsicologa($perfil_psicologa);

        return $this->apiResponse($response);
    }

    public function listarPsicologasDisponibles(Request $request)
    {
        $response = $this->admisiones_services->listarPsicologasDisponibles();

        return $this->apiResponse($response);
    }

    public function agendarCitaPsicologia(Request $request)
    {
        $data = $request->validate([
            'id_inscripcion' => 'required|integer|exists:admisiones_inscripciones,id',
            'id_psicologa' => 'required|integer|exists:usuarios,id_user',
            'fecha_cita' => 'required|date',
            'observaciones' => 'nullable|string',
        ]);

        $response = $this->admisiones_services->agendarCitaPsicologia(
            $data['id_inscripcion'],
            $data['id_psicologa'],
            $data['fecha_cita'],
            $data['observaciones'] ?? null
        );

        return $this->apiResponse($response);
    }

    public function obtenerCitasPsicologiaDeInscripcion(Request $request)
    {
        $id_inscripcion = (int) $request->input('id_inscripcion');

        if (! $id_inscripcion) {
            return response()->json([
                'error' => true,
                'message' => 'Debe proporcionar un id de inscripción válido',
                'data' => [],
            ]);
        }

        $response = $this->admisiones_services->obtenerCitasPsicologiaDeInscripcion($id_inscripcion);

        return $this->apiResponse($response);
    }

    public function actualizarFechaCitaPsicologia(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer|exists:admisiones_citas_psicologia,id',
            'fecha_cita' => 'required|date',
        ]);

        $response = $this->admisiones_services->actualizarFechaCitaPsicologia($data['id'], $data['fecha_cita']);

        return $this->apiResponse($response);
    }

    public function actualizarPsicologaCitaPsicologia(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer|exists:admisiones_citas_psicologia,id',
            'id_psicologa' => 'required|integer|exists:usuarios,id_user',
        ]);

        $response = $this->admisiones_services->actualizarPsicologaCitaPsicologia($data['id'], $data['id_psicologa']);

        return $this->apiResponse($response);
    }

    public function actualizarEstadoCitaPsicologia(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer|exists:admisiones_citas_psicologia,id',
            'estado_cita' => 'required|in:AGENDADA,ATENDIDA',
        ]);

        $response = $this->admisiones_services->actualizarEstadoCitaPsicologia($data['id'], $data['estado_cita']);

        return $this->apiResponse($response);
    }

    public function subirDocumentoObservacionCitaPsicologia(Request $request)
    {
        $data = $request->validate([
            'id' => 'required|integer|exists:admisiones_citas_psicologia,id',
            'documento' => 'required|file|mimes:pdf',
        ]);

        $resultado = $this->cloudinary_service->uploadFile($request->file('documento'), 'Admisiones/CitasPsicologia');

        if ($resultado['error']) {
            return response()->json($resultado, 400);
        }

        $response = $this->admisiones_services->subirDocumentoObservacionCita($data['id'], $resultado['data']['url']);

        return $this->apiResponse($response);
    }

    public function listarCitasPsicologia(Request $request)
    {
        $data = $request->validate([
            'id_psicologa' => 'nullable|integer|exists:usuarios,id_user',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date',
        ]);

        $response = $this->admisiones_services->listarCitasPsicologia(
            $data['id_psicologa'] ?? null,
            $data['fecha_desde'] ?? null,
            $data['fecha_hasta'] ?? null
        );

        return $this->apiResponse($response);
    }
}
