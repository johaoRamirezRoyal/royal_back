<?php

namespace App\Http\Controllers\Hikvision;

use App\Http\Controllers\Controller;
use App\Models\LlegadasTarde\ConfiguracionLlegadasTarde;
use App\Services\AsistenciaTrabajadores\AsistenciaGestionService;
use App\Services\Hikvisionattendance\hikvisionattendanceService;
use App\Services\LlegadasTardeEstudiantes\LlegadasTarde;
use App\Services\Usuarios\UsuariosServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class HikvisionController extends Controller
{
    // Perfil de Estudiante en la tabla perfiles
    const PERFIL_ESTUDIANTE = 16;

    // Ventana de debounce para descartar reintentos/rebotes del dispositivo (mismo
    // usuario marcando "de nuevo" a los pocos segundos de su marcación anterior).
    const SEGUNDOS_DEBOUNCE_MARCACION = 15;

    protected hikvisionattendanceService $hikvision_service;

    protected UsuariosServices $usuario_services;

    protected LlegadasTarde $llegadas_tarde_service;

    protected AsistenciaGestionService $asistencia_gestion_service;

    public function __construct(
        hikvisionattendanceService $hikvisionService,
        UsuariosServices $usuariosServices,
        LlegadasTarde $llegadasTardeService,
        AsistenciaGestionService $asistenciaGestionService
    ) {
        $this->hikvision_service = $hikvisionService;
        $this->usuario_services = $usuariosServices;
        $this->llegadas_tarde_service = $llegadasTardeService;
        $this->asistencia_gestion_service = $asistenciaGestionService;
    }

    public function __invoke(Request $request)
    {
        $data = $this->extraerDatos($request);

        if ($data) {
            Log::info('[hikvision-email] Evento recibido', ['eventType' => $data['eventType'] ?? null]);
        }

        if ($data && ($data['eventType'] ?? '') === 'AccessControllerEvent') {
            $this->testNotificationHikvision($data);
        }

        return response('', 200);
    }

    /**
     * Extrae el payload del evento: JSON plano, XML plano, o
     * multipart/form-data (una parte JSON/XML y, si hay foto, una imagen).
     */
    private function extraerDatos(Request $request): ?array
    {
        $contentType = $request->header('Content-Type', '');

        if (str_contains($contentType, 'multipart/form-data')) {
            // Multipart trae la parte de control (JSON/XML) más, opcionalmente, una
            // foto: se loguean los campos y solo nombre/tamaño de archivos (no los
            // bytes) para no inflar el log con binarios.
            Log::info('[hikvision-notification] Request cruda del dispositivo (multipart)', [
                'contentType' => $contentType,
                'post' => $request->post(),
                'files' => array_map(
                    fn ($archivo) => array_map(
                        fn ($f) => ['name' => $f->getClientOriginalName(), 'size' => $f->getSize()],
                        is_array($archivo) ? $archivo : [$archivo]
                    ),
                    $request->allFiles()
                ),
            ]);

            foreach ($request->post() as $valor) {
                $decoded = $this->intentarDecodificar((string) $valor);
                if ($decoded !== null) {
                    return $decoded;
                }
            }

            foreach ($request->allFiles() as $archivo) {
                $archivos = is_array($archivo) ? $archivo : [$archivo];
                foreach ($archivos as $file) {
                    $decoded = $this->intentarDecodificar(file_get_contents($file->getRealPath()));
                    if ($decoded !== null) {
                        return $decoded;
                    }
                }
            }

            return null;
        }

        $raw = $request->getContent();

        Log::info('[hikvision-notification] Request cruda del dispositivo', [
            'contentType' => $contentType,
            'raw' => $raw,
        ]);

        return $this->intentarDecodificar($raw);
    }

    private function intentarDecodificar(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        $json = json_decode($raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($raw);
        if ($xml instanceof SimpleXMLElement) {
            $arr = json_decode((string) json_encode($xml), true);

            return is_array($arr) ? $arr : null;
        }

        return null;
    }

    public function testHikvisionConexion()
    {
        $informacion = $this->hikvision_service->testConnection();

        if ($informacion['isConnected'] == false) {
            return response()->json([
                'error' => true,
                'message' => 'No se logró conectar a hikvision',
                'data' => null,
            ]);
        }

        return response()->json([
            'error' => false,
            'message' => 'Conexión exitosa!',
            'data' => $informacion['data'],
        ]);
    }

    public function obtenerHttpHosts()
    {
        $resultado = $this->hikvision_service->obtenerHttpHosts();

        return $this->apiResponse($resultado);
    }

    public function obtenerEmpleadosRegistrados(Request $request)
    {
        $pageSize = $request->input('per-page', 30);

        Log::info('[hikvision] Solicitud de empleados registrados', ['pageSize' => $pageSize]);

        $usuarios = $this->hikvision_service->obtenerEmpleadosRegistrados($pageSize);

        return $this->apiResponse($usuarios);
    }

    public function obtenerUnEmpleadoEspecifico(Request $request)
    {
        $id_user = $request->input('id_user');

        if (! $id_user) {
            return response()->json([
                'error' => true,
                'message' => 'Debe proporcionar un id_user válido',
                'data' => [],
            ]);
        }

        Log::info('[hikvision] Solicitud de empleado específico', ['id_user' => $id_user]);

        $datos_usuario = $this->hikvision_service->obtenerUnEmpleadoEspecifico($id_user);

        return $this->apiResponse($datos_usuario);
    }

    public function obtenerImagenEmpleado(Request $request)
    {
        $path = $request->input('path');

        if (! $path) {
            return response()->json([
                'error' => true,
                'message' => 'Debe proporcionar la ruta de la imagen',
                'data' => null,
            ], 400);
        }

        Log::info('[hikvision] Solicitud de imagen de empleado', ['path' => $path, 'deviceId' => $request->input('deviceId')]);

        $resultado = $this->hikvision_service->obtenerImagenEmpleado($path, $request->input('deviceId'));

        if ($resultado['error']) {
            return response()->json($resultado, 400);
        }

        return response($resultado['data']['contenido'])
            ->header('Content-Type', $resultado['data']['contentType'])
            ->header('Cache-Control', 'private, max-age=300');
    }

    public function obtenerAsistenciaEmpleado(Request $request)
    {
        $id_empleado = $request->input('id_empleado');
        $start_date = $request->input('start_date', null);
        $end_date = $request->input('end_date', null);

        Log::info('[hikvision] Solicitud de asistencia de empleado', [
            'id_empleado' => $id_empleado,
            'start_date' => $start_date,
            'end_date' => $end_date,
        ]);

        $response = $this->hikvision_service->obtenerAsistenciaEmpleado($id_empleado, $start_date, $end_date);

        return $this->apiResponse($response);
    }

    public function obtenerEmpleadosRegistradosPorPerfil(Request $request)
    {
        $id_perfil = $request->input('id_perfil');

        Log::info('[hikvision] Solicitud de empleados registrados por perfil', ['id_perfil' => $id_perfil]);

        $usuarios = $this->usuario_services->mostrarUsuariosPorPerfil($id_perfil);

        if ($usuarios['error']) {
            return $this->apiResponse($usuarios);
        }

        $info_usuarios = $this->hikvision_service->obtenerEmpleadosRegistradosPorPerfil($usuarios['data']);

        return $this->apiResponse($info_usuarios);
    }

    public function registrarEmpleado(Request $request)
    {
        $input = $request->all();

        // Acepta objeto único o array de empleados
        $empleados = array_is_list($input) ? $input : [$input];

        $rules = [
            '*.id_user' => ['required', 'integer', 'exists:usuarios,id_user'],
            '*.documento' => ['required', 'string', 'max:16'],
            '*.nombre' => ['required', 'string'],
            '*.perfil' => ['required', 'integer', 'exists:perfiles,id_perfil'],
        ];

        $validator = Validator::make($empleados, $rules);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
            ], 400);
        }

        Log::info('[hikvision] Solicitud de registro de empleado(s)', [
            'ids' => array_column($empleados, 'id_user'),
        ]);

        if (count($empleados) === 1) {
            return $this->apiResponse($this->hikvision_service->registrarEmpleado($empleados[0]));
        }

        return $this->apiResponse($this->hikvision_service->registrarEmpleadosMasivo($empleados));
    }

    public function registrarEmpleadosMasivoPerfil(Request $request)
    {
        $id_perfil = $request->input('id_perfil');

        Log::info('[hikvision] Solicitud de registro masivo por perfil', ['id_perfil' => $id_perfil]);

        $usuarios = $this->usuario_services->mostrarUsuariosPorPerfil($id_perfil);

        if ($usuarios['error']) {
            return $this->apiResponse($usuarios);
        }

        $usuariosPendientes = array_values(array_filter(
            $usuarios['data'],
            fn ($usuario) => empty($usuario['asistenciaRegistrada'])
        ));

        if (empty($usuariosPendientes)) {
            return $this->apiResponse([
                'error' => false,
                'message' => 'Todos los usuarios de este perfil ya estaban registrados en el dispositivo de asistencia',
                'data' => ['success' => [], 'error' => []],
            ]);
        }

        $registro_masivo = $this->hikvision_service->registrarEmpleadosMasivo($usuariosPendientes);

        if (! $registro_masivo['error']) {
            $idsExitosos = array_column($registro_masivo['data']['success'], 'id_user');
            $this->usuario_services->actualizarAsistenciaRegistrada($idsExitosos, true);
        }

        return $this->apiResponse($registro_masivo);
    }

    public function eliminarUsuariosRegistrados(Request $request)
    {
        $id_perfil = $request->input('id_perfil');

        Log::info('[hikvision] Solicitud de eliminación de registrados por perfil', ['id_perfil' => $id_perfil]);

        $usuarios = $this->usuario_services->mostrarUsuariosPorPerfil($id_perfil);

        Log::info('[hikvision] usuarios obtenidos', [
            'users' => $usuarios,
        ]);

        if ($usuarios['error']) {
            return $this->apiResponse($usuarios);
        }

        $usuariosRegistrados = array_values(array_filter(
            $usuarios['data'],
            fn ($usuario) => ! empty($usuario['asistenciaRegistrada'])
        ));

        if (empty($usuariosRegistrados)) {
            return $this->apiResponse([
                'error' => true,
                'message' => 'Ningún usuario de este perfil estaba registrado en el dispositivo de asistencia',
                'data' => [],
            ]);
        }

        $eliminacion_masiva = $this->hikvision_service->eliminarUsuariosRegistrados($usuariosRegistrados);

        if (! $eliminacion_masiva['error']) {
            $idsEliminados = array_column($usuariosRegistrados, 'id_user');
            $this->usuario_services->actualizarAsistenciaRegistrada($idsEliminados, false);
        }

        return $this->apiResponse($eliminacion_masiva);
    }

    public function eliminarEmpleados(Request $request)
    {
        $input = $request->all();

        $empleados = array_is_list($input) ? $input : [$input];

        $validator = Validator::make($empleados, [
            '*.id_user' => ['required', 'integer', 'exists:usuarios,id_user'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
            ], 400);
        }

        Log::info('[hikvision] Solicitud de eliminación de empleado(s)', [
            'ids' => array_column($empleados, 'id_user'),
        ]);

        $resultado = $this->hikvision_service->eliminarUsuariosRegistrados($empleados);

        if (! $resultado['error']) {
            $ids = array_column($empleados, 'id_user');
            $this->usuario_services->actualizarAsistenciaRegistrada($ids, false);
        }

        return $this->apiResponse($resultado);
    }

    public function desactivarUsuario(Request $request)
    {
        $data = $request->all();

        $validator = Validator::make($data, [
            'id_user' => ['required', 'array', 'min:1'],
            'id_user.*' => ['integer', 'exists:usuarios,id_user'],
            'enable' => ['required', 'integer', 'in:0,1'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
            ], 400);
        }

        $enable = $request->input('enable');
        $resultado = ['success' => [], 'error' => []];

        Log::info('[hikvision] Solicitud de activar/desactivar usuario(s)', [
            'ids' => $request->input('id_user'),
            'enable' => $enable,
        ]);

        foreach ($request->input('id_user') as $id_user) {
            $usuario = $this->usuario_services->mostrarInfoUsuarioId($id_user);

            if ($usuario['error'] || ! $usuario['usuario']) {
                $resultado['error'][] = ['id_user' => $id_user, 'message' => 'No se encontró el usuario en la base de datos'];

                continue;
            }

            $desactivar = $this->hikvision_service->desactivarUsuario($usuario['usuario']->toArray(), $enable);

            if ($desactivar['error']) {
                $resultado['error'][] = ['id_user' => $id_user, 'message' => $desactivar['message']];

                continue;
            }

            $resultado['success'][] = ['id_user' => $id_user, 'message' => $desactivar['message']];
        }

        return $this->apiResponse([
            'error' => false,
            'message' => 'Proceso completado',
            'data' => $resultado,
        ]);
    }

    public function registrarHuellaEmpleado(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employeeNo' => ['required', 'string'],
            'fingerPrintID' => ['nullable', 'integer', 'min:1', 'max:10'],
            'deviceId' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
            ], 400);
        }

        Log::info('[hikvision] Solicitud de registro de huella', [
            'employeeNo' => $request->input('employeeNo'),
            'fingerPrintID' => $request->input('fingerPrintID', 1),
            'deviceId' => $request->input('deviceId'),
        ]);

        $resultado = $this->hikvision_service->registrarHuellaEmpleado(
            $request->input('employeeNo'),
            (int) $request->input('fingerPrintID', 1),
            $request->input('deviceId')
        );

        return $this->apiResponse($resultado);
    }

    public function registrarTarjetaEmpleado(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employeeNo' => ['required', 'string'],
            'cardNo' => ['required', 'string'],
            'cardType' => ['nullable', 'string', 'in:normalCard,disabledCard,blockCard,patrolCard,dutyCard,visitorCard'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
            ], 400);
        }

        Log::info('[hikvision] Solicitud de registro de tarjeta', [
            'employeeNo' => $request->input('employeeNo'),
            'cardNo' => $request->input('cardNo'),
        ]);

        $resultado = $this->hikvision_service->registrarTarjetaEmpleado(
            $request->input('employeeNo'),
            $request->input('cardNo'),
            $request->input('cardType', 'normalCard')
        );

        return $this->apiResponse($resultado);
    }

    public function registrarTarjetaEmpleadoCaptura(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employeeNo' => ['required', 'string'],
            'cardType' => ['nullable', 'string', 'in:normalCard,disabledCard,blockCard,patrolCard,dutyCard,visitorCard'],
            'deviceId' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
            ], 400);
        }

        Log::info('[hikvision] Solicitud de registro de tarjeta con captura', [
            'employeeNo' => $request->input('employeeNo'),
            'deviceId' => $request->input('deviceId'),
        ]);

        $resultado = $this->hikvision_service->registrarTarjetaEmpleadoConCaptura(
            $request->input('employeeNo'),
            $request->input('cardType', 'normalCard'),
            $request->input('deviceId')
        );

        return $this->apiResponse($resultado);
    }

    public function eliminarTarjetaEmpleado(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employeeNo' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
            ], 400);
        }

        Log::info('[hikvision] Solicitud de eliminación de tarjeta', ['employeeNo' => $request->input('employeeNo')]);

        $resultado = $this->hikvision_service->eliminarTarjetaEmpleado(
            $request->input('employeeNo')
        );

        return $this->apiResponse($resultado);
    }

    public function registrarContrasenaEmpleado(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employeeNo' => ['required', 'integer', 'exists:usuarios,id_user'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
            ], 400);
        }

        $employeeNo = $request->input('employeeNo');

        $usuario = $this->usuario_services->mostrarInfoUsuarioId($employeeNo);

        if ($usuario['error'] || ! $usuario['usuario']) {
            return response()->json([
                'error' => true,
                'message' => 'No se encontró el usuario en la base de datos',
                'data' => [],
            ], 400);
        }

        Log::info('[hikvision] Solicitud de registro de contraseña', ['employeeNo' => $employeeNo]);

        $resultado = $this->hikvision_service->registrarContrasenaEmpleado(
            (string) $employeeNo,
            (string) $usuario['usuario']->documento
        );

        return $this->apiResponse($resultado);
    }

    public function registrarRostroEmpleado(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employeeNo' => ['required', 'string'],
            'faceLibraryId' => ['nullable', 'string'],
            'deviceId' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
            ], 400);
        }

        Log::info('[hikvision] Solicitud de registro de rostro', [
            'employeeNo' => $request->input('employeeNo'),
            'deviceId' => $request->input('deviceId'),
        ]);

        $resultado = $this->hikvision_service->registrarRostroEmpleado(
            $request->input('employeeNo'),
            $request->input('faceLibraryId', '1'),
            $request->input('deviceId')
        );

        return $this->apiResponse($resultado);
    }

    public function cancelarRegistroRostro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employeeNo' => ['required', 'string'],
            'deviceId' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
            ], 400);
        }

        Log::info('[hikvision] Solicitud de cancelación de captura de rostro', [
            'employeeNo' => $request->input('employeeNo'),
            'deviceId' => $request->input('deviceId'),
        ]);

        $resultado = $this->hikvision_service->cancelarRegistroRostro(
            $request->input('employeeNo'),
            $request->input('deviceId')
        );

        return $this->apiResponse($resultado);
    }

    /**
     * Lista los terminales configurados (id, nombre, host, puerto), para que
     * el frontend pueda ofrecer un selector de terminal antes de una captura.
     */
    public function listarDispositivos()
    {
        return $this->apiResponse([
            'error' => false,
            'message' => 'OK',
            'data' => $this->hikvision_service->devicesInfo(),
        ]);
    }

    /**
     * Guarda/edita el nombre legible de un terminal ya configurado (ej. "Garita abajo").
     */
    public function guardarNombreDispositivo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'deviceId' => ['required', 'string'],
            'nombre' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
            ], 400);
        }

        Log::info('[hikvision] Solicitud de guardar nombre de dispositivo', [
            'deviceId' => $request->input('deviceId'),
            'nombre' => $request->input('nombre'),
        ]);

        $resultado = $this->hikvision_service->guardarNombreDispositivo(
            $request->input('deviceId'),
            $request->input('nombre')
        );

        return $this->apiResponse($resultado);
    }

    public function eliminarHuellaEmpleado(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employeeNo' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first(),
                'data' => [],
            ], 400);
        }

        Log::info('[hikvision] Solicitud de eliminación de huellas', ['employeeNo' => $request->input('employeeNo')]);

        $resultado = $this->hikvision_service->eliminarTodasLasHuellasEmpleado(
            $request->input('employeeNo')
        );

        return $this->apiResponse($resultado);
    }

    public function testNotificationHikvision(Request $request)
    {
        $data = $this->extraerDatos($request);

        $employeeNoString = $data['AccessControllerEvent']['employeeNoString'] ?? null;

        // Ignorar ruido: apertura/cierre de puerta y pings periódicos del equipo
        // sin persona asociada. Solo nos interesa el evento real de marcación biométrica.
        if ($employeeNoString === null) {
            return;
        }

        Log::info('[hikvision-notification] Evento de asistencia recibido', [
            'employeeNoString' => $employeeNoString,
            'dateTime' => $data['dateTime'] ?? null,
            'raw' => $data,
        ]);

        $idUsuario = (int) $employeeNoString;

        // El dispositivo ya no distingue entrada/salida (eventos de marcación biométrica
        // genéricos) — cada método decide por su cuenta, con un typecheck sobre el estado
        // del día, si esta marcación aplica como entrada (llegada tarde solo tiene sentido
        // ahí) y es idempotente por día, así que llamarlo en cada evento es seguro.
        $this->registrarLlegadaTardeSiAplica($idUsuario);

        $this->registrarAsistenciaGestionSiAplica($idUsuario);
    }

    /**
     * Registra una llegada tarde si el usuario es estudiante y el servidor recibió el
     * evento después de la hora límite. Se usa la hora del servidor (no la del
     * dispositivo) porque el reloj del equipo no es confiable. Idempotente por día
     * (ver LlegadasTarde::agregarLlegadaTarde), así que es seguro invocarlo en cada
     * marcación del día, no solo en la primera.
     */
    private function registrarLlegadaTardeSiAplica(int $idUsuario): void
    {
        $usuario = $this->usuario_services->mostrarInfoUsuarioId($idUsuario);

        if ($usuario['error'] || ! $usuario['usuario']) {
            Log::warning('[hikvision-notification] Llegada tarde omitida: usuario no encontrado', ['idUsuario' => $idUsuario]);
            return;
        }

        if ($usuario['usuario']->estado !== 'activo' || (int) $usuario['usuario']->perfil !== self::PERFIL_ESTUDIANTE) {
            Log::info('[hikvision-notification] Llegada tarde omitida: no aplica (no es estudiante activo)', [
                'idUsuario' => $idUsuario,
                'estado' => $usuario['usuario']->estado,
                'perfil' => $usuario['usuario']->perfil,
            ]);
            return;
        }

        $ahora = now();
        $horaLimite = ConfiguracionLlegadasTarde::find(1)?->hora_limite ?? '07:15:00';

        if ($ahora->format('H:i:s') <= $horaLimite) {
            return;
        }

        $resultado = $this->llegadas_tarde_service->agregarLlegadaTarde(
            $idUsuario,
            $ahora->format('Y-m-d'),
            $ahora->format('H:i:s')
        );

        if ($resultado['error']) {
            Log::error('[hikvision-notification] Fallo al registrar llegada tarde', [
                'idUsuario' => $idUsuario,
                'message' => $resultado['message'] ?? null,
            ]);
        }
    }

    /**
     * Registra la marcación (entrada o salida) en asistencia_gestion si el usuario
     * no está en la lista de perfiles excluidos (estudiantes, admin, etc.). El tipo
     * lo resuelve AsistenciaGestionService::registrarMarcacion() con un typecheck sobre
     * el estado del día (ver docblock de ese método).
     */
    private function registrarAsistenciaGestionSiAplica(int $idUsuario): void
    {
        $usuario = $this->usuario_services->mostrarInfoUsuarioId($idUsuario);

        if ($usuario['error'] || ! $usuario['usuario']) {
            Log::warning('[hikvision-notification] Asistencia gestión omitida: usuario no encontrado', ['idUsuario' => $idUsuario]);
            return;
        }

        $perfil = (int) $usuario['usuario']->perfil;

        if (in_array($perfil, AsistenciaGestionService::PERFILES_EXCLUIDOS_ASISTENCIA, true)) {
            Log::info('[hikvision-notification] Asistencia gestión omitida: perfil excluido', [
                'idUsuario' => $idUsuario,
                'perfil' => $perfil,
            ]);
            return;
        }

        // Cache::add() es atómico (falla si la clave ya existe): si el dispositivo
        // reenvía el mismo evento físico segundos después (reintento/rebote), esta
        // segunda petición se descarta aquí en vez de llegar a registrarMarcacion(),
        // donde se interpretaría erróneamente como la salida del usuario.
        if (!Cache::add("hikvision:marcacion:{$idUsuario}", true, self::SEGUNDOS_DEBOUNCE_MARCACION)) {
            Log::info('[hikvision-notification] Marcación descartada: petición duplicada dentro de la ventana de debounce', [
                'idUsuario' => $idUsuario,
            ]);

            return;
        }

        $ahora = now();

        $resultado = $this->asistencia_gestion_service->registrarMarcacion(
            $idUsuario,
            $ahora->format('Y-m-d'),
            $ahora->format('H:i:s')
        );

        if ($resultado['error']) {
            Log::error('[hikvision-notification] Fallo al registrar asistencia gestión', [
                'idUsuario' => $idUsuario,
                'message' => $resultado['message'] ?? null,
            ]);

            return;
        }

        Log::info('[hikvision-notification] Marcación de asistencia registrada', [
            'idUsuario' => $idUsuario,
            'tipo' => $resultado['data']['tipo'] ?? null,
            'message' => $resultado['message'],
        ]);
    }
}
