<?php

namespace App\Services\Hikvisionattendance;

use App\Models\Hikvision\HikvisionDispositivo;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


class hikvisionattendanceService
{
    /** @var array<int, array{id: string, protocol: string, host: string, port: mixed}> */
    protected array $devices;

    /** @var array<string, Client> Clients memoizados por device id ("host:port"). */
    protected array $clients = [];

    protected HandlerStack $handlerStack;

    protected string $username;

    protected string $password;

    // Tipos de verificación
    const VERIFICACION_TIPO = [
        '0' => 'Facial',
        '1' => 'Huella',
        '2' => 'Tarjeta/QR',
        '3' => 'Contraseña',
        '4' => 'Combinada',
    ];

    const ENTRADA_SALIDA_TIPO = [
        '0' => 'Entrada',
        '1' => 'Salida',
    ];

    // groupId del Person Group de Hikvision (ya creado en el dispositivo) asignado según el id_perfil del usuario
    const GROUP_ID_POR_PERFIL = [
        16 => 8, // Students
        1 => 2, // Admin. Dept.
        2 => 2,
        4 => 2,
        5 => 2,
        7 => 2,
        8 => 2,
        9 => 2,
        11 => 2,
        12 => 2,
        15 => 2,
        18 => 2,
        19 => 2,
        21 => 2,
        22 => 2,
        24 => 2,
        25 => 2,
        26 => 2,
        29 => 2,
        30 => 2,
        31 => 2,
        33 => 2,
        34 => 2,
        3 => 9, // Teachers
        13 => 9,
        14 => 9,
        20 => 9,
        10 => 10, // Workers
        23 => 10,
        27 => 10,
        32 => 10,
    ];

    /**
     * Obtiene el groupId del Person Group de Hikvision para un id_perfil dado.
     */
    protected function obtenerGroupIdPorPerfil(int $idPerfil): ?int
    {
        return self::GROUP_ID_POR_PERFIL[$idPerfil] ?? null;
    }

    /**
     * Construye la contraseña de asistencia a partir de los últimos 4 dígitos
     * del documento del usuario (el dispositivo acepta entre 4 y 8 caracteres).
     * Si el usuario no tiene documento registrado, se genera una contraseña
     * aleatoria de 4 dígitos para no bloquear el registro.
     */
    protected function construirPasswordAsistencia(?string $documento): string
    {
        if (!empty($documento)) {
            return substr($documento, -4);
        }

        return (string) random_int(1000, 9999);
    }

    public function __construct()
    {
        $this->username = config('services.hikvision.username');
        $this->password = config('services.hikvision.password');
        $this->devices = $this->resolverDispositivos();

        $this->handlerStack = HandlerStack::create();
        $this->handlerStack->push($this->retryMiddleware(3, 1000)); // 3 intentos
    }

    /**
     * Resuelve la lista de terminales configurados: el dispositivo "principal"
     * (HIKVISION_HOST/PORT/PROTOCOL, como hoy) más los adicionales declarados
     * en HIKVISION_HOSTS (formato "Nombre@host[:port],Nombre2@host2[:port2]"
     * — el nombre es opcional, si se omite cae al propio id "host:port"; el
     * puerto opcional cae al HIKVISION_PORT). Si no hay adicionales, el
     * resultado es exactamente el dispositivo único de siempre.
     *
     * El nombre "de fábrica" resuelto aquí es solo el fallback antes de que
     * alguien nombre el terminal desde el frontend (devicesInfo() prioriza
     * el nombre guardado en hikvision_dispositivos sobre este).
     *
     * @return array<int, array{id: string, protocol: string, host: string, port: mixed, name: string}>
     */
    private function resolverDispositivos(): array
    {
        $protocol = config('services.hikvision.protocol');
        $puertoDefault = config('services.hikvision.port');

        $lista = [$this->normalizarDispositivo(
            $protocol,
            config('services.hikvision.host'),
            $puertoDefault
        )];

        foreach (explode(',', (string) config('services.hikvision.extra_hosts')) as $entrada) {
            $entrada = trim($entrada);

            if ($entrada === '') {
                continue;
            }

            [$nombre, $entrada] = str_contains($entrada, '@')
                ? explode('@', $entrada, 2)
                : [null, $entrada];

            [$host, $puerto] = array_pad(explode(':', trim($entrada), 2), 2, null);
            $lista[] = $this->normalizarDispositivo($protocol, $host, $puerto ?? $puertoDefault, $nombre ? trim($nombre) : null);
        }

        return collect($lista)->unique('id')->values()->all();
    }

    private function normalizarDispositivo(string $protocol, string $host, $port, ?string $name = null): array
    {
        $id = "{$host}:{$port}";

        return ['id' => $id, 'protocol' => $protocol, 'host' => $host, 'port' => $port, 'name' => $name ?: $id];
    }

    /**
     * Construye (y memoiza) el Client de Guzzle para un terminal dado.
     */
    protected function client(string $deviceId): Client
    {
        if (!isset($this->clients[$deviceId])) {
            $device = collect($this->devices)->firstWhere('id', $deviceId);

            if (!$device) {
                throw new \InvalidArgumentException("Dispositivo Hikvision desconocido: {$deviceId}");
            }

            $this->clients[$deviceId] = new Client([
                'base_uri' => "{$device['protocol']}://{$device['host']}:{$device['port']}",
                'auth' => [$this->username, $this->password, 'digest'],
                'verify' => env('HIKVISION_VERIFY_SSL', false),
                'timeout' => 30,
                'handler' => $this->handlerStack,
            ]);
        }

        return $this->clients[$deviceId];
    }

    protected function primaryDeviceId(): string
    {
        return $this->devices[0]['id'];
    }

    /**
     * Ids de todos los terminales configurados (formato "host:port").
     */
    public function deviceIds(): array
    {
        return array_column($this->devices, 'id');
    }

    /**
     * Hosts (IPs) de todos los terminales configurados en .env (HIKVISION_HOST +
     * HIKVISION_HOSTS), sin I/O extra — usado para validar el origen de las
     * peticiones entrantes en /pushNotification (ver RestrictToHikvisionDevices).
     */
    public function deviceHosts(): array
    {
        return array_column($this->devices, 'host');
    }

    /**
     * Info legible de cada terminal configurado (id, nombre, host, puerto),
     * para un selector de terminal que muestre nombre en vez de solo IP:puerto.
     * El nombre guardado desde el frontend (tabla hikvision_dispositivos) tiene
     * prioridad sobre el de .env; si ninguno existe, cae al id ("host:puerto").
     *
     * @return array<int, array{id: string, name: string, host: string, port: mixed, isConnected: bool}>
     */
    public function devicesInfo(): array
    {
        $nombresGuardados = HikvisionDispositivo::whereIn('device_id', $this->deviceIds())
            ->pluck('nombre', 'device_id');

        $conexion = $this->testConnection()['data'];

        return array_map(
            fn ($device) => [
                'id' => $device['id'],
                'name' => $nombresGuardados[$device['id']] ?? $device['name'],
                'host' => $device['host'],
                'port' => $device['port'],
                'isConnected' => $conexion[$device['id']]['isConnected'] ?? false,
            ],
            $this->devices
        );
    }

    /**
     * Guarda/actualiza el nombre legible de un terminal ya configurado, para
     * que el frontend pueda editarlo desde la UI en vez de depender de .env.
     */
    public function guardarNombreDispositivo(string $deviceId, string $nombre): array
    {
        if (!in_array($deviceId, $this->deviceIds(), true)) {
            return ['error' => true, 'message' => "Dispositivo desconocido: {$deviceId}", 'data' => []];
        }

        $dispositivo = HikvisionDispositivo::updateOrCreate(
            ['device_id' => $deviceId],
            ['nombre' => $nombre]
        );

        return ['error' => false, 'message' => 'Nombre actualizado correctamente', 'data' => $dispositivo->toArray()];
    }

    /**
     * Ejecuta $operacion contra TODOS los terminales configurados y agrega el
     * resultado por dispositivo. Un empleado debe poder marcar en cualquier
     * puerta, así que el error global solo se marca si falló en TODOS; el
     * detalle por terminal queda expuesto en 'devices' para diagnosticar un
     * terminal específico sin tumbar la operación completa.
     *
     * @param  callable(Client $client, string $deviceId): array{error: bool, message?: string, data?: mixed}  $operacion
     */
    private function fanOut(callable $operacion): array
    {
        $porDispositivo = [];

        foreach ($this->devices as $device) {
            try {
                $porDispositivo[$device['id']] = $operacion($this->client($device['id']), $device['id']);
            } catch (\Throwable $e) {
                $porDispositivo[$device['id']] = ['error' => true, 'message' => $this->extraerMensajeError($e)];
            }
        }

        $huboExito = collect($porDispositivo)->contains(fn ($r) => empty($r['error']));

        return [
            'error' => !$huboExito,
            'message' => $huboExito ? 'Proceso completado' : 'Falló en todos los dispositivos',
            'devices' => $porDispositivo,
        ];
    }

    /**
     * Extrae un mensaje de error legible de una excepción de Guzzle: el cuerpo
     * de la respuesta del dispositivo si está disponible, o el mensaje de la
     * excepción como último recurso.
     */
    private function extraerMensajeError(\Throwable $e): string
    {
        if (method_exists($e, 'getResponse') && $e->getResponse()) {
            return $e->getResponse()->getBody()->getContents();
        }

        return $e->getMessage();
    }

    /**
     * Traducir método de acceso a formato legible
     */
    protected function translateAccessMethod($method)
    {
        $methods = [
            'FACIAL' => 'Reconocimiento Facial',
            'FINGERPRINT' => 'Huella Dactilar',
            'CARD' => 'Tarjeta/QR',
            'PASSWORD' => 'Contraseña',
            'QR' => 'Código QR',
            'FACE' => 'Facial',
            'FP' => 'Huella',
            'IC' => 'Tarjeta IC',
            'ID' => 'ID Card',
            'TEMP' => 'Temperatura',
            'FACE_FP' => 'Facial + Huella',
            'FACE_QR' => 'Facial + QR',
        ];

        foreach ($methods as $key => $value) {
            if (strpos($method, $key) !== false) {
                return $value;
            }
        }

        return $method;
    }

