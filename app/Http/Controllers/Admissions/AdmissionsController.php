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
use App\Http\Requests\Admisiones\ReferenciaFamiliarRequest;
use App\Http\Requests\Admisiones\RegistrarFamiliaresRequest;
use App\Http\Traits\HasAuthCookie;
use App\Services\Admisiones\AdmisionesServices;
use App\Services\AnioEscolar\AnioEscolarServices;
use App\Services\Auth\AuthServices;
use App\Services\Cloudinary\CloudinaryService;
use App\Services\JwtService;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

            $userId = $userCreated['data']->id_user;

            $this->admisiones_services->registrarInscripcion([
                'id_usuario_registro' => $userId,
                'anio_academico' => $lastYear,
            ]);

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

        $data['anio_academico'] = $this->anio_escolar_services->obtenerUltimoAnioEscolar()['data']->id;

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

    public function eliminarDatoInscripcion(Request $request){
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

    public function eliminarFamiliarAspirante(Request $request){
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

        if (empty($ids) || !is_array($ids)) {
            return $this->apiResponse([
                'error' => true,
                'message' => 'Debe enviar un arreglo de IDs',
                'data' => []
            ]);
        }

        $documentos = $this->admisiones_services
            ->verInfoDocumentos($ids);

        if ($documentos['error']) {
            return $this->apiResponse($documentos);
        }

        $imageFormats = ['png', 'jpg', 'jpeg', 'webp'];

        foreach ($documentos['data'] as $docs) {

            if (!empty($docs['public_id'])) {

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
                        ]
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

    public function mostrarTodosLosEstadosDeInscripcion(Request $request){

        $activo = $request->input('activo');
        $activo_bool = (!is_null($activo)) ? filter_var($activo, FILTER_VALIDATE_BOOL) : $activo_bool = null; 
        
        Log::info("Estado Request: ", ['estado' => $activo]);

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


}
