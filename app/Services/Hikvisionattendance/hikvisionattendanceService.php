<?php

namespace App\Services\Hikvisionattendance;

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
    protected Client $client;

    protected string $baseUrl;

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
        $this->baseUrl = config('services.hikvision.protocol')
            . '://'
            . config('services.hikvision.host')
            . ':'
            . config('services.hikvision.port');

        $stack = HandlerStack::create();
        $stack->push($this->retryMiddleware(3, 1000)); // 3 intentos

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'auth' => [$this->username, $this->password, 'digest'], // 👈 Agregar 'digest'
            'verify' => env('HIKVISION_VERIFY_SSL', false),
            'timeout' => 30,
        ]);
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
     * Summary of testConnection
     * @return array{data: mixed, isConnected: bool|array{data: null, isConnected: int}}
     */
    public function testConnection()
    {
        try {
            Log::info("Probando conexión con dispositivo: $this->baseUrl");

            $response = $this->client->get('/ISAPI/System/capabilities?format=json');

            $body = $response->getBody()->getContents();

            $data = $this->parseXmlResponse($body);

            $isConnected = $response->getStatusCode() === 200;

            Log::info(
                'Conexión exitosa',
                [
                    'status' => $response->getStatusCode(),
                    'data' => $data,
                ]
            );

            return ['isConnected' => $isConnected, 'data' => $data];
        } catch (GuzzleException $e) {
            Log::error('Fallo en conexión con dispositivo', [
                'error' => $e->getMessage(),
                'base_url' => $this->baseUrl,
            ]);

            return ['isConnected' => false, 'data' => null];
        }
    }

    /**
     * Obtiene la configuración de los hosts HTTP a los que el dispositivo envía notificaciones de eventos.
     */
    public function obtenerHttpHosts()
    {
        try {
            $response = $this->client->get('/ISAPI/Event/notification/httpHosts?format=json');

            return [
                'error' => false,
                'data' => $this->parseXmlResponse($response->getBody()->getContents()),
            ];
        } catch (GuzzleException $e) {
            Log::error('Error al obtener httpHosts', ['error' => $e->getMessage()]);

            return [
                'error' => true,
                'message' => 'No se pudo obtener la configuración de httpHosts',
                'data' => null,
            ];
        }
    }

    /**
     * Obtener eventos de acceso (facial, huella, QR)
     *
     * @return array['error', 'message', 'data']
     */
    public function getAccessEvents($startTime = null, $endTime = null, $pageSize = 100)
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
            $response = $this->client->get('/ISAPI/AccessControl/AccessEvent', [
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

            $evento = $this->getAccessEvents($inicio, $final, 500);

            if ($evento['error'] || ! isset($evento['data']['AccessLogSet']['AccessLog'])) {
                return [];
            }

            $logs = (array) $evento['data']['AccessLogSet']['AccessLog'];

            $asistencia = [];

            foreach ($logs as $log) {
                if (isset($log['EmployeeNo']) && $log['EmployeeNo'] == $id_empleado) {
                    $asistencia[] = [
                        'employee_id' => $log['EmployeeNo'],
                        'name' => $log['EmployeeName'] ?? null,
                        'timestamp' => $log['AccessTime'],
                        'method' => $this->translateAccessMethod($log['AccessMethod'] ?? 'Desconocido'),
                        'door' => $log['DoorName'] ?? null,
                        'status' => $log['AccessStatus'] ?? 'Desconocido',
                        'raw_data' => $log,
                    ];
                }
            }

            $nombre_usuario = $logs[0]['EmployeeName'] ?? 'Empleado';

            return [
                'error' => false,
                'message' => "Asistencia obtenida del usuario: {$nombre_usuario}",
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
        try {
            $payload = [
                'UserInfo' => [
                    'employeeNo' => $datos_empleado['id_user'],
                    'name' => $datos_empleado['nombre'],
                    'userType' => 'normal',
                    'password' => $this->construirPasswordAsistencia((string) $datos_empleado['documento']),
                    'doorRight' => '1',
                    'RightPlan' => [
                        ['doorNo' => 1, 'planTemplateNo' => '1'],
                    ],
                    'Valid' => [
                        'enable' => true,
                        'beginTime' => now()->format('Y-m-d\TH:i:s'),
                        'endTime' => '2035-12-31T23:59:59',
                        'timeType' => 'local',
                    ],
                ],
            ];

            $groupId = $this->obtenerGroupIdPorPerfil((int) $datos_empleado['perfil']);

            if ($groupId !== null) {
                $payload['UserInfo']['groupId'] = $groupId;
            }

            $response = $this->client->post(
                '/ISAPI/AccessControl/Employee',
                [
                    'body' => json_encode($payload),
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ],
            );

            if ($response->getStatusCode() === 201 || $response->getStatusCode() === 200) {
                return [
                    'error' => false,
                    'id_user' => $datos_empleado['id_user'],
                    'message' => 'Usuario creado con exito',
                ];
            }

            return [
                'error' => true,
                'message' => 'Error al registrar el usuario',
                'id_user' => $datos_empleado['id_user'],
            ];
        } catch (GuzzleException $e) {
            Log::error('Error registrando al empleado: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => $e->getMessage(),
                'id_user' => $datos_empleado['id_user'],
            ];
        }
    }

    /**
     * Summary of registrarEmpleadosMasivo
     *
     * @return array{data: array, error: bool, message: string}
     */
    public function registrarEmpleadosMasivo(array $usuarios, int $concurrencias = 5)
    {
        $result = [
            'success' => [],
            'error' => [],
        ];

        $requests = function () use ($usuarios) {
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
                            'beginTime' => now()->format('Y-m-d\TH:i:s'),
                            'endTime'   => '2035-12-31T23:59:59',
                            'timeType'  => 'local',
                        ],
                    ],
                ];

                $groupId = $this->obtenerGroupIdPorPerfil((int) $usuario['perfil']);

                if ($groupId !== null) {
                    $data['UserInfo']['groupId'] = $groupId;
                }

                yield function () use ($data) {
                    return $this->client->postAsync(
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

        $pool = new Pool($this->client, $requests(), [
            'concurrency' => $concurrencias,

            'fulfilled' => function ($response, $index) use (&$result, $usuarios) {
                $usuario = $usuarios[$index];

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

        return [
            'error' => false,
            'message' => 'Proceso completado',
            'data' => $result,
        ];
    }

    /**
     * Summary of obtenerEmpleadosRegistrados
     * @param int $pageSize
     * @param int $pagina
     * @return array{data: array, error: bool, message: string|array{data: null, error: bool, message: string}}
     */
    public function obtenerEmpleadosRegistrados(int $pageSize = 30, int $pagina = 1)
    {
        try {
            $todosLosEmpleados = [];
            $pageSize = 30;
            $offset = 0;

            do {
                $response = $this->client->post('/ISAPI/AccessControl/UserInfo/Search?format=json', [
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

            foreach ($todosLosEmpleados as &$empleado) {
                if (!empty($empleado['faceURL'])) {
                    $empleado['faceURL'] = $this->proxyImagenUrl($empleado['faceURL']);
                }

                $empleado['contrasenaVigente'] = $this->tieneContrasenaVigente($empleado);
            }
            unset($empleado);

            return [
                'error' => false,
                'message' => "Usuarios registrados obtenidos",
                'data' => $todosLosEmpleados
            ];
        } catch (\Exception $e) {
            Log::error('Error obteniendo todos los empleados: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => "Error obteniendo a los usuarios registrados en hikvision",
                'data' => null,
            ];
        }
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
     * @return array{error: bool, message: string, data: array{contenido: string, contentType: string}|null}
     */
    public function obtenerImagenEmpleado(string $path): array
    {
        try {
            $rutaRelativa = $this->extraerRutaRelativa($path);

            if (str_contains($rutaRelativa, '://')) {
                throw new \InvalidArgumentException('Ruta de imagen inválida');
            }

            if (!str_starts_with($rutaRelativa, '/')) {
                $rutaRelativa = '/' . $rutaRelativa;
            }

            $response = $this->client->get($rutaRelativa);

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
     * del backend, para que el frontend nunca reciba la IP ni las credenciales del dispositivo.
     */
    private function proxyImagenUrl(string $faceUrl): string
    {
        $rutaRelativa = $this->extraerRutaRelativa($faceUrl);

        return '/api/hikvision/image?path=' . urlencode($rutaRelativa);
    }

    /**
     * Elimina el protocolo + host del dispositivo de una URL absoluta, dejando
     * solo la ruta relativa que se le puede pasar a $this->client (que ya trae el host configurado).
     */
    private function extraerRutaRelativa(string $path): string
    {
        if (str_starts_with($path, $this->baseUrl)) {
            return substr($path, strlen($this->baseUrl));
        }

        return $path;
    }

    /**
     * Summary of obtenerUnEmpleadoEspecifico
     * @param mixed $id_usuario
     * @return array{data: array<int|string>, error: bool, message: string|array{data: array{data: null, status: int}, error: bool, message: string}}
     */
    public function obtenerUnEmpleadoEspecifico($id_usuario)
    {
        try {
            $response = $this->client->post('/ISAPI/AccessControl/UserInfo/Search?format=json', [
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

            $response = $this->client->post('/ISAPI/AccessControl/UserInfo/Search?format=json', [
                'json' => [
                    'UserInfoSearchCond' => [
                        'searchID' => (string) now()->timestamp,
                        'searchResultPosition' => 0,
                        'maxResults' => count($usuarios), // importante
                        'EmployeeNoList' => $employeeList,
                    ],
                ],
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            $groupIdPorEmployeeNo = [];
            foreach ($usuarios as $usuario) {
                $groupIdPorEmployeeNo[(string) $usuario['id_user']] = $this->obtenerGroupIdPorPerfil((int) $usuario['perfil']);
            }

            if (isset($data['UserInfoSearch']['UserInfo']) && is_array($data['UserInfoSearch']['UserInfo'])) {
                foreach ($data['UserInfoSearch']['UserInfo'] as &$infoUsuario) {
                    $infoUsuario['groupId'] = $groupIdPorEmployeeNo[(string) ($infoUsuario['employeeNo'] ?? '')] ?? null;
                }
                unset($infoUsuario);
            }

            return [
                'error' => false,
                'message' => 'Información obtenida correctamente',
                'data' => $data,
            ];
        } catch (GuzzleException $e) {
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

        try{
            $response = $this->client->put('/ISAPI/AccessControl/UserInfo/Delete?format=json', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => $body
            ]);

            return [
                'error' => false,
                'message' => "Usuarios borrados",
                'data' => json_decode($response->getBody()->getContents(), true),
            ];
        }catch(\Exception $e){
            $errorMsg = $e->getMessage();

            // Si el error es 400, intentemos ver qué dijo el equipo exactamente
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $errorMsg = $e->getResponse()->getBody()->getContents();
            }

            Log::error("Error eliminando en Hikvision: " . $errorMsg);

            return [
                'error' => true,
                'message' => "Error al borrar: " . $errorMsg
            ];
        }
    }

    /**
     * Elimina TODOS los empleados que el dispositivo reporta en este momento (vía
     * obtenerEmpleadosRegistrados), sin filtrar por nuestra BD. Incluye cuentas
     * manuales/admin que nunca pasaron por esta app. Acción destructiva e irreversible,
     * pensada para limpiar registros erróneos — no para uso recurrente desde la UI.
     */
    public function eliminarTodosLosUsuariosDelDispositivo(): array
    {
        $listado = $this->obtenerEmpleadosRegistrados();

        if ($listado['error']) {
            return $listado;
        }

        $empleados = $listado['data'];

        if (empty($empleados)) {
            return ['error' => false, 'message' => 'El dispositivo no tiene usuarios registrados', 'data' => []];
        }

        $employeeNos = array_map(fn ($empleado) => (string) $empleado['employeeNo'], $empleados);

        try {
            // El dispositivo limita el tamaño del lote de borrado, igual que en la búsqueda paginada.
            foreach (array_chunk($employeeNos, 30) as $lote) {
                $this->client->put('/ISAPI/AccessControl/UserInfo/Delete?format=json', [
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
            $errorMsg = $e->getMessage();

            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $errorMsg = $e->getResponse()->getBody()->getContents();
            }

            Log::error('Error eliminando TODOS los usuarios en Hikvision: '.$errorMsg);

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
        try{
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

            // CAMBIO: Endpoint /Modify y método PUT
            $response = $this->client->put("/ISAPI/AccessControl/UserInfo/Modify?format=json", [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => $body,
            ]);
    
            $data = json_decode($response->getBody()->getContents());
    
            return [
                'error' => false,
                'message' => $message,
                'data' => $data,
            ];
        }catch(\Exception $e){
            Log::error("Error al desactivar el usuario: " . $e->getMessage());
            return[
                'error' => true,
                'message' => "Error desactivando al usuario",
                'data' => [],
            ];
        }
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

            $response = $this->client->put('/ISAPI/AccessControl/UserInfo/Modify?format=json', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => $body,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            return [
                'error' => false,
                'message' => 'Información del usuario actualizada correctamente',
                'data' => $data,
            ];
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $errorMsg = $e->getResponse()->getBody()->getContents();
            }

            Log::error("Error actualizando usuario en Hikvision: " . $errorMsg);

            return [
                'error' => true,
                'message' => "No se pudo actualizar la información del usuario",
                'data' => $errorMsg,
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
        if ($this->obtenerInfoUsuarioDispositivo($employeeNo) === null) {
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
    public function registrarHuellaEmpleado(string $employeeNo, int $fingerPrintID = 1): array
    {
        if (!$this->empleadoExisteEnDispositivo($employeeNo)) {
            return [
                'error' => true,
                'message' => 'El empleado no está registrado en el dispositivo',
                'data' => [],
            ];
        }

        $captura = $this->capturarHuella($fingerPrintID);

        if ($captura['error']) {
            return $captura;
        }

        return $this->vincularHuellaEmpleado($employeeNo, $fingerPrintID, $captura['data']['fingerData']);
    }

    private function empleadoExisteEnDispositivo(string $employeeNo): bool
    {
        return $this->obtenerInfoUsuarioDispositivo($employeeNo) !== null;
    }

    /**
     * Obtiene el UserInfo del empleado tal como lo reporta el dispositivo (incluye
     * campos como 'password' que no se exponen a través de los demás métodos públicos),
     * o null si el empleado no existe en el dispositivo.
     */
    private function obtenerInfoUsuarioDispositivo(string $employeeNo): ?object
    {
        $resultado = $this->obtenerUnEmpleadoEspecifico($employeeNo);

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
    private function capturarHuella(int $fingerNo): array
    {
        try {
            // Este submódulo del dispositivo ignora "?format=json" y solo acepta/devuelve XML,
            // a diferencia del resto de endpoints de AccessControl (confirmado contra el equipo real).
            $xml = '<?xml version="1.0" encoding="UTF-8"?><CaptureFingerPrintCond><fingerNo>'
                . $fingerNo
                . '</fingerNo></CaptureFingerPrintCond>';

            $response = $this->client->post('/ISAPI/AccessControl/CaptureFingerPrint', [
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
    private function vincularHuellaEmpleado(string $employeeNo, int $fingerPrintID, string $fingerData): array
    {
        try {
            $response = $this->client->post('/ISAPI/AccessControl/FingerPrintDownload?format=json', [
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

            return [
                'error' => false,
                'message' => 'Huella registrada correctamente',
                'data' => $data ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Error vinculando huella en Hikvision: ' . $e->getMessage(), [
                'employeeNo' => $employeeNo,
                'fingerPrintID' => $fingerPrintID,
            ]);

            return [
                'error' => true,
                'message' => $this->traducirErrorHuella($e),
                'data' => [],
            ];
        }
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
        try {
            $response = $this->client->put('/ISAPI/AccessControl/FingerPrint/Delete?format=json', [
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

            return [
                'error' => false,
                'message' => 'Huellas eliminadas correctamente',
                'data' => $data ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Error eliminando huellas en Hikvision: ' . $e->getMessage(), [
                'employeeNo' => $employeeNo,
            ]);

            $body = (method_exists($e, 'getResponse') && $e->getResponse())
                ? $e->getResponse()->getBody()->getContents()
                : null;

            return [
                'error' => true,
                'message' => $body ?? 'Error al eliminar las huellas del dispositivo',
                'data' => [],
            ];
        }
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
    public function registrarRostroEmpleado(string $employeeNo, string $faceLibraryId = '1'): array
    {
        if (!$this->empleadoExisteEnDispositivo($employeeNo)) {
            return [
                'error' => true,
                'message' => 'El empleado no está registrado en el dispositivo',
                'data' => [],
            ];
        }

        Cache::forget($this->cancelacionRostroCacheKey($employeeNo));

        $captura = $this->capturarRostro($employeeNo);

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
    public function cancelarRegistroRostro(string $employeeNo): array
    {
        Cache::put($this->cancelacionRostroCacheKey($employeeNo), true, now()->addSeconds(30));

        return [
            'error' => false,
            'message' => 'Se solicitó la cancelación de la captura de rostro',
            'data' => [],
        ];
    }

    private function cancelacionRostroCacheKey(string $employeeNo): string
    {
        return "hikvision:cancelar-captura-rostro:{$employeeNo}";
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
    private function capturarRostro(string $employeeNo, int $maxIntentos = 25, int $intervaloMs = 700): array
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><CaptureFaceDataCond><captureInfrared>false</captureInfrared><dataType>binary</dataType></CaptureFaceDataCond>';

        try {
            for ($intento = 1; $intento <= $maxIntentos; $intento++) {
                if (Cache::pull($this->cancelacionRostroCacheKey($employeeNo))) {
                    Log::info('Captura de rostro cancelada por el usuario', ['employeeNo' => $employeeNo]);

                    return [
                        'error' => true,
                        'message' => 'Captura de rostro cancelada',
                        'data' => [],
                    ];
                }

                $response = $this->client->post('/ISAPI/AccessControl/CaptureFaceData', [
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
    private function vincularRostroEmpleado(string $employeeNo, string $faceLibraryId, string $imagenJpeg): array
    {
        try {
            $response = $this->client->put('/ISAPI/Intelligent/FDLib/FDSetUp?format=json', [
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

            return [
                'error' => false,
                'message' => 'Rostro registrado correctamente',
                'data' => $data ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Error vinculando rostro en Hikvision: ' . $e->getMessage(), [
                'employeeNo' => $employeeNo,
            ]);

            return [
                'error' => true,
                'message' => $this->traducirErrorRostro($e),
                'data' => [],
            ];
        }
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