    /**
     * Middleware para reiniciar la solicitud en caso de fallos
     *
     * @param  mixed  $maxRetries
     * @param  mixed  $delay
     * @return callable
     */
    private function retryMiddleware($maxRetries = 3, $delay = 1000)
    {
        return Middleware::retry(
            function (
                $retries,
                $request,
                $response = null,
                $exception = null
            ) use ($maxRetries) {

                if ($retries >= $maxRetries) {
                    return false;
                }

                // Los endpoints de captura (huella/rostro) son polling bloqueante dentro de un
                // mismo request: si el dispositivo no responde, reintentar aquí solo triplica la
                // espera y retrasa que una cancelación (ver cancelarRegistroRostro) surta efecto.
                if (str_contains($request->getUri()->getPath(), 'Capture')) {
                    return false;
                }

                if ($exception instanceof RequestException) {
                    return true;
                }

                if ($response && $response->getStatusCode() >= 500) {
                    return true;
                }

                return false;
            },
            function ($retries) use ($delay) {
                Log::warning("Intento de conexion: #{$retries} a Hikvision");

                return $delay * $retries;
            }
        );
    }

    /**
     * Summary of parseXmlResponse
     *
     * @param  mixed  $xmlContent
     */
    protected function parseXmlResponse($xmlContent)
    {
        try {
            $xml = simplexml_load_string($xmlContent);

            return json_decode(json_encode($xml), true);
        } catch (\Exception $e) {
            Log::error('Error parseando XML: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Health check de conectividad hacia CADA terminal configurado (útil para
     * diagnosticar cuál falla, no solo si "algo" falla).
     *
     * @return array{isConnected: bool, data: array<string, array{isConnected: bool, data: mixed}>}
     */
    public function testConnection()
    {
        $porDispositivo = [];

        foreach ($this->devices as $device) {
            try {
                Log::info("Probando conexión con dispositivo: {$device['id']}");

                $response = $this->client($device['id'])->get('/ISAPI/System/capabilities?format=json');
                $body = $response->getBody()->getContents();
                $data = $this->parseXmlResponse($body);
                $isConnected = $response->getStatusCode() === 200;

                Log::info('Conexión exitosa', ['device' => $device['id'], 'status' => $response->getStatusCode()]);

                $porDispositivo[$device['id']] = ['isConnected' => $isConnected, 'data' => $data];
            } catch (GuzzleException $e) {
                Log::error('Fallo en conexión con dispositivo', [
                    'error' => $e->getMessage(),
                    'device' => $device['id'],
                ]);

                $porDispositivo[$device['id']] = ['isConnected' => false, 'data' => null];
            }
        }

        $conectado = collect($porDispositivo)->contains(fn ($r) => $r['isConnected']);

        return ['isConnected' => $conectado, 'data' => $porDispositivo];
    }

    /**
     * Obtiene la configuración de los hosts HTTP a los que cada dispositivo envía notificaciones de eventos.
     */
    public function obtenerHttpHosts()
    {
        $porDispositivo = [];

        foreach ($this->devices as $device) {
            try {
                $response = $this->client($device['id'])->get('/ISAPI/Event/notification/httpHosts?format=json');

                $porDispositivo[$device['id']] = [
                    'error' => false,
                    'data' => $this->parseXmlResponse($response->getBody()->getContents()),
                ];
            } catch (GuzzleException $e) {
                Log::error('Error al obtener httpHosts', ['error' => $e->getMessage(), 'device' => $device['id']]);

                $porDispositivo[$device['id']] = ['error' => true, 'data' => null];
            }
        }

        $huboExito = collect($porDispositivo)->contains(fn ($r) => !$r['error']);

        return [
            'error' => !$huboExito,
            'message' => $huboExito ? null : 'No se pudo obtener la configuración de httpHosts de ningún dispositivo',
            'data' => $porDispositivo,
        ];
    }

    /**
     * Obtener eventos de acceso (facial, huella, QR)
     *
     * @return array['error', 'message', 'data']
     */
    public function getAccessEvents(string $deviceId, $startTime = null, $endTime = null, $pageSize = 100)
    {
        try {
            $query = [
                'pageSize' => $pageSize,
                'orderBy' => 'descending',
            ];

            if ($startTime) {
                $query['startTime'] = $startTime;
            }
            if ($endTime) {
                $query['endTime'] = $endTime;
            }

            // Ruta ISAPI para eventos de acceso en dispositivos de control de acceso
            $response = $this->client($deviceId)->get('/ISAPI/AccessControl/AccessEvent', [
                'query' => $query,
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents());

            return [
                'error' => false,
                'message' => 'Acesso de eventos',
                'data' => $data,
            ];
        } catch (GuzzleException $e) {
            Log::error('Error obteniendo eventos de acceso: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => 'Error al obtener eventos',
                'data' => null,
            ];
        }
    }

    /**
     * OBTENER ASISTENCIA DE UN EMPLEADO ESPECIFICO...
     * getEmployeeAttendance
     *
     * @return array['error', 'message', 'data']
     */
    public function obtenerAsistenciaEmpleado($id_empleado, $start_date = null, $end_date = null)
    {
        try {
            $inicio = $start_date ? date('c', strtotime($start_date)) : date('c', strtotime('-30 days'));
            $final = $end_date ? date('c', strtotime($end_date)) : date('c');

            $asistencia = [];
            $nombreUsuario = null;

            foreach ($this->devices as $device) {
                $evento = $this->getAccessEvents($device['id'], $inicio, $final, 500);

                if ($evento['error'] || ! isset($evento['data']['AccessLogSet']['AccessLog'])) {
                    continue;
                }

                $logs = (array) $evento['data']['AccessLogSet']['AccessLog'];

                foreach ($logs as $log) {
                    if (isset($log['EmployeeNo']) && $log['EmployeeNo'] == $id_empleado) {
                        $asistencia[] = [
                            'employee_id' => $log['EmployeeNo'],
                            'name' => $log['EmployeeName'] ?? null,
                            'timestamp' => $log['AccessTime'],
                            'method' => $this->translateAccessMethod($log['AccessMethod'] ?? 'Desconocido'),
                            'door' => $log['DoorName'] ?? null,
                            'status' => $log['AccessStatus'] ?? 'Desconocido',
                            'device' => $device['id'],
                            'raw_data' => $log,
                        ];
                    }
                }

                $nombreUsuario ??= $logs[0]['EmployeeName'] ?? null;
            }

            // Se unen los eventos de todos los terminales, más recientes primero.
            usort($asistencia, fn ($a, $b) => strcmp($b['timestamp'], $a['timestamp']));

            return [
                'error' => false,
                'message' => 'Asistencia obtenida del usuario: ' . ($nombreUsuario ?? 'Empleado'),
                'data' => $asistencia,
            ];
        } catch (\Exception $e) {
            Log::error('Error obteniendo la asistencia del empleado: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => 'Problemas obteniendo la asistencia del empleado',
                'data' => null,
            ];
        }
    }

    /**
     * Agregar a un empleado
     *
    payload = {
        "UserInfo": {
            "employeeNo": "1001",          # ID único, máx 99999999
            "name": "Juan Pérez",          # máx 32 caracteres
            "userType": "normal",
            "doorRight": "1",
            "RightPlan": [
                {"doorNo": 1, "planTemplateNo": "1"}
            ],
            "Valid": {
                "enable": True,
                "beginTime": "2024-01-01T00:00:00",
                "endTime":   "2035-12-31T23:59:59",
                "timeType":  "local"
            }
        }
    }
     */
    public function registrarEmpleado(array $datos_empleado)
    {
        $payload = [
            'UserInfo' => [
                'employeeNo' => (string) $datos_empleado['id_user'],
                'name' => substr(preg_replace('/[^A-Za-z0-9 ]/', '', $datos_empleado['nombre']), 0, 30),
                'userType' => 'normal',
                'password' => $this->construirPasswordAsistencia((string) $datos_empleado['documento']),
                'gender' => 'male',
                'localUIRight' => false,
                'doorRight' => '1',
                'RightPlan' => [
                    ['doorNo' => 1, 'planTemplateNo' => '1'],
                ],
                'Valid' => [
                    'enable' => true,
                    // beginTime fijo (no now()): el dispositivo recalcula este campo con
                    // su propio reloj si se le manda "now" del servidor, y el desfase de
                    // zona horaria lo deja adelantado respecto a su hora local, provocando
                    // que rechace al usuario como "permiso expirado".
                    'beginTime' => '2024-01-01T00:00:00',
                    'endTime' => '2035-12-31T23:59:59',
                    'timeType' => 'local',
                ],
            ],
        ];

        $groupId = $this->obtenerGroupIdPorPerfil((int) $datos_empleado['perfil']);

        if ($groupId !== null) {
            $payload['UserInfo']['groupId'] = $groupId;
        }

        $resultado = $this->fanOut(function (Client $client) use ($payload, $datos_empleado) {
            try {
                $response = $client->post(
                    '/ISAPI/AccessControl/UserInfo/Record?format=json',
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ],
                        'json' => $payload,
                    ],
                );

                if ($response->getStatusCode() === 201 || $response->getStatusCode() === 200) {
                    // POST .../UserInfo/Record ignora el Valid.beginTime que se le manda y el
                    // equipo le pone su propio "ahora" al crear, dejando al usuario recién
                    // creado con permiso "expirado" hasta esa hora. PUT .../Modify sí respeta
                    // el Valid enviado, así que se fuerza justo después de crear.
                    $client->put('/ISAPI/AccessControl/UserInfo/Modify?format=json', [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ],
                        'json' => $payload,
                    ]);

                    return ['error' => false, 'message' => 'Usuario creado con exito'];
                }

                return ['error' => true, 'message' => 'Error al registrar el usuario'];
            } catch (GuzzleException $e) {
                // El dispositivo ya tiene a este employeeNo (típico al reintentar un
                // registrarEmpleado que ya había tenido éxito en este terminal pero
                // falló en otro): el objetivo real ("que esté en el dispositivo") ya
                // se cumple, así que se trata como éxito en vez de bloquear el reintento.
                $bodyDecoded = json_decode($this->extraerMensajeError($e), true);

                if (($bodyDecoded['subStatusCode'] ?? null) === 'employeeNoAlreadyExist') {
                    return ['error' => false, 'message' => 'Ya estaba registrado en el dispositivo'];
                }

                Log::error('Error registrando al empleado: ' . $e->getMessage(), [
                    'payload' => $payload,
                ]);

                return ['error' => true, 'message' => $this->extraerMensajeError($e)];
            }
        });

        return [
            'error' => $resultado['error'],
            'message' => $resultado['error'] ? 'Error al registrar el usuario' : 'Usuario creado con exito',
            'id_user' => $datos_empleado['id_user'],
            'devices' => $resultado['devices'],
        ];
    }

