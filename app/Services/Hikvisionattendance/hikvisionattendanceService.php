<?php

namespace App\Services\Hikvisionattendance;

use App\Models\Usuario;
use GuzzleHttp\Client; 
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Pool;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\StreamFactoryInterface;

class hikvisionattendanceService{
    protected Client $client;
    protected string $baseUrl;
    protected string $username;
    protected string $password;


    //Tipos de verificación
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
            'auth' => [$this->username, $this->password],
            'verify' => env('HIKVISION_VERIFY_SSL', false), //TODO: Solo para pruebas, no olvidar ponerlo en true en producción
            'timeout' => 30
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
     * @param mixed $maxRetries
     * @param mixed $delay
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
     * @param mixed $xmlContent
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
     * Obtener eventos de acceso (facial, huella, QR)
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
                'message' => "Acesso de eventos",
                'data' => $response->getBody()->getContents(),
            ];

        } catch (GuzzleException $e) {
            Log::error('Error obteniendo eventos de acceso: ' . $e->getMessage());

            return [
                'error' => true,
                'message' => "Error al obtener eventos",
                'data' => null,
            ];
        }
    }

    /**
     * OBTENER ASISTENCIA DE UN EMPLEADO ESPECIFICO... 
     * getEmployeeAttendance
     * @return array['error', 'message', 'data']
     */
    public function obtenerAsistenciaEmpleado($id_empleado, $start_date = null, $end_date = null){
        try{
            $inicio = $start_date ? date('c', strtotime($start_date)) : date('c', strtotime('-30 days'));
            $final = $end_date ? date('c', strtotime($end_date)) : date('c');

            $evento = $this->getAccessEvents($inicio, $final, 500);

            if ($evento['error'] || !isset($evento['data']['AccessLogSet']['AccessLog'])) {
                return [];
            }

            $logs = (array) $evento['data']['AccessLogSet']['AccessLog'];

            $asistencia = [];

            foreach($logs as $log){
                if(isset($log['EmployeeNo']) && $log['EmployeeNo'] == $id_empleado){
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
                'data' => $asistencia
                ];

        }catch(\Exception $e){
            Log::error('Error obteniendo la asistencia del empleado: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => 'Problemas obteniendo la asistencia del empleado',
                'data' => null
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
    public function registrarEmpleado($datos_empleado){
        try{
            $payload = [
                "UserInfo" => [
                    "employeeNo" => $datos_empleado['id_user'],
                    "name" => $datos_empleado['nombre'],
                    "userType" => $datos_empleado['perfil'],
                    "Valid" => [
                        "enable" => true,
                        "beginTime" => $datos_empleado['fechareg'],
                        "endTime" =>   "2035-12-31T23:59:59",
                        "timeType" =>  "local",
                    ],
                ],
            ];

            $response = $this->client->post(
                '/ISAPI/AccessControl/Employee', [
                    'body' => json_encode($payload),
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                ],
            );

            if($response->getStatusCode() === 201 || $response->getStatusCode() === 200){
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
        }catch(GuzzleException $e){
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
     * @param array $usuarios
     * @param int $concurrencias
     * @return array{data: array, error: bool, message: string}
     */
    public function registrarEmpleadosMasivo(array $usuarios, int $concurrencias = 5) {
        $result = [
            'success' => [],
            'error' => [],
        ];

        $request = function () use ($usuarios) {
            foreach($usuarios as $usuario){
                $payload = [
                    "UserInfo" => [
                        "employeeNo" => $usuario['id_user'],
                        "name" => $usuario['nombre'],
                        "userType" => $usuario['perfil'],
                        "Valid" => [
                            "enable" => true,
                            "beginTime" => $usuario['fechareg'],
                            "endTime" => "2035-12-31T23:59:59",
                            "timeType" => "local",
                        ],
                    ],
                ];

                yield new Request(
                    'POST',
                    '/ISAPI/AccessControl/Employee',
                    ['Content-Type' => 'Application/json'],
                    json_encode($payload)
                );
            }
        };
        
        $pool = new Pool($this->client, $request(), [
            'concurrency' => $concurrencias,
            'fulfilled' => function ($response, $index) use (&$result, $usuarios){
                $usuario = $usuarios[$index];

                if(in_array($response->getStatusCode(), [200, 201])){
                    $result['success'][] = [
                        'id_user' => $usuario['id_user'],
                        'message' => 'Registrado Correctamente',
                    ];
                } else {
                    $result['error'][] = [
                        'id_user' => $usuario['id_user'],
                        'message' => 'Respuesta inesperada en la petición a hikvision',
                    ];
                }
            }, 
            'rejected' => function ($reason, $index) use (&$result, $usuarios){
                $usuarios = $usuarios[$index];

                $result['error'][] = [
                    'id_user' => $usuarios['id_user'],
                    'message' => $reason instanceof \Exception 
                                        ? $reason->getMessage()
                                        : 'Error desconocido',
                ];
            },
        ]);

        //Se ejecuta aquí el pool
        $promise = $pool->promise();
        $promise->wait();

        return [
            'error' => false,
            'message' => 'Proceso completado',
            'data' => $result,
        ];
    }

    /**
     * Obtener listado de usuarios registrados en hikvision
     * @return array['error', 'message', 'data']
     */
    public function obtenerEmpleadosRegistrados($pageSize = 30, $pagina = 1){
        try {
            $response = $this->client->get(
                '/ISAPI/AccessControl/Employee', [
                    'query' => [
                        'pageSize' => $pageSize,
                        'pageNo' => $pagina,
                    ],
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ],
            );

            return [
                'error' => false,
                'message' => "Usuarios listados",
                'data' => $response->getBody()->getContents(),
            ];
        }catch(GuzzleException $e){
            Log::error('Error obteniendo el listado de empleados: ' . $e->getMessage());
            return [
                'error' => true,
                'message' => "Error listando a los usuarios",
                'data' => null
            ];
        }
    }

    /**
     * Summary of obtenerUnEmpleadoEspecifico
     * @param mixed $id_usuario
     * @return array['error', 'message', 'data']
     */
    public function obtenerUnEmpleadoEspecifico($id_usuario){
        try{
            $response = $this->client->get(
                "/ISAPI/AccessControl/Employee/$id_usuario", [
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ],
            );

            return [
                'error' => false,
                'message' => "Información obtenida",
                'data' => $response->getBody()->getContents(),
            ];
        }catch(GuzzleException $e){
            Log::error("Error al obtener la información del empleado: " . $e->getMessage());
            return [
                'error' => true,
                'message' => "No se pudo obtener la información del empleado... ",
                'data' => null
            ];
        }
    }


}