    /**
     * Summary of registrarEmpleadosMasivo
     *
     * @return array{data: array, error: bool, message: string}
     */
    /**
     * Registra el lote en TODOS los terminales, uno tras otro (no paralelo entre
     * terminales: es un alta administrativa por lote, no un flujo en vivo, así
     * que sumar tiempo entre 2-5 dispositivos es aceptable). Un usuario cuenta
     * como éxito global si se registró en al menos un terminal.
     */
    public function registrarEmpleadosMasivo(array $usuarios, int $concurrencias = 5)
    {
        $porDispositivo = [];

        foreach ($this->devices as $device) {
            $porDispositivo[$device['id']] = $this->ejecutarPoolMasivo($usuarios, $this->client($device['id']), $concurrencias);
        }

        $exitosos = [];
        $fallidos = [];

        foreach ($usuarios as $usuario) {
            $id = $usuario['id_user'];
            $dispositivosConExito = 0;

            foreach ($porDispositivo as $resultado) {
                if (in_array($id, array_column($resultado['success'], 'id_user'))) {
                    $dispositivosConExito++;
                }
            }

            if ($dispositivosConExito > 0) {
                $exitosos[] = [
                    'id_user' => $id,
                    'message' => "Registrado en {$dispositivosConExito}/" . count($this->devices) . ' dispositivos',
                ];
            } else {
                $fallidos[] = ['id_user' => $id, 'message' => 'Falló en todos los dispositivos'];
            }
        }

        return [
            'error' => false,
            'message' => 'Proceso completado',
            'data' => ['success' => $exitosos, 'error' => $fallidos, 'porDispositivo' => $porDispositivo],
        ];
    }

    /**
     * Cuerpo original de registrarEmpleadosMasivo, parametrizado por Client
     * para poder correrlo una vez por cada terminal.
     *
     * @return array{success: array, error: array}
     */
    private function ejecutarPoolMasivo(array $usuarios, Client $client, int $concurrencias): array
    {
        $result = [
            'success' => [],
            'error' => [],
        ];

        $requests = function () use ($usuarios, $client) {
            foreach ($usuarios as $usuario) {
                // Estructura JSON que espera Hikvision
                $data = [
                    'UserInfo' => [
                        'employeeNo'    => (string) $usuario['id_user'],
                        'name'          => substr(preg_replace('/[^A-Za-z0-9 ]/', '', $usuario['nombre']), 0, 30),
                        'userType'      => 'normal',
                        'password'      => $this->construirPasswordAsistencia((string) $usuario['documento']),
                        'gender'        => 'male',
                        'localUIRight'  => false, // En JSON usa booleanos reales, no strings
                        'doorRight'     => '1',
                        'RightPlan'     => [
                            ['doorNo' => 1, 'planTemplateNo' => '1'],
                        ],
                        'Valid' => [
                            'enable'    => true,
                            // beginTime fijo, mismo motivo que en registrarEmpleado.
                            'beginTime' => '2024-01-01T00:00:00',
                            'endTime'   => '2035-12-31T23:59:59',
                            'timeType'  => 'local',
                        ],
                    ],
                ];

                $groupId = $this->obtenerGroupIdPorPerfil((int) $usuario['perfil']);

                if ($groupId !== null) {
                    $data['UserInfo']['groupId'] = $groupId;
                }

                yield function () use ($data, $client) {
                    return $client->postAsync(
                        '/ISAPI/AccessControl/UserInfo/Record?format=json', // Forzamos el formato en la URL
                        [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Accept'       => 'application/json',
                            ],
                            'json' => $data, // Guzzle codifica esto automáticamente
                        ]
                    );
                };
            }
        };

        $pool = new Pool($client, $requests(), [
            'concurrency' => $concurrencias,

            'fulfilled' => function ($response, $index) use (&$result, $usuarios, $client) {
                $usuario = $usuarios[$index];

                // Mismo motivo que en registrarEmpleado: el POST de creación ignora el
                // Valid.beginTime enviado, así que se fuerza con un Modify justo después.
                $client->put('/ISAPI/AccessControl/UserInfo/Modify?format=json', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => [
                        'UserInfo' => [
                            'employeeNo' => (string) $usuario['id_user'],
                            'name' => substr(preg_replace('/[^A-Za-z0-9 ]/', '', $usuario['nombre']), 0, 30),
                            'userType' => 'normal',
                            'Valid' => [
                                'enable' => true,
                                'beginTime' => '2024-01-01T00:00:00',
                                'endTime' => '2035-12-31T23:59:59',
                                'timeType' => 'local',
                            ],
                        ],
                    ],
                ]);

                $result['success'][] = [
                    'id_user' => $usuario['id_user'],
                    'message' => 'Registrado Correctamente',
                ];
            },

            'rejected' => function ($reason, $index) use (&$result, $usuarios) {

                $usuario = $usuarios[$index];

                $response = method_exists($reason, 'getResponse')
                    ? $reason->getResponse()
                    : null;

                $body = $response ? $response->getBody()->getContents() : null;
                $bodyDecoded = $body ? json_decode($body, true) : null;

                // El dispositivo ya tiene a este employeeNo (registrado por fuera de esta app,
                // o por un intento previo a que existiera el flag asistenciaRegistrada).
                // El objetivo real ("que esté en el dispositivo") ya se cumple, así que se trata como éxito.
                if (($bodyDecoded['subStatusCode'] ?? null) === 'employeeNoAlreadyExist') {
                    $result['success'][] = [
                        'id_user' => $usuario['id_user'],
                        'message' => 'Ya estaba registrado en el dispositivo',
                    ];

                    return;
                }

                Log::error('Hikvision FULL ERROR', [
                    'usuario' => $usuario,
                    'response' => $body
                ]);

                $result['error'][] = [
                    'id_user' => $usuario['id_user'],
                    'message' => $body ?? $reason->getMessage(),
                ];
            },
        ]);

        // Se ejecuta aquí el pool
        $promise = $pool->promise();
        $promise->wait();

        return $result;
    }

    /**
     * Summary of obtenerEmpleadosRegistrados
     * @param int $pageSize
     * @param int $pagina
     * @return array{data: array, error: bool, message: string|array{data: null, error: bool, message: string}}
     */
    /**
     * Une los empleados registrados de TODOS los terminales, deduplicados por
     * employeeNo (un mismo empleado suele existir en varios terminales por el
     * modelo fan-out).
     */
    public function obtenerEmpleadosRegistrados(int $pageSize = 30, int $pagina = 1)
    {
        $todosLosEmpleados = [];
        $vistos = [];

        foreach ($this->devices as $device) {
            try {
                $empleadosDispositivo = $this->obtenerEmpleadosRegistradosDeDispositivo($device['id']);
            } catch (\Exception $e) {
                Log::error('Error obteniendo empleados de un dispositivo: ' . $e->getMessage(), ['device' => $device['id']]);

                continue;
            }

            foreach ($empleadosDispositivo as $empleado) {
                $employeeNo = $empleado['employeeNo'] ?? null;

                if ($employeeNo !== null && isset($vistos[$employeeNo])) {
                    continue;
                }

                if (!empty($empleado['faceURL'])) {
                    $empleado['faceURL'] = $this->proxyImagenUrl($empleado['faceURL'], $device['id']);
                }

                $empleado['contrasenaVigente'] = $this->tieneContrasenaVigente($empleado);

                $todosLosEmpleados[] = $empleado;

                if ($employeeNo !== null) {
                    $vistos[$employeeNo] = true;
                }
            }
        }

        return [
            'error' => false,
            'message' => 'Usuarios registrados obtenidos',
            'data' => $todosLosEmpleados,
        ];
    }

    /**
     * Igual que obtenerEmpleadosRegistrados() pero de UN solo terminal, sin
     * fan-out ni dedupe (usado por el comando hikvision:wipe-all para previsualizar
     * antes de borrar un dispositivo específico).
     *
     * @return array{error: bool, message: string, data: array}
     */
    public function listarEmpleadosDeDispositivo(string $deviceId): array
    {
        if (!in_array($deviceId, $this->deviceIds(), true)) {
            return ['error' => true, 'message' => "Dispositivo desconocido: {$deviceId}", 'data' => []];
        }

        try {
            return [
                'error' => false,
                'message' => 'Usuarios registrados obtenidos',
                'data' => $this->obtenerEmpleadosRegistradosDeDispositivo($deviceId),
            ];
        } catch (\Exception $e) {
            Log::error('Error obteniendo empleados del dispositivo: ' . $e->getMessage(), ['device' => $deviceId]);

            return ['error' => true, 'message' => 'Error obteniendo a los usuarios registrados en hikvision', 'data' => []];
        }
    }

    /**
     * Trae el listado paginado completo de empleados de UN terminal específico
     * (sin proxy de imagen ni dedupe: eso lo resuelve el llamador según necesite
     * fan-out o un único dispositivo, como eliminarTodosLosUsuariosDelDispositivo).
     */
    private function obtenerEmpleadosRegistradosDeDispositivo(string $deviceId): array
    {
        $client = $this->client($deviceId);
        $todosLosEmpleados = [];
        $pageSize = 30;
        $offset = 0;

        do {
            $response = $client->post('/ISAPI/AccessControl/UserInfo/Search?format=json', [
                'json' => [
                    'UserInfoSearchCond' => [
                        'searchID' => '1',
                        'searchResultPosition' => $offset,
                        'maxResults' => $pageSize,
                    ],
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            $usuarios = $data['UserInfoSearch']['UserInfo'] ?? [];
            $totalMatch = $data['UserInfoSearch']['totalMatches'] ?? 0;

            $todosLosEmpleados = array_merge($todosLosEmpleados, $usuarios);

            $offset += $pageSize;
        } while ($offset < $totalMatch);

        return $todosLosEmpleados;
    }

    /**
     * Determina si el empleado tiene una contraseña de asistencia vigente: que tenga
     * un password asignado (no vacío) y que su cuenta esté habilitada y dentro del
     * rango Valid.beginTime/endTime reportado por el dispositivo.
     */
    private function tieneContrasenaVigente(array $empleado): bool
    {
        if (empty($empleado['password'])) {
            return false;
        }

        $valid = $empleado['Valid'] ?? [];

        if (empty($valid['enable'])) {
            return false;
        }

        try {
            $ahora = now();

            if (!empty($valid['beginTime']) && $ahora->lt(Carbon::parse($valid['beginTime']))) {
                return false;
            }

            if (!empty($valid['endTime']) && $ahora->gt(Carbon::parse($valid['endTime']))) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Descarga la imagen de un empleado desde el dispositivo Hikvision usando las
     * credenciales del servidor (digest auth ya configuradas en $this->client),
     * para que el frontend nunca necesite conocer la IP ni las credenciales del dispositivo.
     *
     * @param string $path Ruta relativa dentro del dispositivo (p. ej. "/LOCALS/pic/enrlFace/0/0000000001.jpg@WEB000000000002")
     * @param string|null $deviceId Terminal de origen de la imagen; por defecto el principal (cubre URLs viejas cacheadas en el navegador).
     * @return array{error: bool, message: string, data: array{contenido: string, contentType: string}|null}
     */
    public function obtenerImagenEmpleado(string $path, ?string $deviceId = null): array
    {
        $deviceId ??= $this->primaryDeviceId();

        try {
            $rutaRelativa = $this->extraerRutaRelativa($path, $deviceId);

            if (str_contains($rutaRelativa, '://')) {
                throw new \InvalidArgumentException('Ruta de imagen inválida');
            }

            if (!str_starts_with($rutaRelativa, '/')) {
                $rutaRelativa = '/' . $rutaRelativa;
            }

            $response = $this->client($deviceId)->get($rutaRelativa);

            return [
                'error' => false,
                'message' => 'Imagen obtenida',
                'data' => [
                    'contenido' => $response->getBody()->getContents(),
                    'contentType' => $response->getHeaderLine('Content-Type') ?: 'image/jpeg',
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Error obteniendo imagen de empleado: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'No se pudo obtener la imagen del empleado',
                'data' => null,
            ];
        }
    }

    /**
     * Convierte la URL absoluta del dispositivo (faceURL) en una ruta proxy propia
     * del backend (con el device de origen incluido), para que el frontend nunca
     * reciba la IP ni las credenciales del dispositivo.
     */
    private function proxyImagenUrl(string $faceUrl, string $deviceId): string
    {
        $rutaRelativa = $this->extraerRutaRelativa($faceUrl, $deviceId);

        return '/api/hikvision/image?path=' . urlencode($rutaRelativa) . '&deviceId=' . urlencode($deviceId);
    }

    /**
     * Elimina el protocolo + host del terminal indicado de una URL absoluta,
     * dejando solo la ruta relativa que se le puede pasar a $this->client() (que
     * ya trae el host configurado).
     *
     * Se compara por host vía parse_url() en vez de un prefijo de string exacto:
     * el propio dispositivo suele devolver su faceURL sin el puerto por defecto
     * (ej. "http://20.20.20.16/..." cuando el puerto es 80), mientras que
     * $baseUrl siempre lo incluye explícito ("http://20.20.20.16:80") — un
     * str_starts_with exacto nunca matchea en ese caso y la URL queda intacta
     * (con "://" adentro), lo que hacía fallar obtenerImagenEmpleado con
     * "Ruta de imagen inválida" en el segundo paso (proxy → dispositivo).
     */
    private function extraerRutaRelativa(string $path, string $deviceId): string
    {
        $device = collect($this->devices)->firstWhere('id', $deviceId);
        if (!$device) {
            return $path;
        }

        $partes = parse_url($path);
        if (!isset($partes['host']) || $partes['host'] !== $device['host']) {
            return $path;
        }

        $relativa = $partes['path'] ?? '/';
        if (isset($partes['query'])) {
            $relativa .= '?' . $partes['query'];
        }

        return $relativa;
    }

    /**
     * Summary of obtenerUnEmpleadoEspecifico
     * @param mixed $id_usuario
     * @return array{data: array<int|string>, error: bool, message: string|array{data: array{data: null, status: int}, error: bool, message: string}}
     */
    public function obtenerUnEmpleadoEspecifico($id_usuario, ?string $deviceId = null)
    {
        $deviceId ??= $this->primaryDeviceId();

        try {
            $response = $this->client($deviceId)->post('/ISAPI/AccessControl/UserInfo/Search?format=json', [
                'json' => [
                    'UserInfoSearchCond' => [
                        'searchID' => '1',
                        'searchResultPosition' => 0,
                        'maxResults' => 1,
                        'EmployeeNoList' => [
                            [
                                'employeeNo' => (string) $id_usuario
                            ]
                        ],
                    ]
                ],
            ]);

            $data = json_decode($response->getBody()->getContents());
            $status = $response->getStatusCode();

            return [
                'error' => false,
                'message' => 'Información obtenida',
                'data' => [$data],
            ];
        } catch (GuzzleException $e) {
            Log::error('Error al obtener la información del empleado: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => 'No se pudo obtener la información del empleado... ',
                'data' => ['data' => null],
            ];
        }
    }

    /**
     * Summary of obtenerEmpleadosRegistradosPorPerfil
     * @param array $usuarios
     * @return array{data: array, error: bool, message: string|array{data: mixed, error: bool, message: string}|array{data: null, error: bool, message: string}}
     */
    public function obtenerEmpleadosRegistradosPorPerfil(array $usuarios)
    {
        try {
            if (empty($usuarios)) {
                return [
                    'error' => true,
                    'message' => 'La lista de usuarios está vacía',
                    'data' => [],
                ];
            }

            // Construir lista en formato Hikvision
            $employeeList = array_map(function ($usuario) {
                return [
                    'employeeNo' => (string) $usuario['id_user']
                ];
            }, $usuarios);

            $groupIdPorEmployeeNo = [];
            foreach ($usuarios as $usuario) {
                $groupIdPorEmployeeNo[(string) $usuario['id_user']] = $this->obtenerGroupIdPorPerfil((int) $usuario['perfil']);
            }

            // Se busca en TODOS los terminales y se deduplica por employeeNo (un
            // mismo empleado suele existir en varios por el modelo fan-out).
            $vistos = [];
            $usuariosEncontrados = [];

            foreach ($this->devices as $device) {
                try {
                    $response = $this->client($device['id'])->post('/ISAPI/AccessControl/UserInfo/Search?format=json', [
                        'json' => [
                            'UserInfoSearchCond' => [
                                'searchID' => (string) now()->timestamp,
                                'searchResultPosition' => 0,
                                'maxResults' => count($usuarios), // importante
                                'EmployeeNoList' => $employeeList,
                            ],
                        ],
                    ]);

                    $data = json_decode($response->getBody()->getContents(), true);

                    foreach ($data['UserInfoSearch']['UserInfo'] ?? [] as $infoUsuario) {
                        $employeeNo = (string) ($infoUsuario['employeeNo'] ?? '');

                        if ($employeeNo !== '' && isset($vistos[$employeeNo])) {
                            continue;
                        }

                        $infoUsuario['groupId'] = $groupIdPorEmployeeNo[$employeeNo] ?? null;
                        $usuariosEncontrados[] = $infoUsuario;

                        if ($employeeNo !== '') {
                            $vistos[$employeeNo] = true;
                        }
                    }
                } catch (GuzzleException $e) {
                    Log::error('Error al obtener empleados por lista', [
                        'error' => $e->getMessage(),
                        'device' => $device['id'],
                        'usuarios' => $usuarios,
                    ]);
                }
            }

            return [
                'error' => false,
                'message' => 'Información obtenida correctamente',
                'data' => ['UserInfoSearch' => ['UserInfo' => $usuariosEncontrados]],
            ];
        } catch (\Throwable $e) {
            Log::error('Error al obtener empleados por lista', [
                'error' => $e->getMessage(),
                'usuarios' => $usuarios
            ]);

            return [
                'error' => true,
                'message' => 'No se pudo obtener la información de los empleados',
                'data' => null,
            ];
        }
    }

    /**
     * Summary of eliminarUsuariosRegistrados
     * @param array $usuarios
     * @return array{data: array, error: bool, message: string|array{data: mixed, error: bool, message: string}}
     */
    public function eliminarUsuariosRegistrados(array $usuarios){
        $listaEmpleados = [];

        foreach ($usuarios as $usuario) {
            // Usamos id_user como indicaste
            if (isset($usuario['id_user'])) {
                $listaEmpleados[] = [
                    'employeeNo' => (string)$usuario['id_user']
                ];
            }
        }

        if (empty($listaEmpleados)) {
            return ['error' => true, 'message' => 'No hay IDs de usuario para procesar'];
        }

        $body = [
            'UserInfoDelCond' => [
                'mode' => 'byEmployeeNo',
                'EmployeeNoList' => $listaEmpleados
            ],
        ];

        $resultado = $this->fanOut(function (Client $client) use ($body) {
            $response = $client->put('/ISAPI/AccessControl/UserInfo/Delete?format=json', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => $body
            ]);

            return ['error' => false, 'message' => 'OK', 'data' => json_decode($response->getBody()->getContents(), true)];
        });

        if ($resultado['error']) {
            Log::error('Error eliminando en Hikvision', ['devices' => $resultado['devices']]);
        }

        return [
            'error' => $resultado['error'],
            'message' => $resultado['error'] ? 'Error al borrar' : 'Usuarios borrados',
            'data' => $resultado['devices'],
        ];
    }

    /**
     * Elimina TODOS los empleados que el dispositivo reporta en este momento (vía
     * obtenerEmpleadosRegistrados), sin filtrar por nuestra BD. Incluye cuentas
     * manuales/admin que nunca pasaron por esta app. Acción destructiva e irreversible,
     * pensada para limpiar registros erróneos — no para uso recurrente desde la UI.
     */
    /**
     * Requiere $deviceId explícito a propósito: a diferencia del resto de
     * operaciones de escritura (fan-out a todos los terminales), un wipe-all
     * NUNCA debe hacerse implícitamente en todos los dispositivos — sería
     * demasiado fácil vaciar varias puertas por error.
     */
    public function eliminarTodosLosUsuariosDelDispositivo(string $deviceId): array
    {
        if (!in_array($deviceId, $this->deviceIds(), true)) {
            return ['error' => true, 'message' => "Dispositivo desconocido: {$deviceId}"];
        }

        try {
            $empleados = $this->obtenerEmpleadosRegistradosDeDispositivo($deviceId);
        } catch (\Exception $e) {
            Log::error('Error listando empleados del dispositivo: ' . $e->getMessage(), ['device' => $deviceId]);

            return ['error' => true, 'message' => 'No se pudo listar los empleados del dispositivo'];
        }

        if (empty($empleados)) {
            return ['error' => false, 'message' => 'El dispositivo no tiene usuarios registrados', 'data' => []];
        }

        $employeeNos = array_map(fn ($empleado) => (string) $empleado['employeeNo'], $empleados);
        $client = $this->client($deviceId);

        try {
            // El dispositivo limita el tamaño del lote de borrado, igual que en la búsqueda paginada.
            foreach (array_chunk($employeeNos, 30) as $lote) {
                $client->put('/ISAPI/AccessControl/UserInfo/Delete?format=json', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept'       => 'application/json',
                    ],
                    'json' => [
                        'UserInfoDelCond' => [
                            'mode' => 'byEmployeeNo',
                            'EmployeeNoList' => array_map(fn ($no) => ['employeeNo' => $no], $lote),
                        ],
                    ],
                ]);
            }

            return [
                'error' => false,
                'message' => 'Se eliminaron '.count($employeeNos).' usuarios del dispositivo',
                'data' => ['employeeNos' => $employeeNos],
            ];
        } catch (\Exception $e) {
            $errorMsg = $this->extraerMensajeError($e);

            Log::error('Error eliminando TODOS los usuarios en Hikvision: '.$errorMsg, ['device' => $deviceId]);

            return [
                'error' => true,
                'message' => 'Error al borrar: '.$errorMsg,
            ];
        }
    }

    /**
     * Summary of desactivarUsuario
     * @param array $usuario
     * @param int $estado
     * @return array{data: array, error: bool, message: string|array{data: mixed, error: bool, message: string}}
     */
    public function desactivarUsuario(array $usuario, int $estado){
        $enable = ($estado == 1) ? true : false;
        $message = ($estado == 1) ? "Usuario Activado" : "Usuario Desactivado";

        if(!isset($usuario['id_user'])){
            return [
                'error' => true,
                'message' => "El usuario debe contener información, mínimo el id_user",
                'data' => [],
            ];
        }

        $body = [
            'UserInfo' => [
                'employeeNo' => (string) $usuario['id_user'],
                'name' => substr($usuario['nombre'], 0, 30),
                'userType' => 'normal',
                'Valid' => [
                    'enable' => $enable, // Activamos/desactivamos el acceso
                    // beginTime fijo (no now()): el dispositivo recalcula este campo con
                    // su propio reloj si se le manda "now" del servidor, y el desfase de
                    // zona horaria lo deja adelantado respecto a su hora local, provocando
                    // que rechace al usuario como "permiso expirado" justo al reactivarlo.
                    'beginTime' => '2024-01-01T00:00:00',
                    'endTime' => '2035-12-31T23:59:59', // Fecha lejana
                    'timeType' => 'local',
                ],
            ],
        ];

        $resultado = $this->fanOut(function (Client $client) use ($body) {
            // CAMBIO: Endpoint /Modify y método PUT
            $response = $client->put("/ISAPI/AccessControl/UserInfo/Modify?format=json", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => $body,
            ]);

            return ['error' => false, 'message' => 'OK', 'data' => json_decode($response->getBody()->getContents())];
        });

        if ($resultado['error']) {
            Log::error('Error al desactivar el usuario', ['devices' => $resultado['devices']]);
        }

        return [
            'error' => $resultado['error'],
            'message' => $resultado['error'] ? 'Error desactivando al usuario' : $message,
            'data' => $resultado['devices'],
        ];
    }

    /**
     * Actualiza la información de un usuario en el dispositivo Hikvision vía ISAPI.
     *
     * @param array $datos_usuario Debe contener al menos 'employeeNo' y los campos a actualizar.
     * @return array
     */
    public function actualizarInformacionUsuario(array $datos_usuario)
    {
        try {
            if (!isset($datos_usuario['id_user'])) {
                return [
                    'error' => true,
                    'message' => 'El id_user es obligatorio para actualizar el usuario.',
                    'data' => null,
                ];
            }

            // Mapeamos id_user a employeeNo para el ISAPI
            $payload_user = $datos_usuario;
            $payload_user['employeeNo'] = (string) $datos_usuario['id_user'];
            unset($payload_user['id_user']);

            // Limpieza de nombre si viene en el payload (máximo 32 caracteres según ISAPI)
            if (isset($payload_user['name'])) {
                $payload_user['name'] = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $payload_user['name']), 0, 32);
            }

            // Confirmado contra el dispositivo real: si el PUT no incluye "Valid",
            // el dispositivo recalcula beginTime usando su reloj interno con un desfase
            // de zona horaria, dejándolo adelantado respecto a su propia hora local y
            // provocando que rechace al usuario como "permiso expirado" (la cuenta
            // aún no es válida según su propio reloj). Por eso siempre se manda un
            // Valid explícito y estable, salvo que el caller ya haya enviado uno.
            if (!isset($payload_user['Valid'])) {
                $payload_user['Valid'] = [
                    'enable' => true,
                    'beginTime' => '2024-01-01T00:00:00',
                    'endTime' => '2035-12-31T23:59:59',
                    'timeType' => 'local',
                ];
            }

            $body = [
                'UserInfo' => $payload_user
            ];

            $resultado = $this->fanOut(function (Client $client) use ($body) {
                try {
                    $response = $client->put('/ISAPI/AccessControl/UserInfo/Modify?format=json', [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Accept'       => 'application/json',
                        ],
                        'json' => $body,
                    ]);

                    return ['error' => false, 'message' => 'OK', 'data' => json_decode($response->getBody()->getContents(), true)];
                } catch (\Exception $e) {
                    $errorMsg = $this->extraerMensajeError($e);
                    Log::error("Error actualizando usuario en Hikvision: " . $errorMsg);

                    return ['error' => true, 'message' => $errorMsg, 'data' => null];
                }
            });

            return [
                'error' => $resultado['error'],
                'message' => $resultado['error']
                    ? 'No se pudo actualizar la información del usuario'
                    : 'Información del usuario actualizada correctamente',
                'data' => $resultado['devices'],
            ];
        } catch (\Exception $e) {
            Log::error('Error actualizando usuario en Hikvision: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => 'No se pudo actualizar la información del usuario',
                'data' => null,
            ];
        }
    }
    
    /**
     * Registra (o sobrescribe) la contraseña de asistencia de un empleado ya existente
     * en el dispositivo. Si el empleado tiene documento, la contraseña son sus últimos
     * 4 dígitos; si no tiene documento registrado, se le asigna una contraseña aleatoria
     * de 4 dígitos.
     *
     * @return array{error: bool, message: string, data: array}
     */
    public function registrarContrasenaEmpleado(string $employeeNo, ?string $documento): array
    {
        if ($this->obtenerInfoUsuarioDispositivo($employeeNo, $this->primaryDeviceId()) === null) {
            return [
                'error' => true,
                'message' => 'El empleado no está registrado en el dispositivo',
                'data' => [],
            ];
        }

        $origen = !empty($documento) ? 'documento' : 'aleatorio';
        $password = $this->construirPasswordAsistencia($documento);

        $resultado = $this->actualizarInformacionUsuario([
            'id_user' => $employeeNo,
            'password' => $password,
        ]);

        if ($resultado['error']) {
            return [
                'error' => true,
                'message' => 'No se pudo registrar la contraseña en el dispositivo: ' . $resultado['message'],
                'data' => [],
            ];
        }

        return [
            'error' => false,
            'message' => $origen === 'documento'
                ? 'Contraseña asignada a partir de los últimos 4 dígitos del documento'
                : 'El usuario no tiene documento registrado: se asignó una contraseña aleatoria',
            'data' => ['password' => $password, 'origen' => $origen],
        ];
    }

    /**
     * Registra una huella para un empleado ya existente en el dispositivo, en dos pasos:
     * 1) CaptureFingerPrint: bloquea esperando a que el usuario coloque el dedo en el
     *    lector y devuelve el template crudo (fingerData) sin saber a quién pertenece.
     * 2) FingerPrintDownload: vincula ese fingerData capturado al employeeNo + slot
     *    indicados, que es lo que realmente "guarda" la huella en el perfil del empleado.
     *
     * El dispositivo responde "OK" en el paso 2 incluso si el employeeNo no existe,
     * sin persistir nada, así que se valida antes de pedirle al usuario que coloque el dedo.
     *
     * @return array{error: bool, message: string, data: array}
     */
    public function registrarHuellaEmpleado(string $employeeNo, int $fingerPrintID = 1, ?string $deviceId = null): array
    {
        $deviceId ??= $this->primaryDeviceId();

        if (!$this->empleadoExisteEnDispositivo($employeeNo, $deviceId)) {
            return [
                'error' => true,
                'message' => 'El empleado no está registrado en el dispositivo',
                'data' => [],
            ];
        }

        $captura = $this->capturarHuella($deviceId, $fingerPrintID);

        if ($captura['error']) {
            return $captura;
        }

        return $this->vincularHuellaEmpleado($employeeNo, $fingerPrintID, $captura['data']['fingerData']);
    }

    private function empleadoExisteEnDispositivo(string $employeeNo, string $deviceId): bool
    {
        return $this->obtenerInfoUsuarioDispositivo($employeeNo, $deviceId) !== null;
    }

    /**
     * Obtiene el UserInfo del empleado tal como lo reporta el dispositivo indicado
     * (incluye campos como 'password' que no se exponen a través de los demás
     * métodos públicos), o null si el empleado no existe en ese dispositivo.
     */
    private function obtenerInfoUsuarioDispositivo(string $employeeNo, string $deviceId): ?object
    {
        $resultado = $this->obtenerUnEmpleadoEspecifico($employeeNo, $deviceId);

        if ($resultado['error']) {
            return null;
        }

        $busqueda = $resultado['data'][0]->UserInfoSearch ?? null;

        if (!$busqueda || ($busqueda->responseStatusStrg ?? null) !== 'OK' || empty($busqueda->UserInfo)) {
            return null;
        }

        return $busqueda->UserInfo[0];
    }

    /**
     * Paso 1: captura el template de huella desde el sensor. Llamada bloqueante: el
     * dispositivo espera a que el usuario coloque el dedo y solo responde cuando
     * captura la huella, falla o agota su propio timeout interno de espera.
     *
     * @return array{error: bool, message: string, data: array}
     */
    private function capturarHuella(string $deviceId, int $fingerNo): array
    {
        try {
            // Este submódulo del dispositivo ignora "?format=json" y solo acepta/devuelve XML,
            // a diferencia del resto de endpoints de AccessControl (confirmado contra el equipo real).
            $xml = '<?xml version="1.0" encoding="UTF-8"?><CaptureFingerPrintCond><fingerNo>'
                . $fingerNo
                . '</fingerNo></CaptureFingerPrintCond>';

            $response = $this->client($deviceId)->post('/ISAPI/AccessControl/CaptureFingerPrint', [
                'headers' => [
                    'Content-Type' => 'application/xml',
                ],
                'body' => $xml,
                'timeout' => 25, // margen para que el usuario coloque el dedo
            ]);

            $body = $response->getBody()->getContents();
            $data = $this->parseXmlResponse($body);

            Log::info('Huella capturada del sensor', ['fingerNo' => $fingerNo, 'raw' => $body, 'data' => $data]);

            if (empty($data['fingerData'])) {
                return [
                    'error' => true,
                    'message' => 'El dispositivo no devolvió los datos de la huella capturada',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Huella capturada',
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Error capturando huella en Hikvision: ' . $e->getMessage(), [
                'fingerNo' => $fingerNo,
            ]);

            return [
                'error' => true,
                'message' => $this->traducirErrorHuella($e),
                'data' => [],
            ];
        }
    }

    /**
     * Paso 2: vincula un template de huella ya capturado (fingerData) al employeeNo
     * y slot (fingerPrintID) indicados, guardándolo en el perfil del empleado.
     *
     * El empleado debe existir previamente en el dispositivo (UserInfo), de lo
     * contrario el dispositivo responde "OK" pero no persiste nada.
     *
     * Nota de nomenclatura ISAPI (confirmado contra el dispositivo real): a pesar del
     * nombre, "FingerPrintDownload" es la operación de escritura (el dispositivo
     * "descarga" el dato hacia su almacenamiento); "FingerPrintUpload" es la de
     * consulta (el dispositivo "sube" el dato hacia el cliente).
     *
     * @return array{error: bool, message: string, data: array}
     */
    /**
     * Vincula la huella capturada en TODOS los terminales (el template es el
     * mismo hardware/modelo en todos, así que un mismo fingerData es válido en
     * cualquiera), para que el empleado pueda marcar en cualquier puerta.
     */
    private function vincularHuellaEmpleado(string $employeeNo, int $fingerPrintID, string $fingerData): array
    {
        $resultado = $this->fanOut(function (Client $client) use ($employeeNo, $fingerPrintID, $fingerData) {
            try {
                $response = $client->post('/ISAPI/AccessControl/FingerPrintDownload?format=json', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept'       => 'application/json',
                    ],
                    'json' => [
                        'FingerPrintCfg' => [
                            'employeeNo'       => $employeeNo,
                            'fingerPrintID'    => $fingerPrintID,
                            'fingerType'       => 'normalFP',
                            'fingerData'       => $fingerData,
                            'enableCardReader' => [1],
                        ],
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                Log::info('Huella vinculada a empleado', ['employeeNo' => $employeeNo, 'fingerPrintID' => $fingerPrintID, 'data' => $data]);

                return ['error' => false, 'message' => 'Huella registrada correctamente', 'data' => $data ?? []];
            } catch (\Exception $e) {
                Log::error('Error vinculando huella en Hikvision: ' . $e->getMessage(), [
                    'employeeNo' => $employeeNo,
                    'fingerPrintID' => $fingerPrintID,
                ]);

                return ['error' => true, 'message' => $this->traducirErrorHuella($e), 'data' => []];
            }
        });

        return [
            'error' => $resultado['error'],
            'message' => $resultado['error'] ? 'Error al registrar la huella en el dispositivo' : 'Huella registrada correctamente',
            'data' => $resultado['devices'],
        ];
    }

    /**
     * Traduce errores conocidos del dispositivo (timeout esperando el dedo, employeeNo
     * inexistente, huella duplicada, límite alcanzado) a un mensaje legible para el
     * modal del frontend. Si el código no es reconocido, se conserva el mensaje crudo
     * del dispositivo para no ocultar información útil para soporte.
     */
    private function traducirErrorHuella(\Exception $e): string
    {
        if ($e instanceof ConnectException || stripos($e->getMessage(), 'timed out') !== false) {
            return 'Tiempo de espera agotado: no se detectó ningún dedo en el lector';
        }

        $body = (method_exists($e, 'getResponse') && $e->getResponse())
            ? $e->getResponse()->getBody()->getContents()
            : null;

        $decoded = $body ? json_decode($body, true) : null;
        $subStatusCode = $decoded['subStatusCode'] ?? null;

        $mensajes = [
            'employeeNoNotExist' => 'El empleado no está registrado en el dispositivo',
            'fingerPrintMaxNumExceed' => 'Se alcanzó el límite de huellas permitidas para este empleado',
            'fingerPrintExist' => 'Esta huella ya está registrada',
        ];

        if ($subStatusCode && isset($mensajes[$subStatusCode])) {
            return $mensajes[$subStatusCode];
        }

        return $decoded['errorMsg'] ?? $body ?? 'Error al registrar la huella en el dispositivo';
    }

    /**
     * Elimina TODAS las huellas registradas de un empleado en el dispositivo.
     *
     * El firmware de este equipo (confirmado con pruebas directas contra el
     * dispositivo real) solo soporta el mode "byEmployeeNo" para borrar huellas,
     * y este mode ignora el parámetro fingerPrintID a pesar de que las
     * capabilities ISAPI (GET /ISAPI/AccessControl/FingerPrint/Delete/capabilities)
     * lo documentan como válido: siempre borra todas las huellas del empleado.
     * No existe forma de eliminar una huella individual en este modelo, por lo
     * que el flujo de UI es "borrar todas y volver a registrar".
     *
     * @return array{error: bool, message: string, data: array}
     */
    public function eliminarTodasLasHuellasEmpleado(string $employeeNo): array
    {
        $resultado = $this->fanOut(function (Client $client) use ($employeeNo) {
            try {
                $response = $client->put('/ISAPI/AccessControl/FingerPrint/Delete?format=json', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept'       => 'application/json',
                    ],
                    'json' => [
                        'FingerPrintDelete' => [
                            'mode' => 'byEmployeeNo',
                            'EmployeeNoDetail' => [
                                'employeeNo' => $employeeNo,
                            ],
                        ],
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                Log::info('Huellas eliminadas del empleado', ['employeeNo' => $employeeNo, 'data' => $data]);

                return ['error' => false, 'message' => 'Huellas eliminadas correctamente', 'data' => $data ?? []];
            } catch (\Exception $e) {
                Log::error('Error eliminando huellas en Hikvision: ' . $e->getMessage(), [
                    'employeeNo' => $employeeNo,
                ]);

                return ['error' => true, 'message' => $this->extraerMensajeError($e), 'data' => []];
            }
        });

        return [
            'error' => $resultado['error'],
            'message' => $resultado['error'] ? 'Error al eliminar las huellas del dispositivo' : 'Huellas eliminadas correctamente',
            'data' => $resultado['devices'],
        ];
    }

    /**
     * Registra a un empleado ya existente en el dispositivo capturando el número de tarjeta
     * directamente del lector (GET /ISAPI/AccessControl/CaptureCardInfo, bloqueante ~10-25s
     * esperando a que se acerque la tarjeta) y luego vinculándolo vía registrarTarjetaEmpleado.
     *
     * @return array{error: bool, message: string, data: array}
     */
    public function registrarTarjetaEmpleadoConCaptura(string $employeeNo, string $cardType = 'normalCard', ?string $deviceId = null): array
    {
        $deviceId ??= $this->primaryDeviceId();

        if (!$this->empleadoExisteEnDispositivo($employeeNo, $deviceId)) {
            return [
                'error' => true,
                'message' => 'El empleado no está registrado en el dispositivo',
                'data' => [],
            ];
        }

        $captura = $this->capturarTarjeta($deviceId);

        if ($captura['error']) {
            return $captura;
        }

        return $this->registrarTarjetaEmpleado($employeeNo, $captura['data']['cardNo'], $cardType);
    }

    /**
     * Pone al lector del dispositivo en modo espera de tarjeta (confirmado contra el
     * dispositivo real: bloquea ~10s+ antes de responder, igual que CaptureFingerPrint/
     * CaptureFaceData, aunque no aparezca en /ISAPI/AccessControl/CardInfo/capabilities).
     * A diferencia de esos dos, es GET (no POST) y no requiere cuerpo XML. La respuesta de
     * éxito trae el número en `CardInfo.cardNo` (confirmado contra el dispositivo real).
     *
     * @return array{error: bool, message: string, data: array}
     */
    private function capturarTarjeta(string $deviceId): array
    {
        try {
            $response = $this->client($deviceId)->get('/ISAPI/AccessControl/CaptureCardInfo?format=json', [
                'timeout' => 25,
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            Log::info('Tarjeta capturada del lector', ['raw' => $body, 'data' => $data]);

            $cardNo = $data['CardInfo']['cardNo']
                ?? $data['cardNo']
                ?? $data['CardNo']
                ?? $data['CaptureCardInfo']['cardNo'] ?? null;

            if (!$cardNo) {
                Log::warning('Tarjeta: respuesta de captura sin cardNo reconocible', ['data' => $data]);

                return [
                    'error' => true,
                    'message' => 'El dispositivo no devolvió el número de la tarjeta capturada',
                    'data' => [],
                ];
            }

            return [
                'error' => false,
                'message' => 'Tarjeta capturada',
                'data' => ['cardNo' => (string) $cardNo],
            ];
        } catch (\Exception $e) {
            Log::error('Error capturando tarjeta en Hikvision: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => $this->traducirErrorCapturaTarjeta($e),
                'data' => [],
            ];
        }
    }

    /**
     * Traduce errores conocidos de la captura de tarjeta (timeout esperando la tarjeta,
     * dispositivo ocupado) a un mensaje legible. Si el código no es reconocido, se
     * conserva el mensaje crudo del dispositivo.
     */
    private function traducirErrorCapturaTarjeta(\Exception $e): string
    {
        if ($e instanceof ConnectException || stripos($e->getMessage(), 'timed out') !== false) {
            return 'Tiempo de espera agotado: no se detectó ninguna tarjeta en el lector';
        }

        $body = (method_exists($e, 'getResponse') && $e->getResponse())
            ? $e->getResponse()->getBody()->getContents()
            : null;

        $decoded = $body ? json_decode($body, true) : null;
        $subStatusCode = $decoded['subStatusCode'] ?? null;

        $mensajes = [
            'deviceError' => 'Tiempo de espera agotado: no se detectó ninguna tarjeta en el lector',
            'deviceBusy' => 'El lector está ocupado, intenta de nuevo en unos segundos',
        ];

        if ($subStatusCode && isset($mensajes[$subStatusCode])) {
            return $mensajes[$subStatusCode];
        }

        return $decoded['errorMsg'] ?? $body ?? 'Error al capturar la tarjeta desde el dispositivo';
    }

    /**
     * Registra (o sobrescribe) una tarjeta de proximidad para un empleado ya existente
     * en el dispositivo, vía CardInfo/Record, a partir de un cardNo ya conocido (capturado
     * con registrarTarjetaEmpleadoConCaptura, o excepcionalmente ya impreso en la tarjeta).
     *
     * @return array{error: bool, message: string, data: array}
     */
    /**
     * Vincula la tarjeta en TODOS los terminales, para que el empleado pueda
     * marcar en cualquier puerta. El chequeo previo de existencia se hace solo
     * contra el dispositivo principal (sanity check rápido); si el empleado no
     * llegó a existir en algún otro terminal, ese fallo queda expuesto en 'devices'.
     */
    public function registrarTarjetaEmpleado(string $employeeNo, string $cardNo, string $cardType = 'normalCard'): array
    {
        if (!$this->empleadoExisteEnDispositivo($employeeNo, $this->primaryDeviceId())) {
            return [
                'error' => true,
                'message' => 'El empleado no está registrado en el dispositivo',
                'data' => [],
            ];
        }

        $resultado = $this->fanOut(function (Client $client) use ($employeeNo, $cardNo, $cardType) {
            try {
                $response = $client->post('/ISAPI/AccessControl/CardInfo/Record?format=json', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => [
                        'CardInfo' => [
                            'employeeNo' => $employeeNo,
                            'cardNo' => $cardNo,
                            'cardType' => $cardType,
                        ],
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                Log::info('Tarjeta registrada a empleado', ['employeeNo' => $employeeNo, 'cardNo' => $cardNo, 'data' => $data]);

                return ['error' => false, 'message' => 'Tarjeta registrada correctamente', 'data' => $data ?? []];
            } catch (\Exception $e) {
                // El dispositivo ya tiene esta tarjeta vinculada (típico al reintentar
                // un registro que ya había tenido éxito en este terminal pero falló en
                // otro): el objetivo real ("que la tarjeta quede en el dispositivo") ya
                // se cumple, así que se trata como éxito en vez de bloquear el reintento.
                $bodyDecoded = json_decode($this->extraerMensajeError($e), true);

                if (in_array($bodyDecoded['subStatusCode'] ?? null, ['cardNoAlreadyExist', 'cardNoExist'], true)) {
                    return ['error' => false, 'message' => 'La tarjeta ya estaba registrada en el dispositivo'];
                }

                Log::error('Error registrando tarjeta en Hikvision: ' . $e->getMessage(), [
                    'employeeNo' => $employeeNo,
                    'cardNo' => $cardNo,
                ]);

                return ['error' => true, 'message' => $this->traducirErrorTarjeta($e), 'data' => []];
            }
        });

        return [
            'error' => $resultado['error'],
            'message' => $resultado['error'] ? 'Error al registrar la tarjeta en el dispositivo' : 'Tarjeta registrada correctamente',
            'data' => $resultado['devices'],
        ];
    }

    /**
     * Elimina TODAS las tarjetas registradas de un empleado en el dispositivo.
     *
     * @return array{error: bool, message: string, data: array}
     */
    public function eliminarTarjetaEmpleado(string $employeeNo): array
    {
        $resultado = $this->fanOut(function (Client $client) use ($employeeNo) {
            try {
                $response = $client->put('/ISAPI/AccessControl/CardInfo/Delete?format=json', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ],
                    'json' => [
                        'CardInfoDelCond' => [
                            'mode' => 'byEmployeeNo',
                            'EmployeeNoList' => [
                                ['employeeNo' => $employeeNo],
                            ],
                        ],
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                Log::info('Tarjetas eliminadas del empleado', ['employeeNo' => $employeeNo, 'data' => $data]);

                return ['error' => false, 'message' => 'Tarjetas eliminadas correctamente', 'data' => $data ?? []];
            } catch (\Exception $e) {
                Log::error('Error eliminando tarjetas en Hikvision: ' . $e->getMessage(), [
                    'employeeNo' => $employeeNo,
                ]);

                return ['error' => true, 'message' => $this->extraerMensajeError($e), 'data' => []];
            }
        });

        return [
            'error' => $resultado['error'],
            'message' => $resultado['error'] ? 'Error al eliminar las tarjetas del dispositivo' : 'Tarjetas eliminadas correctamente',
            'data' => $resultado['devices'],
        ];
    }

    /**
     * Traduce errores conocidos del dispositivo (tarjeta duplicada, employeeNo inexistente)
     * a un mensaje legible para el frontend. Si el código no es reconocido, se conserva el
     * mensaje crudo del dispositivo.
     */
    private function traducirErrorTarjeta(\Exception $e): string
    {
        $body = (method_exists($e, 'getResponse') && $e->getResponse())
            ? $e->getResponse()->getBody()->getContents()
            : null;

        $decoded = $body ? json_decode($body, true) : null;
        $subStatusCode = $decoded['subStatusCode'] ?? null;

        $mensajes = [
            'employeeNoNotExist' => 'El empleado no está registrado en el dispositivo',
            'cardNoAlreadyExist' => 'Esta tarjeta ya está registrada',
            'cardNoExist' => 'Esta tarjeta ya está registrada',
        ];

        if ($subStatusCode && isset($mensajes[$subStatusCode])) {
            return $mensajes[$subStatusCode];
        }

        return $decoded['errorMsg'] ?? $body ?? 'Error al registrar la tarjeta en el dispositivo';
    }

    /**
     * Registra el rostro de un empleado ya existente en el dispositivo, en dos pasos:
     * 1) CaptureFaceData: a diferencia de la huella, no es una llamada bloqueante única;
     *    cada POST devuelve el progreso actual (captureProgress, en XML) mientras no hay
     *    rostro detectado, así que hay que reconsultar mientras el usuario se posiciona
     *    frente a la cámara. Al completarse, el dispositivo entrega la imagen JPEG cruda
     *    en el cuerpo de la respuesta (no XML), porque se pide con dataType=binary.
     * 2) Se sube esa imagen (multipart) a la biblioteca de rostros (FDID) del dispositivo,
     *    vinculada al employeeNo.
     *
     * Importante: una vez el dispositivo entrega la imagen, la sesión de captura queda
     * cerrada en ese mismo instante — seguir llamando a CaptureFaceData después de eso
     * (p. ej. por un bug en el conteo del bucle) hace que el dispositivo responda 400
     * badParameters y, confirmado en pruebas reales, puede dejar el submódulo de captura
     * sin responder durante varios minutos. Por eso el bucle corta de inmediato al
     * recibir contenido no-XML, sin reintentar.
     *
     * @return array{error: bool, message: string, data: array}
     */
    public function registrarRostroEmpleado(string $employeeNo, string $faceLibraryId = '1', ?string $deviceId = null): array
    {
        $deviceId ??= $this->primaryDeviceId();

        if (!$this->empleadoExisteEnDispositivo($employeeNo, $deviceId)) {
            return [
                'error' => true,
                'message' => 'El empleado no está registrado en el dispositivo',
                'data' => [],
            ];
        }

        Cache::forget($this->cancelacionRostroCacheKey($employeeNo, $deviceId));

        $captura = $this->capturarRostro($employeeNo, $deviceId);

        if ($captura['error']) {
            return $captura;
        }

        return $this->vincularRostroEmpleado($employeeNo, $faceLibraryId, $captura['data']['imagenJpeg']);
    }

    /**
     * Marca como cancelada la captura de rostro en curso para un empleado, para que
     * el bucle de polling de capturarRostro() (que corre en la petición de registrarRostroEmpleado,
     * potencialmente en otro proceso PHP-FPM) la detecte y se detenga antes de agotar sus reintentos.
     */
    public function cancelarRegistroRostro(string $employeeNo, ?string $deviceId = null): array
    {
        $deviceId ??= $this->primaryDeviceId();

        Cache::put($this->cancelacionRostroCacheKey($employeeNo, $deviceId), true, now()->addSeconds(30));

        return [
            'error' => false,
            'message' => 'Se solicitó la cancelación de la captura de rostro',
            'data' => [],
        ];
    }

    // Namespaced por deviceId: dos terminales capturando el mismo employeeNo en
    // paralelo no deben pisarse la cancelación entre sí.
    private function cancelacionRostroCacheKey(string $employeeNo, string $deviceId): string
    {
        return "hikvision:cancelar-captura-rostro:{$deviceId}:{$employeeNo}";
    }

    /**
     * Paso 1: captura el rostro desde la cámara del dispositivo.
     *
     * Confirmado contra el dispositivo real: este submódulo, igual que CaptureFingerPrint,
     * solo acepta/devuelve XML (ignora "?format=json"). Mientras no se detecta un rostro,
     * cada llamada responde de inmediato en XML con el progreso actual (captureProgress);
     * al completarse, el cuerpo de la respuesta deja de ser XML y pasa a ser la imagen
     * JPEG cruda capturada, momento en el que se debe detener el bucle inmediatamente.
     *
     * @return array{error: bool, message: string, data: array}
     */
    private function capturarRostro(string $employeeNo, string $deviceId, int $maxIntentos = 25, int $intervaloMs = 700): array
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><CaptureFaceDataCond><captureInfrared>false</captureInfrared><dataType>binary</dataType></CaptureFaceDataCond>';
        $client = $this->client($deviceId);

        try {
            for ($intento = 1; $intento <= $maxIntentos; $intento++) {
                if (Cache::pull($this->cancelacionRostroCacheKey($employeeNo, $deviceId))) {
                    Log::info('Captura de rostro cancelada por el usuario', ['employeeNo' => $employeeNo]);

                    return [
                        'error' => true,
                        'message' => 'Captura de rostro cancelada',
                        'data' => [],
                    ];
                }

                $response = $client->post('/ISAPI/AccessControl/CaptureFaceData', [
                    'headers' => [
                        'Content-Type' => 'application/xml',
                    ],
                    'body' => $xml,
                    'timeout' => 10,
                ]);

                $body = $response->getBody()->getContents();

                if (!str_starts_with(ltrim($body), '<')) {
                    $imagen = $this->extraerJpegValido($body);

                    if ($imagen === null) {
                        Log::warning('Rostro: respuesta binaria sin marcadores JPEG reconocibles', [
                            'bytes' => strlen($body),
                            'primeros_bytes' => bin2hex(substr($body, 0, 32)),
                            'content_type' => $response->getHeaderLine('Content-Type'),
                        ]);

                        return [
                            'error' => true,
                            'message' => 'El dispositivo devolvió la imagen capturada en un formato inesperado',
                            'data' => [],
                        ];
                    }

                    Log::info('Rostro capturado del sensor (imagen binaria)', [
                        'bytes_originales' => strlen($body),
                        'bytes_jpeg' => strlen($imagen),
                        'content_type' => $response->getHeaderLine('Content-Type'),
                    ]);

                    return [
                        'error' => false,
                        'message' => 'Rostro capturado',
                        'data' => ['imagenJpeg' => $imagen],
                    ];
                }

                usleep($intervaloMs * 1000);
            }

            return [
                'error' => true,
                'message' => 'Tiempo de espera agotado: no se detectó ningún rostro frente a la cámara',
                'data' => [],
            ];
        } catch (\Exception $e) {
            Log::error('Error capturando rostro en Hikvision: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => $this->traducirErrorRostro($e),
                'data' => [],
            ];
        }
    }

    /**
     * Recorta el contenido binario devuelto por CaptureFaceData a los límites reales del
     * JPEG (marcador SOI 0xFFD8FF ... EOI 0xFFD9), descartando cualquier byte de framing
     * que el dispositivo pueda anteponer o anexar. Sin este recorte, el dispositivo
     * rechaza la imagen en el paso de vinculación con "SubpicAnalysisModelingError"
     * (no logra modelar un rostro a partir de datos con bytes extra antes/después del JPEG).
     */
    private function extraerJpegValido(string $contenido): ?string
    {
        $inicio = strpos($contenido, "\xFF\xD8\xFF");

        if ($inicio === false) {
            return null;
        }

        $fin = strrpos($contenido, "\xFF\xD9");

        if ($fin === false || $fin < $inicio) {
            return substr($contenido, $inicio);
        }

        return substr($contenido, $inicio, $fin - $inicio + 2);
    }

    /**
     * Paso 2: sube la imagen JPEG ya capturada y la vincula al employeeNo y biblioteca
     * (FDID) indicados, vía multipart (metadatos JSON + archivo de imagen).
     *
     * Confirmado contra el dispositivo real: este endpoint exige PUT (POST responde
     * methodNotAllowed); con PUT y el multipart de abajo el dispositivo reconoce la
     * operación como "saveFacePic".
     *
     * @return array{error: bool, message: string, data: array}
     */
    /**
     * Sube la misma imagen capturada en un terminal a la biblioteca de rostros
     * de TODOS los terminales, para que el empleado pueda marcar en cualquier puerta.
     */
    private function vincularRostroEmpleado(string $employeeNo, string $faceLibraryId, string $imagenJpeg): array
    {
        $resultado = $this->fanOut(function (Client $client) use ($employeeNo, $faceLibraryId, $imagenJpeg) {
            try {
                $response = $client->put('/ISAPI/Intelligent/FDLib/FDSetUp?format=json', [
                    'multipart' => [
                        [
                            'name' => 'FaceDataRecord',
                            'contents' => json_encode([
                                'faceLibType' => 'blackFD',
                                'FDID' => $faceLibraryId,
                                'FPID' => $employeeNo,
                            ]),
                            'headers' => ['Content-Type' => 'application/json'],
                        ],
                        [
                            'name' => 'img',
                            'contents' => $imagenJpeg,
                            'filename' => 'face.jpg',
                            'headers' => ['Content-Type' => 'image/jpeg'],
                        ],
                    ],
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                Log::info('Rostro vinculado a empleado', ['employeeNo' => $employeeNo, 'data' => $data]);

                return ['error' => false, 'message' => 'Rostro registrado correctamente', 'data' => $data ?? []];
            } catch (\Exception $e) {
                Log::error('Error vinculando rostro en Hikvision: ' . $e->getMessage(), [
                    'employeeNo' => $employeeNo,
                ]);

                return ['error' => true, 'message' => $this->traducirErrorRostro($e), 'data' => []];
            }
        });

        return [
            'error' => $resultado['error'],
            'message' => $resultado['error'] ? 'Error al registrar el rostro en el dispositivo' : 'Rostro registrado correctamente',
            'data' => $resultado['devices'],
        ];
    }

    /**
     * Traduce errores conocidos del dispositivo (timeout esperando frente a la cámara,
     * employeeNo inexistente, rostro duplicado) a un mensaje legible para el modal del
     * frontend. Si el código no es reconocido, se conserva el mensaje crudo del dispositivo.
     */
    private function traducirErrorRostro(\Exception $e): string
    {
        if ($e instanceof ConnectException || stripos($e->getMessage(), 'timed out') !== false) {
            return 'Tiempo de espera agotado: no se detectó ningún rostro frente a la cámara';
        }

        $body = (method_exists($e, 'getResponse') && $e->getResponse())
            ? $e->getResponse()->getBody()->getContents()
            : null;

        $decoded = $body ? json_decode($body, true) : null;
        $subStatusCode = $decoded['subStatusCode'] ?? null;

        $mensajes = [
            'employeeNoNotExist' => 'El empleado no está registrado en el dispositivo',
            'faceExist' => 'Este rostro ya está registrado',
        ];

        if ($subStatusCode && isset($mensajes[$subStatusCode])) {
            return $mensajes[$subStatusCode];
        }

        return $decoded['errorMsg'] ?? $body ?? 'Error al registrar el rostro en el dispositivo';
    }
}
